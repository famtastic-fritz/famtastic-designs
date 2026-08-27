#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const repositoryRoot = resolve(new URL('../../..', import.meta.url).pathname);
const builder = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs');
const finalizer = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/finalize-beauty-proof-cohort.mjs');
const serializer = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/serialize-signed-proof-assets.mjs');
const validator = join(repositoryRoot, 'website-delivery-swarm/scripts/validate-build-dna.mjs');
const sourceInput = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/input.example.json');
const artifactRoot = join(repositoryRoot, 'artifacts', 'beauty-proof-finalizer-tests-' + process.pid);

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

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

function receipt(imagePath, promptPath, id, invalidHash = false) {
  const image = readFileSync(imagePath);
  return {
    schema: 'famtastic.gemini-image-receipt.v1',
    provider: 'gemini-developer-api',
    api: 'generateContent',
    model: 'gemini-3.1-flash-lite-image',
    status: 'completed',
    started_at: '2026-08-27T00:00:00.000Z',
    completed_at: '2026-08-27T00:00:01.000Z',
    usage_metadata: { prompt_token_count: 12, candidates_token_count: 8 },
    cost: { expected_usd: 0.0336 },
    results: [{
      id,
      sha256: invalidHash ? '0'.repeat(64) : sha256(image),
      bytes: image.length,
      mime_type: 'image/png',
      duration_ms: 1000,
      prompt_sha256: sha256(readFileSync(promptPath)),
    }],
  };
}

function buildFinalizerInput(cohortOutput, imagePath, receiptDirectory, badHash = false) {
  const cohort = JSON.parse(readFileSync(join(cohortOutput, 'cohort-manifest.json'), 'utf8'));
  const input = {
    schema: 'famtastic.beauty-proof-cohort-finalizer-input.v1',
    source_lane: 'verified_cold',
    package_profile: 'anonymous_safe_medium_ultra_v1',
    cohort_manifest: join('artifacts', cohortOutput.split('/artifacts/')[1], 'cohort-manifest.json'),
    bundles: cohort.bundles.map(function (bundle) {
      const bundleRoot = join(repositoryRoot, bundle.bundle);
      const directions = {};
      for (const direction of ['a', 'b', 'c']) {
        const receiptPath = join(receiptDirectory, bundle.slug + '-' + direction + '.json');
        writeJson(receiptPath, receipt(imagePath, join(bundleRoot, direction, 'gemini-flash-lite-image-prompt.txt'), direction + '-result', badHash && direction === 'b'));
        directions[direction] = { image: imagePath, receipt: receiptPath, receipt_result_id: direction + '-result' };
      }
      return { bundle: bundle.bundle, directions };
    }),
  };
  const inputPath = join(receiptDirectory, badHash ? 'bad-finalizer-input.json' : 'finalizer-input.json');
  writeJson(inputPath, input);
  return inputPath;
}

