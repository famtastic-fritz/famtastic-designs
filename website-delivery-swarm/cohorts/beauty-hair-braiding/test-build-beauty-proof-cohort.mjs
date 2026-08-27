#!/usr/bin/env node

import { existsSync, readFileSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const repositoryRoot = resolve(new URL('../../..', import.meta.url).pathname);
const builder = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs');
const validator = join(repositoryRoot, 'website-delivery-swarm/scripts/validate-build-dna.mjs');
const promotion = join(repositoryRoot, 'scripts/promote-local-proof-godaddy.sh');
const inputJson = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/input.example.json');
const inputCsv = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/input.example.csv');

function run(command, args) {
  const result = spawnSync(command, args, { cwd: repositoryRoot, encoding: 'utf8' });
  if (result.status !== 0) throw new Error((result.stderr || result.stdout || 'command failed').trim());
  return result.stdout;
}

function assert(value, message) {
  if (!value) throw new Error(message);
}

function inspect(output, expected) {
  const manifest = JSON.parse(readFileSync(join(output, 'cohort-manifest.json'), 'utf8'));
  assert(manifest.selected_count === expected, 'wrong selected_count');
  assert(manifest.runtime_binding_status === 'unbound-local-preparation', 'cohort must remain explicitly unbound and non-importable');
  assert(manifest.no_external_actions.length === 5, 'external action record incomplete');
  for (const bundle of manifest.bundles) {
    const root = join(repositoryRoot, bundle.bundle);
    const dna = JSON.parse(readFileSync(join(root, 'build-dna.json'), 'utf8'));
    assert(dna.classification === 'local-preparation-only', 'wrong DNA classification');
    assert(dna.runtime_binding && dna.runtime_binding.status === 'unbound-local-preparation' && dna.runtime_binding.importable === false && dna.run === undefined, 'prepared Build DNA must remain explicitly non-importable before canonical binding');
    assert(dna.stages.some(function (stage) { return stage.stage_id === 'preview-art' && stage.result.status === 'gated'; }), 'art gate missing');
    for (const direction of ['a', 'b', 'c']) {
      const htmlPath = join(root, direction, 'index.html');
      const html = readFileSync(htmlPath, 'utf8');
      assert(existsSync(htmlPath), 'missing direction ' + direction);
      assert(!html.includes('@example.invalid'), 'contact email leaked into page');
      assert(!/<script\b/i.test(html), 'active content leaked into page');
      assert(Buffer.byteLength(html, 'utf8') <= 500000, 'page exceeds callback limit');
    }
    run(process.execPath, [validator, join(root, 'build-dna.json'), repositoryRoot]);
    run('bash', [promotion, root, '--directions=a,b,c']);
  }
}

const jsonOutput = join(repositoryRoot, 'artifacts', 'beauty-proof-tests-json-' + process.pid);
const csvOutput = join(repositoryRoot, 'artifacts', 'beauty-proof-tests-csv-' + process.pid);
try {
  run(process.execPath, [builder, '--input', inputJson, '--output', jsonOutput, '--limit', '2']);
  inspect(jsonOutput, 2);
  run(process.execPath, [builder, '--input', inputCsv, '--output', csvOutput, '--limit', '1']);
  inspect(csvOutput, 1);
  console.log('PASS: Beauty / Hair / Braiding cohort builder JSON and CSV contracts');
} finally {
  rmSync(jsonOutput, { recursive: true, force: true });
  rmSync(csvOutput, { recursive: true, force: true });
}
