// MAIN-OWNED only, outside the PHP sandbox. No PHP execution, pulls or installs.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import { performance } from 'node:perf_hooks';
const exec = promisify(execFile);
const here = path.dirname(fileURLToPath(import.meta.url));
const lineage = JSON.parse(fs.readFileSync(path.join(here, 'lineage.json'), 'utf8'));
const endpoint = 'unix:///Users/famtastic-fritz/.colima/default/docker.sock';
const label = 'org.famtastic.worker-mariadb-proof';
const MiB = 1024 * 1024;
const approval = '--approve-ephemeral-mariadb';
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
let deadline = Infinity;
function need(ok, code) { if (!ok) throw new Error(code); }
function disk() {
  const s = fs.statfsSync(os.tmpdir());
  need(s.bavail * s.bsize >= 200 * MiB, 'disk_below_200MiB');
}
async function docker(args, env = {}, commandLimit = 15000) {
  const remaining = deadline - performance.now();
  need(remaining >= 1, 'overall_deadline_exceeded');
  // Never print Docker's raw stderr/inspect output or credential-bearing env.
  try {
    return (await exec('docker', ['--host', endpoint, ...args], {
      env: { ...process.env, ...env }, timeout: Math.floor(Math.min(commandLimit, remaining)), killSignal: 'SIGKILL', maxBuffer: MiB,
    })).stdout.trim();
  } catch { throw new Error(`docker_${args[0]}_failed`); }
}
function save(root, name, data) {
  fs.writeFileSync(path.join(root, name), JSON.stringify(data, null, 2) + '\n', { flag: 'wx', mode: 0o600 });
}
function readOwned(root, name) {
  const file = path.join(root, name), st = fs.lstatSync(file);
  need(st.isFile() && !st.isSymbolicLink() && st.uid === process.getuid() && !(st.mode & 0o022) && st.size < 8192, 'unsafe_journal_file');
  return fs.readFileSync(file, 'utf8');
}
function record(root, name, data) {
  if (fs.existsSync(path.join(root, name))) {
    need(readOwned(root, name) === JSON.stringify(data, null, 2) + '\n', 'conflicting_journal');
  } else save(root, name, data);
}
function ownedRoot(root) {
  const real = fs.realpathSync(root), st = fs.lstatSync(root);
  need(!st.isSymbolicLink() && st.isDirectory() && (st.mode & 0o077) === 0, 'unsafe_run_root');
  need(path.dirname(real) === fs.realpathSync(os.tmpdir()) && /^famtastic-worker-mariadb-[A-Za-z0-9]{6}$/.test(path.basename(real)), 'foreign_run_root');
  need(st.uid === process.getuid(), 'foreign_run_owner');
  return real;
}
async function networkOwned(id, run, name) {
  need(/^[a-f0-9]{64}$/.test(id), 'invalid_network_id');
  const n = JSON.parse(await docker(['network', 'inspect', id]))[0];
  need(n.Id === id && n.Name === name && n.Labels?.[label] === run && n.Internal === true && n.Driver === 'bridge', 'foreign_network');
  return n;
}
async function containerOwned(id, run, net, name) {
  need(/^[a-f0-9]{64}$/.test(id), 'invalid_container_id');
  const c = JSON.parse(await docker(['inspect', id]))[0], h = c.HostConfig;
  need(c.Id === id && c.Name === '/' + name && c.Image === lineage.image && c.Config.Labels?.[label] === run, 'foreign_container');
  need(h.Privileged === false && h.ReadonlyRootfs && h.Memory === 768 * MiB && h.MemorySwap === h.Memory
    && h.NanoCpus === 1e9 && h.PidsLimit === 128 && !h.Binds?.length, 'unsafe_container_limits');
  const mounts = h.Mounts || [];
  const sizes = { '/var/lib/mysql': 256 * MiB, '/run/mysqld': 8 * MiB, '/tmp': 32 * MiB };
  need(mounts.length === 3 && mounts.every(m => m.Type === 'tmpfs' && sizes[m.Target] === m.TmpfsOptions?.SizeBytes
    && m.TmpfsOptions?.Mode === (m.Target === '/tmp' ? 0o1777 : 0o700)), 'unsafe_mounts');
  need((c.Mounts || []).every(m => m.Type === 'tmpfs'), 'persistent_mount_forbidden');
  const networks = Object.values(c.NetworkSettings.Networks);
  need(h.NetworkMode === net && networks.length === 1
    && networks.every(n => n.NetworkID === net || (c.State.Status === 'created' && !n.NetworkID)), 'foreign_network_attachment');
  const ports = h.PortBindings;
  need(Object.keys(ports).length === 1 && ports['3306/tcp']?.length === 1 && ports['3306/tcp'][0].HostIp === '127.0.0.1', 'nonloopback_port');
  return c;
}
// Successful filtered list + exact identity comparison establishes absence.
// Failed inspect/list is uncertainty, NEVER an absent-resource result.
async function exactId(kind, name, recordedId) {
  const list = async filter => {
    const args = kind === 'container' ? ['container', 'ls', '--all', '--no-trunc'] : ['network', 'ls', '--no-trunc'];
    const out = await docker([...args, '--filter', filter, '--format', '{{json .}}']);
    return out ? out.split('\n').map(line => JSON.parse(line)) : [];
  };
  const rows = (await list(`name=${name}`)).filter(r => kind === 'container' ? r.Names === name : r.Name === name);
  need(rows.length <= 1, 'ambiguous_exact_name');
  if (!rows.length) {
    if (recordedId) need((await list(`id=${recordedId}`)).every(r => r.ID !== recordedId), 'recorded_resource_renamed');
    return null;
  }
  const id = rows[0].ID;
  need(/^[a-f0-9]{64}$/.test(id) && (!recordedId || recordedId === id), 'resource_identity_changed');
  return id;
}
async function create() {
  deadline = performance.now() + 60000; // Includes inspections/allocation/start/readiness.
  disk();
  const image = JSON.parse(await docker(['image', 'inspect', lineage.image]))[0];
  need(image.Id === lineage.image && image.Architecture === 'arm64', 'wrong_cached_image');
  const memory = Number(await docker(['info', '--format', '{{.MemTotal}}']));
  need(memory >= 4 * 1024 * MiB, 'engine_memory_too_small');
  // Operator must separately review CURRENT free engine RAM; MemTotal is not free.
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'famtastic-worker-mariadb-'));
  fs.chmodSync(root, 0o700);
  const run = crypto.randomBytes(12).toString('hex'), name = `famtastic-worker-proof-${run}`;
  const database = `wc_${run}`, username = `wc_${run.slice(0, 12)}`;
  const password = crypto.randomBytes(24).toString('hex'), rootPassword = crypto.randomBytes(24).toString('hex');
  save(root, 'owner.json', { schema: lineage.schema, run, image: lineage.image, endpoint, source_commit: lineage.source_commit,
    network_name: name, container_name: name, phase: 'allocation_intent' });
  // Print only an owned recovery path, before any resource allocation.
  console.log(JSON.stringify({ status: 'allocating', run_root: root }));
  save(root, 'network-intent.json', { name, run, phase: 'before_network_create' });
  const net = await docker(['network', 'create', '--internal', '--driver', 'bridge', '--label', `${label}=${run}`, name]);
  save(root, 'network.json', { id: net });
  await networkOwned(net, run, name); disk();
  save(root, 'container-intent.json', { name, run, network_id: net, phase: 'before_container_create' });
  const args = ['create', '--pull=never', '--name', name, '--hostname', `fwc-${run}`, '--label', `${label}=${run}`,
    '--cidfile', path.join(root, 'container.id'), '--network', net, '--publish', '127.0.0.1::3306',
    '--read-only', '--security-opt', 'no-new-privileges', '--cpus', '1', '--memory', '768m', '--memory-swap', '768m', '--pids-limit', '128',
    '--log-driver', 'local', '--log-opt', 'max-size=1m', '--log-opt', 'max-file=1', '--log-opt', 'compress=false',
    ...Object.entries({ '/var/lib/mysql': 256 * MiB, '/run/mysqld': 8 * MiB, '/tmp': 32 * MiB })
      .flatMap(([target, size]) => ['--mount', `type=tmpfs,destination=${target},tmpfs-size=${size},tmpfs-mode=${target === '/tmp' ? '1777' : '0700'}`]),
    '--env', 'MARIADB_ROOT_PASSWORD', '--env', 'MARIADB_PASSWORD', '--env', `MARIADB_DATABASE=${database}`, '--env', `MARIADB_USER=${username}`,
    lineage.image, '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=16M', '--max-connections=8',
    '--key-buffer-size=4M', '--tmp-table-size=8M', '--max-heap-table-size=8M', '--performance-schema=OFF',
    '--skip-log-bin', '--innodb-lock-wait-timeout=45'];
  const cid = await docker(args, { MARIADB_ROOT_PASSWORD: rootPassword, MARIADB_PASSWORD: password });
  await containerOwned(cid, run, net, name); disk();
  await docker(['start', cid]);
  let ready = false;
  while (performance.now() < deadline) {
    disk();
    try {
      // TCP avoids mistaking the entrypoint's temporary socket server for ready.
      await docker(['exec', '-e', 'MYSQL_PWD', cid, 'mariadb', '-uroot', '--protocol=TCP', '--host=127.0.0.1', '--connect-timeout=1',
        '--batch', '--skip-column-names', '-e', 'SELECT 1'], { MYSQL_PWD: rootPassword }, 3000);
      ready = true; break;
    } catch { if (performance.now() < deadline) await pause(Math.max(0, Math.min(250, deadline - performance.now()))); }
  }
  need(ready, 'database_not_ready');
  const c = await containerOwned(cid, run, net, name);
  const published = c.NetworkSettings.Ports['3306/tcp'];
  need(published?.length === 1 && published[0].HostIp === '127.0.0.1', 'unsafe_published_port');
  const port = Number(published[0].HostPort);
  need(port > 1024 && port !== 3306 && port !== 3400, 'unsafe_dynamic_port');
  // Literal identifiers/values below contain generated hex only, no operator SQL.
  const sql = `GRANT PROCESS ON *.* TO '${username}'@'%'; CREATE TABLE ${database}.proof_harness_owner (run_id VARCHAR(24) PRIMARY KEY, image_id VARCHAR(71) NOT NULL, source_commit VARCHAR(40) NOT NULL) ENGINE=InnoDB; INSERT INTO ${database}.proof_harness_owner VALUES ('${run}', '${lineage.image}', '${lineage.source_commit}');`;
  await docker(['exec', '-e', 'MYSQL_PWD', cid, 'mariadb', '-uroot', '--protocol=TCP', '--host=127.0.0.1', '--connect-timeout=1', '-e', sql], { MYSQL_PWD: rootPassword });
  need(performance.now() < deadline, 'overall_deadline_exceeded');
  save(root, 'connection.json', { schema: lineage.schema, run, image: lineage.image, source_commit: lineage.source_commit,
    host: '127.0.0.1', port, database, username, password, hostname: `fwc-${run}`, container_id: cid });
  save(root, 'provision-receipt.json', { status: 'provisioned_not_tested', run, container_id: cid, network_id: net,
    image: lineage.image, source_commit: lineage.source_commit, host: '127.0.0.1', port, persistent_volumes: false });
  console.log(JSON.stringify({ status: 'provisioned_not_tested', run_root: root, connection_file: path.join(root, 'connection.json') }));
}
async function cleanup(argument) {
  deadline = performance.now() + 60000;
  const root = ownedRoot(argument), owner = JSON.parse(readOwned(root, 'owner.json'));
  const name = `famtastic-worker-proof-${owner.run}`;
  need(owner.schema === lineage.schema && owner.image === lineage.image && owner.endpoint === endpoint && owner.source_commit === lineage.source_commit
    && /^[a-f0-9]{24}$/.test(owner.run) && owner.network_name === name && owner.container_name === name && owner.phase === 'allocation_intent', 'foreign_owner_record');
  // Exact recorded intent names handle daemon success + lost client response.
  // Never discover by prefix/label alone, adopt foreign resources or delete volumes.
  const networkFile = path.join(root, 'network.json'), cidFile = path.join(root, 'container.id');
  const resolved = kind => fs.existsSync(path.join(root, `${kind}-resolved.json`)) ? JSON.parse(readOwned(root, `${kind}-resolved.json`)).id : null;
  const netAck = fs.existsSync(networkFile) ? JSON.parse(readOwned(root, 'network.json')).id : null;
  const cidAck = fs.existsSync(cidFile) ? readOwned(root, 'container.id').trim() || null : null;
  const netResolved = resolved('network'), cidResolved = resolved('container');
  need(!netAck || !netResolved || netAck === netResolved, 'conflicting_network_identity');
  need(!cidAck || !cidResolved || cidAck === cidResolved, 'conflicting_container_identity');
  const savedNet = netAck || netResolved, savedCid = cidAck || cidResolved;
  for (const id of [savedNet, savedCid]) need(id === null || /^[a-f0-9]{64}$/.test(id), 'invalid_recorded_id');
  const net = await exactId('network', name, savedNet), cid = await exactId('container', name, savedCid);
  if (net) {
    await networkOwned(net, owner.run, name);
    record(root, 'network-resolved.json', { id: net });
  }
  // A timed-out create may still be in flight. Missing ID + missing exact name
  // is NOT enough to certify cleanup. Retain uncertainty for main reconciliation.
  need(net || savedNet || !fs.existsSync(path.join(root, 'network-intent.json')), 'network_allocation_absence_uncertain');
  need(cid || savedCid || !fs.existsSync(path.join(root, 'container-intent.json')), 'container_allocation_absence_uncertain');
  if (cid) {
    need(net !== null, 'owned_container_network_missing');
    await containerOwned(cid, owner.run, net, name);
    record(root, 'container-resolved.json', { id: cid });
    await docker(['rm', '--force', cid]);
  }
  need(await exactId('container', name, cid || savedCid) === null, 'container_absence_unconfirmed');
  record(root, 'container-removed.json', { run: owner.run, name, status: 'exact_absence_confirmed' });
  if (net) {
    const n = await networkOwned(net, owner.run, name);
    need(Object.keys(n.Containers || {}).length === 0, 'network_not_empty');
    await docker(['network', 'rm', net]);
  }
  need(await exactId('network', name, net || savedNet) === null, 'network_absence_unconfirmed');
  const credentials = path.join(root, 'connection.json');
  if (fs.existsSync(credentials)) { need(!fs.lstatSync(credentials).isSymbolicLink(), 'unsafe_credentials_path'); fs.unlinkSync(credentials); }
  record(root, 'cleanup-receipt.json', { status: 'owned_resources_removed', run: owner.run, image: lineage.image });
  console.log(JSON.stringify({ status: 'owned_resources_removed', run_root: root, evidence_retained: true }));
}
const [mode, argument, flag] = process.argv.slice(2);
try {
  if (mode === 'create' && argument === approval && flag === undefined) await create();
  else if (mode === 'cleanup' && flag === approval) await cleanup(argument);
  else throw new Error('usage_create_APPROVAL_or_cleanup_RUNROOT_APPROVAL');
} catch (e) {
  const code = /^[A-Za-z][A-Za-z0-9_]*$/.test(e.message) ? e.message : 'unexpected_orchestration_failure';
  console.error(JSON.stringify({ status: 'provision_or_cleanup_failed', code, action: 'Retain run root; main must inspect and use exact-owned cleanup. No test pass.' }));
  process.exitCode = 2;
}
