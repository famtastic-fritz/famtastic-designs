#!/usr/bin/env node

/**
 * Offline contract proof for the exact-prompt Gemini Flash Lite cohort bridge.
 * It only uses synthetic local fixtures and the local finalizer dry-run.
 */

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { artifactFromProviderResponse, normalizePromptPayload } from '../../gemini_flash_lite_image_worker.mjs';

const repositoryRoot = resolve(new URL('../../..', import.meta.url).pathname);
const builder = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs');
const binder = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/bind-beauty-proof-runtime.mjs');
const adapter = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/prepare-gemini-flash-lite-worker-input.mjs');
const finalizer = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/finalize-beauty-proof-cohort.mjs');
const worker = join(repositoryRoot, 'website-delivery-swarm/gemini_flash_lite_image_worker.mjs');
const sourceInput = join(repositoryRoot, 'website-delivery-swarm/cohorts/beauty-hair-braiding/input.example.json');
const artifactRoot = join(repositoryRoot, 'artifacts', 'gemini-flash-lite-cohort-bridge-tests-' + process.pid);
const privateOutput = join(tmpdir(), 'famtastic-gemini-flash-lite-cohort-bridge-' + process.pid + '-' + Date.now());

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

function assertFailure(callback, pattern, message) {
  try {
    callback();
  } catch (error) {
    assert(pattern.test(String(error.message)), message + ': ' + error.message);
    return;
  }
  throw new Error(message + ': expected failure');
}

function verifiedInput(path) {
  const input = JSON.parse(readFileSync(sourceInput, 'utf8'));
  input.source.source_lane = 'verified_cold';
  input.package_profile = 'anonymous_safe_medium_ultra_v1';
  input.leads = [input.leads[0]];
  writeJson(path, input);
}

function bindInput(cohortOutput, destination) {
  const cohort = JSON.parse(readFileSync(join(cohortOutput, 'cohort-manifest.json'), 'utf8'));
  const input = {
    schema: 'famtastic.beauty-proof-runtime-binding-input.v1',
    source_lane: 'verified_cold',
    package_profile: 'anonymous_safe_medium_ultra_v1',
    cohort_manifest: relative(repositoryRoot, join(cohortOutput, 'cohort-manifest.json')).split('\\').join('/'),
    bindings: cohort.bundles.map(function (bundle, index) {
      return {
        bundle: bundle.bundle,
        prospect_id: 1601 + index,
        proof_campaign_id: 1701 + index,
        public_preview_delivery_id: 1801 + index,
        campaign_id: cohort.campaign_id,
        job_id: 'public-preview:proof.generate:delivery:' + (1801 + index),
        callback_event_id: 'cold-proof:callback:' + cohort.campaign_id + ':' + (index + 1),
        run_started_at: '2026-08-27T02:00:00.000Z',
      };
    }),
  };
  const path = join(destination, 'runtime-binding-input.json');
  writeJson(path, input);
  return path;
}

function receiptFor(workerInput, bundleRoot) {
  return {
    schema: 'famtastic.gemini-flash-lite-image-receipt.v1',
    status: 'complete',
    request_id: workerInput.request_id,
    provider: 'google-gemini-api',
    api: 'generateContent',
    model: 'gemini-3.1-flash-lite-image',
    estimated_cost_usd: 0.1008,
    cost_status: 'estimated_pending_provider_reconciliation',
    artifacts: workerInput.image_prompts.map(function (prompt) {
      const image = readFileSync(join(bundleRoot, prompt.direction_id, 'thumbnail.png'));
      return {
        direction_id: prompt.direction_id,
        filename: prompt.filename,
        mime_type: 'image/png',
        prompt_sha256: prompt.prompt_sha256,
        sha256: sha256(image),
        bytes: image.length,
        duration_ms: 1000,
        usage_metadata: { prompt_token_count: 12, candidates_token_count: 8 },
      };
    }),
    completed_at: '2026-08-27T02:00:01.000Z',
  };
}

