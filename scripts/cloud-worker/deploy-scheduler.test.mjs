import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const source = readFileSync(new URL('../deploy-backend-godaddy.sh', import.meta.url), 'utf8');
const classifier = source.match(/# BEGIN OWNED_LIFECYCLE_CLASSIFIER[^\n]*\n([\s\S]*?)# END OWNED_LIFECYCLE_CLASSIFIER/)?.[1];
assert.ok(classifier, 'Use the actual canonical deployer classifier, never a parallel approximation.');
const root = '/home/xrdj7j99xhzt/public_html';
const deploy = '/home/xrdj7j99xhzt/deploy/famtastic-designs';
const old = `# FAMTASTIC_LIFECYCLE_CRON_V1\n*/5 * * * * cd ${root} && ${root}/vendor/bin/drush famtastic:lifecycle-run --limit=50 >/dev/null 2>&1`;
const bounded = mode => `# FAMTASTIC_BOUNDED_WORKER_CRON_V1\n*/5 * * * * cd ${root} && /usr/local/bin/php ${root}/vendor/bin/drush.php famtastic:automation-tick${mode ? ' --dispatch' : ''} >>${deploy}/bounded-worker.log 2>&1`;
function classify(cron) {
  // Only the pure extracted function is executed: no SSH, cron edit or deploy.
  return spawnSync('bash', ['-c', `set -euo pipefail\n${classifier}\nclassify_owned_lifecycle_cron`], {
    encoding: 'utf8', env: { PATH: process.env.PATH, production_dir: root, deploy_dir: deploy, drush: `${root}/vendor/bin/drush`, current_crontab: cron },
  });
}
for (const [name, input, mode] of [
  ['bounded observe preserved', bounded(false), 'bounded_observe'],
  ['bounded dispatch preserved', bounded(true), 'bounded_dispatch'],
  ['legacy remains explicit, not silently replaced', old, 'legacy'],
  ['absent schedule does not imply broad activation', 'MAILTO=""\n0 3 * * * /usr/local/bin/backup-site', 'none'],
  ['unrelated entries retained around bounded marker', `MAILTO=""\n0 3 * * * /usr/local/bin/backup-site\n${bounded(false)}\n`, 'bounded_observe'],
]) test(name, () => { const r = classify(input); assert.equal(r.status, 0, r.stderr); assert.equal(r.stdout.trim(), mode); });

for (const [name, input] of [
  ['two bounded markers', bounded(false) + '\n' + bounded(true)],
  ['bounded plus legacy', bounded(false) + '\n' + old],
  ['legacy duplicate', old + '\n' + old],
  ['altered PHP runtime', bounded(false).replace('/usr/local/bin/php', '/usr/bin/php')],
  ['altered mode', bounded(false).replace('automation-tick', 'automation-tick --dispatch --limit=50')],
  ['unknown marker version', bounded(false).replace('_CRON_V1', '_CRON_V2')],
  ['missing command', '# FAMTASTIC_BOUNDED_WORKER_CRON_V1'],
  ['unmarked dispatch', bounded(true).split('\n')[1]],
  ['unmarked broad cron beside bounded', bounded(false) + '\n*/5 * * * * /usr/bin/drush cron'],
  ['broad cron with Drush options', bounded(false) + '\n*/5 * * * * /usr/bin/drush --root=/other/site cron'],
]) test(`fail closed: ${name}`, () => { assert.notEqual(classify(input).status, 0); });

test('ordinary release validates before promotion and never installs another scheduler', () => {
  const preflight = source.indexOf('ordinary_lifecycle_mode="$(classify_owned_lifecycle_cron)"');
  const preflightExit = source.indexOf('if [[ "$mode" == "preflight" ]]');
  assert.ok(preflight > 0 && preflight < preflightExit);
  const block = source.slice(source.indexOf('# Installing a bounded marker'), source.indexOf("printf 'commit=%s"));
  assert.match(block, /current_crontab.*!=.*ordinary_cron_snapshot/);
  assert.match(block, /bounded_observe/); assert.match(block, /bounded_dispatch/);
  assert.doesNotMatch(block, /crontab\s+(?:"\$cron_stage"|-)|>>\s*"\$cron_stage"/);
  assert.doesNotMatch(source, /if ! grep -Fq "\$cron_marker"/);
});
