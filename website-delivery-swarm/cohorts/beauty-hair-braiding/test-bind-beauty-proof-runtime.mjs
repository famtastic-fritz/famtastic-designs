#!/usr/bin/env node

import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const repositoryRoot = resolve(new URL('../../..', import.meta.url).pathname);
const builder = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs');
const binder = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/bind-beauty-proof-runtime.mjs');
const validator = join(repositoryRoot, 'website-delivery-swarm/scripts/validate-build-dna.mjs');
const sourceInput = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/input.example.json');
const artifactRoot = join(repositoryRoot, 'artifacts', 'beauty-proof-runtime-binding-tests-' + process.pid);

function writeJson(path, value) {
  writeFileSync(path, JSON.stringify(value, null, 2) + '\n');
}

function run(command, args) {
  const result = spawnSync(command, args, { cwd: repositoryRoot, encoding: 'utf8' });
  if (result.status !== 0) throw new Error((result.stderr || result.stdout || 'command failed').trim());
  return result.stdout;
}

function runFailure(command, args) {
  const result = spawnSync(command, args, { cwd: repositoryRoot, encoding: 'utf8' });
  if (result.status === 0) throw new Error('Expected command to fail: ' + command + ' ' + args.join(' '));
  return result.stderr || result.stdout;
}

function assert(value, message) {
  if (!value) throw new Error(message);
}

function verifiedInput(path) {
  const input = JSON.parse(readFileSync(sourceInput, 'utf8'));
  input.source.source_lane = 'verified_cold';
  input.package_profile = 'anonymous_safe_medium_ultra_v1';
  input.leads = [input.leads[0]];
  writeJson(path, input);
}

function bindingInput(cohortOutput, directory, campaignId) {
  const cohort = JSON.parse(readFileSync(join(cohortOutput, 'cohort-manifest.json'), 'utf8'));
  const input = {
    schema: 'famtastic.beauty-proof-runtime-binding-input.v1',
    source_lane: 'verified_cold',
    package_profile: 'anonymous_safe_medium_ultra_v1',
    cohort_manifest: join('artifacts', cohortOutput.split('/artifacts/')[1], 'cohort-manifest.json'),
    bindings: cohort.bundles.map(function (bundle, index) {
      return {
        bundle: bundle.bundle,
        prospect_id: 1101 + index,
        proof_campaign_id: 1201 + index,
        public_preview_delivery_id: 1301 + index,
        campaign_id: campaignId,
        job_id: 'public-preview:proof.generate:delivery:' + (1301 + index),
        callback_event_id: 'cold-proof:callback:' + campaignId + ':' + (index + 1),
        run_started_at: '2026-08-27T01:00:00.000Z',
      };
    }),
  };
  const path = join(directory, campaignId === cohort.campaign_id ? 'runtime-binding.json' : 'bad-runtime-binding.json');
  writeJson(path, input);
  return path;
}

try {
  const inputPath = join(artifactRoot, 'verified-input.json');
  const output = join(artifactRoot, 'cohort');
  mkdirSync(artifactRoot, { recursive: true });
  verifiedInput(inputPath);
  run(process.execPath, [builder, '--input', inputPath, '--output', output, '--limit', '1']);
  const cohort = JSON.parse(readFileSync(join(output, 'cohort-manifest.json'), 'utf8'));
  const bundle = cohort.bundles[0];
  const root = join(repositoryRoot, bundle.bundle);
  const preparedManifest = JSON.parse(readFileSync(join(root, 'manifest.json'), 'utf8'));
  const preparedDna = JSON.parse(readFileSync(join(root, 'build-dna.json'), 'utf8'));
  assert(cohort.runtime_binding_status === 'unbound-local-preparation', 'builder did not mark the cohort unbound');
  assert(preparedManifest.runtime_binding.importable === false && preparedDna.runtime_binding.importable === false && preparedDna.run === undefined, 'builder output must remain visibly non-importable before binding');

  const wrongCampaign = bindingInput(output, artifactRoot, cohort.campaign_id + '-wrong');
  const beforeFailure = readFileSync(join(root, 'manifest.json'), 'utf8');
  const wrongOutput = runFailure(process.execPath, [binder, '--input', wrongCampaign, '--dry-run']);
  assert(/campaign_id must exactly match/i.test(wrongOutput), 'mismatched canonical campaign ID was not rejected');
  assert(readFileSync(join(root, 'manifest.json'), 'utf8') === beforeFailure && !existsSync(join(root, 'runtime-binding.json')), 'rejected binding mutated a prepared bundle');

  const valid = bindingInput(output, artifactRoot, cohort.campaign_id);
  run(process.execPath, [binder, '--input', valid, '--dry-run']);
  assert(readFileSync(join(root, 'manifest.json'), 'utf8') === beforeFailure && !existsSync(join(root, 'runtime-binding.json')), 'binding dry-run mutated a prepared bundle');
  run(process.execPath, [binder, '--input', valid]);
  const manifest = JSON.parse(readFileSync(join(root, 'manifest.json'), 'utf8'));
  const dna = JSON.parse(readFileSync(join(root, 'build-dna.json'), 'utf8'));
  const sidecar = JSON.parse(readFileSync(join(root, 'runtime-binding.json'), 'utf8'));
  const boundCohort = JSON.parse(readFileSync(join(output, 'cohort-manifest.json'), 'utf8'));
  assert(boundCohort.runtime_binding_status === 'bound-canonical-runtime' && boundCohort.runtime_binding_contract === 'famtastic.beauty-proof-runtime-binding.v1', 'cohort did not record completed canonical binding');
  assert(sidecar.status === 'bound' && sidecar.prospect_id === 1101 && sidecar.proof_campaign_id === 1201, 'sidecar did not preserve exact canonical IDs');
  assert(manifest.source_lane === 'verified_cold' && manifest.campaign_id === cohort.campaign_id && manifest.job_id === sidecar.job_id && manifest.event_id === sidecar.callback_event_id, 'manifest did not receive exact callback correlation');
  assert(dna.run.prospect_id === 1101 && dna.run.proof_campaign_id === 1201 && dna.run.campaign_id === cohort.campaign_id && dna.run.source_lane === 'verified_cold', 'Build DNA run did not receive exact public proof identity');
  assert(dna.run.job_id === sidecar.job_id && dna.run.callback_event_id === sidecar.callback_event_id && dna.run.run_started_at === sidecar.run_started_at && dna.run.started_at === sidecar.run_started_at && dna.run.public_preview_delivery_id === 1301, 'Build DNA run did not receive exact job/callback correlation');
  assert(dna.artifacts.some(function (artifact) { return artifact.role === 'runtime-binding' && artifact.sha256 === manifest.runtime_binding.sha256; }), 'Build DNA does not carry the immutable runtime-binding hash');
  run(process.execPath, [validator, join(root, 'build-dna.json'), repositoryRoot]);
  const replayOutput = runFailure(process.execPath, [binder, '--input', valid]);
  assert(/unbound local preparation|Refusing to replace immutable runtime binding/i.test(replayOutput), 'runtime binding was not immutable after first write');
  console.log('PASS: Beauty / Hair / Braiding runtime binding contract');
} finally {
  rmSync(artifactRoot, { recursive: true, force: true });
}