try {
  mkdirSync(artifactRoot, { recursive: true });
  const inputPath = join(artifactRoot, 'verified-input.json');
  const output = join(artifactRoot, 'cohort');
  verifiedInput(inputPath);
  run(process.execPath, [builder, '--input', inputPath, '--output', output, '--limit', '1']);
  const bindingPath = bindInput(output, artifactRoot);
  run(process.execPath, [binder, '--input', bindingPath]);
  const cohort = JSON.parse(readFileSync(join(output, 'cohort-manifest.json'), 'utf8'));
  const bundle = cohort.bundles[0];
  const bundleRoot = join(repositoryRoot, bundle.bundle);

  run(process.execPath, [adapter, '--cohort', join(output, 'cohort-manifest.json'), '--output', privateOutput, '--dry-run']);
  assert(!existsSync(privateOutput), 'adapter dry-run wrote a private worker input directory');
  run(process.execPath, [adapter, '--cohort', join(output, 'cohort-manifest.json'), '--output', privateOutput]);
  const handoff = JSON.parse(readFileSync(join(privateOutput, 'handoff-manifest.json'), 'utf8'));
  assert(handoff.schema === 'famtastic.beauty-proof-gemini-flash-lite-handoff.v1', 'adapter did not write the expected handoff manifest');
  assert(handoff.bundles.length === 1 && handoff.no_external_actions.includes('no Gemini provider call') && handoff.no_external_actions.includes('no macOS Keychain read'), 'adapter did not preserve its offline boundary');
  const handoffEntry = handoff.bundles[0];
  const workerInputPath = join(privateOutput, handoffEntry.output_file);
  const workerInput = JSON.parse(readFileSync(workerInputPath, 'utf8'));
  assert(workerInput.schema === 'famtastic.gemini-flash-lite-image-worker-input.v1', 'adapter wrote the wrong worker input schema');
  assert(workerInput.expected_directions.join(',') === 'a,b,c' && workerInput.image_prompts.length === 3, 'adapter did not emit exactly a/b/c prompts');
  for (const direction of ['a', 'b', 'c']) {
    const entry = workerInput.image_prompts.find(function (item) { return item.direction_id === direction; });
    const raw = readFileSync(join(bundleRoot, direction, 'gemini-flash-lite-image-prompt.txt'));
    assert(entry && entry.filename === direction + '-hero.png', 'adapter did not name the ' + direction + ' image deterministically');
    assert(Buffer.from(entry.prompt, 'utf8').equals(raw), 'adapter changed exact prompt bytes for direction ' + direction);
    assert(entry.prompt.endsWith('\n'), 'adapter trimmed the final newline from direction ' + direction);
    assert(entry.prompt_sha256 === sha256(raw), 'adapter prompt SHA does not match source prompt file hash for direction ' + direction);
  }

  run(process.execPath, [worker, '--validate-input', '--prompts', workerInputPath]);
  const normalized = normalizePromptPayload(workerInput);
  const providerResponse = {
    candidates: [{ content: { parts: [{ inlineData: { data: readFileSync(join(bundleRoot, 'a', 'thumbnail.png')).toString('base64'), mimeType: 'image/png' } }] } }],
    usageMetadata: { promptTokenCount: 12, candidatesTokenCount: 8 },
  };
  const artifact = artifactFromProviderResponse(providerResponse, normalized.prompts[0], 1000, 2000);
  assert(artifact.prompt_sha256 === workerInput.image_prompts[0].prompt_sha256, 'worker changed the prompt hash before recording provider evidence');
  assertFailure(function () {
    artifactFromProviderResponse({ candidates: providerResponse.candidates }, normalized.prompts[0], 1000, 2000);
  }, /usageMetadata/i, 'worker accepted a provider response without usage evidence');

  const receipt = receiptFor(workerInput, bundleRoot);
  const receiptPath = join(privateOutput, 'generation-receipt.json');
  writeJson(receiptPath, receipt);
  run(process.execPath, [worker, '--validate-receipt', '--prompts', workerInputPath, '--receipt', receiptPath]);

  const trimmedPrompt = structuredClone(workerInput);
  trimmedPrompt.image_prompts[0].prompt = trimmedPrompt.image_prompts[0].prompt.trimEnd();
  const trimmedPromptPath = join(privateOutput, 'trimmed-prompt.json');
  writeJson(trimmedPromptPath, trimmedPrompt);
  assert(/prompt_sha256/i.test(runFailure(process.execPath, [worker, '--validate-input', '--prompts', trimmedPromptPath])), 'worker accepted a prompt whose bytes changed after trim()');
  const missingDirection = structuredClone(workerInput);
  missingDirection.image_prompts[0].direction_id = '';
  const missingDirectionPath = join(privateOutput, 'missing-direction.json');
  writeJson(missingDirectionPath, missingDirection);
  assert(/direction_id/i.test(runFailure(process.execPath, [worker, '--validate-input', '--prompts', missingDirectionPath])), 'worker accepted a missing direction_id');
  const missingFilename = structuredClone(workerInput);
  missingFilename.image_prompts[0].filename = '';
  const missingFilenamePath = join(privateOutput, 'missing-filename.json');
  writeJson(missingFilenamePath, missingFilename);
  assert(/filename/i.test(runFailure(process.execPath, [worker, '--validate-input', '--prompts', missingFilenamePath])), 'worker accepted a missing filename');
  const duplicateFilename = structuredClone(workerInput);
  duplicateFilename.image_prompts[1].filename = duplicateFilename.image_prompts[0].filename;
  const duplicateFilenamePath = join(privateOutput, 'duplicate-filename.json');
  writeJson(duplicateFilenamePath, duplicateFilename);
  assert(/duplicate filename/i.test(runFailure(process.execPath, [worker, '--validate-input', '--prompts', duplicateFilenamePath])), 'worker accepted a duplicate filename');
  const missingUsage = structuredClone(receipt);
  missingUsage.artifacts[0].usage_metadata = {};
  const missingUsagePath = join(privateOutput, 'missing-usage-receipt.json');
  writeJson(missingUsagePath, missingUsage);
  assert(/usage_metadata/i.test(runFailure(process.execPath, [worker, '--validate-receipt', '--prompts', workerInputPath, '--receipt', missingUsagePath])), 'worker accepted a receipt without provider usage evidence');
  const incompleteReceipt = structuredClone(receipt);
  incompleteReceipt.artifacts.pop();
  const incompleteReceiptPath = join(privateOutput, 'incomplete-receipt.json');
  writeJson(incompleteReceiptPath, incompleteReceipt);
  assert(/incomplete/i.test(runFailure(process.execPath, [worker, '--validate-receipt', '--prompts', workerInputPath, '--receipt', incompleteReceiptPath])), 'worker accepted an incomplete result set');
  const duplicateReceiptDirection = structuredClone(receipt);
  duplicateReceiptDirection.artifacts[1].direction_id = duplicateReceiptDirection.artifacts[0].direction_id;
  const duplicateReceiptDirectionPath = join(privateOutput, 'duplicate-direction-receipt.json');
  writeJson(duplicateReceiptDirectionPath, duplicateReceiptDirection);
  assert(/duplicate direction_id/i.test(runFailure(process.execPath, [worker, '--validate-receipt', '--prompts', workerInputPath, '--receipt', duplicateReceiptDirectionPath])), 'worker accepted a duplicate receipt direction');

  const finalizerInput = {
    schema: 'famtastic.beauty-proof-cohort-finalizer-input.v1',
    source_lane: 'verified_cold',
    package_profile: 'anonymous_safe_medium_ultra_v1',
    cohort_manifest: relative(repositoryRoot, join(output, 'cohort-manifest.json')).split('\\').join('/'),
    bundles: [{
      bundle: bundle.bundle,
      directions: Object.fromEntries(workerInput.image_prompts.map(function (prompt) {
        return [prompt.direction_id, {
          image: join(bundleRoot, prompt.direction_id, 'thumbnail.png'),
          receipt: receiptPath,
          receipt_result_id: prompt.direction_id,
        }];
      })),
    }],
  };
  const finalizerInputPath = join(privateOutput, 'finalizer-input.json');
  writeJson(finalizerInputPath, finalizerInput);
  const beforeFinalizer = readFileSync(join(bundleRoot, 'a', 'index.html'));
  const finalizerOutput = run(process.execPath, [finalizer, '--input', finalizerInputPath, '--dry-run']);
  assert(/PASS: dry-run validated/i.test(finalizerOutput), 'finalizer rejected receipt hashes copied from adapter prompt bytes');
  assert(readFileSync(join(bundleRoot, 'a', 'index.html')).equals(beforeFinalizer), 'finalizer dry-run mutated the proof bundle');
  console.log('PASS: exact-prompt Gemini Flash Lite cohort bridge and finalizer file-hash contract');
} finally {
  rmSync(artifactRoot, { recursive: true, force: true });
  rmSync(privateOutput, { recursive: true, force: true });
}