function inspectFinalized(output) {
  const cohort = JSON.parse(readFileSync(join(output, 'cohort-manifest.json'), 'utf8'));
  const bundle = cohort.bundles[0];
  const root = join(repositoryRoot, bundle.bundle);
  const manifest = JSON.parse(readFileSync(join(root, 'manifest.json'), 'utf8'));
  const dna = JSON.parse(readFileSync(join(root, 'build-dna.json'), 'utf8'));
  const quality = JSON.parse(readFileSync(join(root, 'quality-report.json'), 'utf8'));
  assert(manifest.source_lane === 'verified_cold', 'manifest lost verified cold lane');
  assert(manifest.package_profile === 'anonymous_safe_medium_ultra_v1', 'manifest lost package profile');
  assert(manifest.proof_assets.schema === 'famtastic.signed-proof-assets.v1', 'signed asset manifest missing');
  assert(quality.static_status === 'passed' && quality.customer_delivery_status === 'blocked', 'quality gates changed incorrectly');
  assert(!dna.stages.some(function (stage) { return stage.stage_id === 'preview-art'; }), 'declared art stage should be replaced');
  assert(['a', 'b', 'c'].every(function (direction) { return dna.stages.some(function (stage) { return stage.stage_id === 'preview-art-' + direction && stage.result.status === 'passed'; }); }), 'receipt-backed art stages missing');
  assert(dna.completion.status === 'gated', 'finalizer must not close delivery gates');
  for (const direction of ['a', 'b', 'c']) {
    const html = readFileSync(join(root, direction, 'index.html'), 'utf8');
    const hero = readFileSync(join(root, direction, 'assets', 'hero.webp'));
    const design = JSON.parse(readFileSync(join(root, direction, 'design-dna.json'), 'utf8'));
    const assets = JSON.parse(readFileSync(join(root, direction, 'assets.json'), 'utf8'));
    assert(hero.subarray(0, 4).toString('ascii') === 'RIFF' && hero.subarray(8, 12).toString('ascii') === 'WEBP', 'hero is not WebP');
    assert(html.includes('src="assets/hero.webp"') && !html.includes('<svg class="art"'), 'hero injection failed');
    assert(design.visual_asset.status === 'provider_receipt_validated', 'direction DNA did not record receipt validation');
    assert(assets.length === 1 && assets[0].relative_path === 'hero.webp' && assets[0].sha256 === design.visual_asset.asset_manifest[0].sha256 && assets[0].sha256 === design.asset_manifest[0].sha256, 'per-direction stored asset manifest missing');
    assert(Buffer.byteLength(html, 'utf8') <= 500000, 'proof exceeds callback HTML limit');
    assert(!html.includes('@example.invalid'), 'contact email leaked into finalized proof');
  }
  run(process.execPath, [validator, join(root, 'build-dna.json'), repositoryRoot]);
  const serializedPath = join(root, 'signed-assets.callback.json');
  run(process.execPath, [serializer, '--bundle', root, '--output', serializedPath]);
  const serialized = JSON.parse(readFileSync(serializedPath, 'utf8'));
  assert(serialized.variants.length === 3 && serialized.variants.every(function (variant) { return variant.assets.length === 1 && variant.assets[0].asset_id === 'hero' && variant.assets[0].base64.length > 0; }), 'callback asset serializer omitted a hero asset');
}

try {
  const inputPath = join(artifactRoot, 'verified-input.json');
  const output = join(artifactRoot, 'cohort');
  const receipts = join(artifactRoot, 'receipts');
  const badOutput = join(artifactRoot, 'bad-cohort');
  const badReceipts = join(artifactRoot, 'bad-receipts');
  mkdirSync(receipts, { recursive: true });
  mkdirSync(badReceipts, { recursive: true });
  verifiedInput(inputPath);
  run(process.execPath, [builder, '--input', inputPath, '--output', output, '--limit', '1']);
  const cohort = JSON.parse(readFileSync(join(output, 'cohort-manifest.json'), 'utf8'));
  const bundleRoot = join(repositoryRoot, cohort.bundles[0].bundle);
  const hero = join(bundleRoot, 'a', 'thumbnail.png');
  const finalizerInput = buildFinalizerInput(output, hero, receipts);
  const preDryRunHtml = readFileSync(join(bundleRoot, 'a', 'index.html'), 'utf8');
  run(process.execPath, [finalizer, '--input', finalizerInput, '--dry-run']);
  assert(readFileSync(join(bundleRoot, 'a', 'index.html'), 'utf8') === preDryRunHtml, 'dry-run changed proof HTML');
  assert(!existsSync(join(bundleRoot, 'a', 'assets', 'hero.webp')), 'dry-run created a hero asset');
  run(process.execPath, [finalizer, '--input', finalizerInput]);
  inspectFinalized(output);

  run(process.execPath, [builder, '--input', inputPath, '--output', badOutput, '--limit', '1']);
  const badInput = buildFinalizerInput(badOutput, hero, badReceipts, true);
  const badOutputText = runFailure(process.execPath, [finalizer, '--input', badInput]);
  assert(/result hash does not match/i.test(badOutputText), 'bad provider receipt was not rejected for its image hash');
  const badCohort = JSON.parse(readFileSync(join(badOutput, 'cohort-manifest.json'), 'utf8'));
  assert(!existsSync(join(repositoryRoot, badCohort.bundles[0].bundle, 'a', 'assets', 'hero.webp')), 'invalid receipt changed a proof asset');

  const wrongLane = JSON.parse(readFileSync(finalizerInput, 'utf8'));
  wrongLane.source_lane = 'unverified';
  const wrongLanePath = join(receipts, 'wrong-lane-finalizer-input.json');
  writeJson(wrongLanePath, wrongLane);
  const wrongLaneOutput = runFailure(process.execPath, [finalizer, '--input', wrongLanePath, '--dry-run']);
  assert(/source_lane must be verified_cold/i.test(wrongLaneOutput), 'wrong source lane was not rejected');
  console.log('PASS: Receipt-backed Beauty / Hair / Braiding local finalizer contracts');
} finally {
  rmSync(artifactRoot, { recursive: true, force: true });
}
