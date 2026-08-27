#!/usr/bin/env node
/**
 * Local-only canonical runtime binding for prepared Beauty / Hair / Braiding
 * proof bundles.
 *
 * The builder intentionally creates non-importable local placeholders. This
 * command is the narrow seam where the canonical ingress supplies the real
 * Drupal prospect/proof campaign/public campaign/job/callback correlation.
 * It does not create IDs, call a provider, write Drupal, promote, publish, or
 * send mail. Once a bundle has a binding, this command refuses to replace it.
 */

import { createHash } from 'node:crypto';
import { existsSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, '../../..');
const INPUT_SCHEMA = 'famtastic.beauty-proof-runtime-binding-input.v1';
const BINDING_SCHEMA = 'famtastic.beauty-proof-runtime-binding.v1';
const COHORT_SCHEMA = 'famtastic.beauty-proof-cohort-output.v1';
const REQUIRED_SOURCE_LANE = 'verified_cold';
const REQUIRED_PACKAGE_PROFILE = 'anonymous_safe_medium_ultra_v1';
const REQUIRED_DIRECTIONS = ['a', 'b', 'c'];

function fail(message) {
  throw new Error(message);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function fileHash(path) {
  return sha256(readFileSync(path));
}

function json(value) {
  return JSON.stringify(value, null, 2) + '\n';
}

function writeJson(path, value) {
  writeFileSync(path, json(value));
}

function isObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function requireObject(value, label) {
  if (!isObject(value)) fail(label + ' must be an object.');
  return value;
}

function cleanText(value, label, maximum = 1000, required = true) {
  if (value === undefined || value === null || value === '') {
    if (required) fail(label + ' is required.');
    return '';
  }
  const text = String(value).trim();
  if (!text && required) fail(label + ' is required.');
  if (text.length > maximum) fail(label + ' exceeds ' + maximum + ' characters.');
  return text;
}

function readJson(path, label) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  }
  catch (error) {
    fail((label || path) + ' is not valid JSON: ' + error.message);
  }
}

function requireExistingFile(path, label) {
  if (!existsSync(path) || !statSync(path).isFile()) fail(label + ' does not exist or is not a file: ' + path);
}

function repoRelative(path) {
  const absolute = resolve(path);
  if (!(absolute === repositoryRoot || absolute.startsWith(repositoryRoot + '/'))) {
    fail('Cohort artifacts must stay inside this repository: ' + absolute);
  }
  return relative(repositoryRoot, absolute).split('\\').join('/');
}

function resolveInsideRepo(path, label) {
  const absolute = resolve(repositoryRoot, cleanText(path, label, 3000));
  repoRelative(absolute);
  return absolute;
}

function positiveId(value, label) {
  if (!Number.isSafeInteger(value) || value < 1) fail(label + ' must be a positive integer.');
  return value;
}

function safeReference(value, label, maximum = 255) {
  const text = cleanText(value, label, maximum);
  if (!/^[A-Za-z0-9][A-Za-z0-9._:-]{1,254}$/.test(text)) {
    fail(label + ' contains unsafe characters.');
  }
  return text;
}

function canonicalJobId(value) {
  const text = safeReference(value, 'job_id');
  if (/^(?:local-|beauty-proof:)/i.test(text)) {
    fail('job_id must be the canonical ingress job ID, not a local builder placeholder.');
  }
  return text;
}

function canonicalCallbackEventId(value) {
  const text = safeReference(value, 'callback_event_id');
  if (/^(?:local-|beauty-proof:)/i.test(text)) {
    fail('callback_event_id must be the canonical callback event ID, not a local builder placeholder.');
  }
  return text;
}

function canonicalCampaignId(value) {
  const text = safeReference(value, 'campaign_id', 128);
  if (/^(?:local-|beauty-proof:)/i.test(text)) {
    fail('campaign_id must be the canonical public campaign ID, not a local builder placeholder.');
  }
  return text;
}

function iso(value, label) {
  const text = cleanText(value, label, 80);
  if (Number.isNaN(Date.parse(text))) fail(label + ' must be an ISO date-time.');
  return text;
}

function validHash(value, label) {
  const text = cleanText(value, label, 64).toLowerCase();
  if (!/^[a-f0-9]{64}$/.test(text)) fail(label + ' must be a SHA-256 hex digest.');
  return text;
}

function exactDirections(value, label) {
  if (!Array.isArray(value) || value.length !== REQUIRED_DIRECTIONS.length || value.some(function (item, index) { return item !== REQUIRED_DIRECTIONS[index]; })) {
    fail(label + ' must be exactly a, b, c.');
  }
}

function options(argv) {
  const result = { input: '', dryRun: false };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--input') result.input = argv[++index] || '';
    else if (argv[index] === '--dry-run') result.dryRun = true;
    else if (argv[index] === '--help' || argv[index] === '-h') {
      console.log('Usage: node website-delivery-swarm/cohorts/beauty-hair-braiding/bind-beauty-proof-runtime.mjs --input /secure/runtime-binding-input.json [--dry-run]');
      process.exit(0);
    }
    else fail('Unknown argument: ' + argv[index]);
  }
  if (!result.input) fail('--input is required.');
  return result;
}

function loadInput(path) {
  requireExistingFile(path, 'Runtime binding input');
  const raw = readFileSync(path);
  const value = readJson(path, 'Runtime binding input');
  requireObject(value, 'Runtime binding input');
  if (value.schema !== INPUT_SCHEMA) fail('Runtime binding input schema must be ' + INPUT_SCHEMA + '.');
  if (cleanText(value.source_lane, 'source_lane', 80) !== REQUIRED_SOURCE_LANE) fail('source_lane must be ' + REQUIRED_SOURCE_LANE + '.');
  if (cleanText(value.package_profile, 'package_profile', 120) !== REQUIRED_PACKAGE_PROFILE) fail('package_profile must be ' + REQUIRED_PACKAGE_PROFILE + '.');
  const cohortPath = resolveInsideRepo(value.cohort_manifest, 'cohort_manifest');
  requireExistingFile(cohortPath, 'cohort_manifest');
  const cohort = readJson(cohortPath, 'cohort_manifest');
  if (cohort.schema !== COHORT_SCHEMA) fail('cohort_manifest has an unsupported schema.');
  if (!isObject(cohort.source) || cohort.source.source_lane !== REQUIRED_SOURCE_LANE) fail('cohort_manifest source.source_lane must be ' + REQUIRED_SOURCE_LANE + '.');
  if (cohort.package_profile !== REQUIRED_PACKAGE_PROFILE) fail('cohort_manifest package_profile must be ' + REQUIRED_PACKAGE_PROFILE + '.');
  if (cohort.runtime_binding_status !== 'unbound-local-preparation') fail('cohort_manifest must be an unbound local preparation cohort.');
  if (!Array.isArray(cohort.bundles) || cohort.bundles.length === 0 || cohort.selected_count !== cohort.bundles.length) {
    fail('cohort_manifest must contain every selected bundle exactly once.');
  }
  if (!Array.isArray(value.bindings) || value.bindings.length !== cohort.bundles.length) {
    fail('Runtime binding input must bind every selected cohort bundle exactly once.');
  }
  return { value, inputHash: sha256(raw), cohortPath, cohort };
}

function normalizeBinding(value, cohortCampaignId, expectedBundle) {
  requireObject(value, 'bindings[]');
  const bundle = resolveInsideRepo(value.bundle, 'bindings[].bundle');
  if (bundle !== expectedBundle) fail('Runtime binding bundle does not match its cohort bundle path.');
  const campaignId = canonicalCampaignId(value.campaign_id);
  if (campaignId !== cohortCampaignId) fail('Runtime binding campaign_id must exactly match cohort_manifest campaign_id.');
  const binding = {
    bundle,
    bundle_relative: repoRelative(bundle),
    prospect_id: positiveId(value.prospect_id, 'prospect_id'),
    proof_campaign_id: positiveId(value.proof_campaign_id, 'proof_campaign_id'),
    campaign_id: campaignId,
    source_lane: REQUIRED_SOURCE_LANE,
    job_id: canonicalJobId(value.job_id),
    callback_event_id: canonicalCallbackEventId(value.callback_event_id),
    run_started_at: iso(value.run_started_at, 'run_started_at'),
  };
  if (value.public_preview_delivery_id !== undefined && value.public_preview_delivery_id !== null && value.public_preview_delivery_id !== '') {
    binding.public_preview_delivery_id = positiveId(value.public_preview_delivery_id, 'public_preview_delivery_id');
  }
  return binding;
}

function assertUnboundBundle(bundle, cohortCampaignId) {
  const manifestPath = join(bundle, 'manifest.json');
  const dnaPath = join(bundle, 'build-dna.json');
  requireExistingFile(manifestPath, 'Prepared bundle manifest');
  requireExistingFile(dnaPath, 'Prepared Build DNA');
  const bindingPath = join(bundle, 'runtime-binding.json');
  if (existsSync(bindingPath)) fail('Refusing to replace immutable runtime binding: ' + repoRelative(bindingPath));
  const manifest = readJson(manifestPath, 'Prepared bundle manifest');
  const dna = readJson(dnaPath, 'Prepared Build DNA');
  if (manifest.campaign_id !== cohortCampaignId) fail('Prepared bundle campaign ID does not match cohort manifest.');
  if (!isObject(manifest.runtime_binding) || manifest.runtime_binding.schema !== BINDING_SCHEMA || manifest.runtime_binding.status !== 'unbound-local-preparation' || manifest.runtime_binding.importable !== false) {
    fail('Prepared bundle manifest must explicitly be an unbound, non-importable local preparation.');
  }
  if (manifest.source_lane !== 'local-preparation-unbound') fail('Prepared bundle manifest must retain its unbound local source lane before binding.');
  exactDirections(manifest.input_snapshot && manifest.input_snapshot.direction_ids, 'Prepared bundle direction IDs');
  if ((manifest.input_snapshot || {}).package_profile !== REQUIRED_PACKAGE_PROFILE) fail('Prepared bundle package profile must be ' + REQUIRED_PACKAGE_PROFILE + '.');
  if (!isObject(dna.runtime_binding) || dna.runtime_binding.schema !== BINDING_SCHEMA || dna.runtime_binding.status !== 'unbound-local-preparation' || dna.runtime_binding.importable !== false) {
    fail('Prepared Build DNA must explicitly be an unbound, non-importable local preparation.');
  }
  if (isObject(dna.run) || existsSync(join(bundle, 'a', 'assets')) || existsSync(join(bundle, 'b', 'assets')) || existsSync(join(bundle, 'c', 'assets'))) {
    fail('Runtime binding only accepts an unfinalized local proof bundle.');
  }
  if (!Array.isArray(dna.artifacts) || dna.artifacts.length === 0) fail('Prepared Build DNA has no artifact ledger.');
  return { manifestPath, dnaPath, bindingPath, manifest, dna };
}

function bindingDocument(binding, baseDna, inputHash, boundAt) {
  const document = {
    schema: BINDING_SCHEMA,
    status: 'bound',
    binding_input_sha256: inputHash,
    bound_at: boundAt,
    bundle: binding.bundle_relative,
    build_id: cleanText(baseDna.build_id, 'Build DNA build_id', 170),
    prospect_id: binding.prospect_id,
    proof_campaign_id: binding.proof_campaign_id,
    campaign_id: binding.campaign_id,
    source_lane: binding.source_lane,
    job_id: binding.job_id,
    callback_event_id: binding.callback_event_id,
    run_started_at: binding.run_started_at,
    callback: {
      event_id: binding.callback_event_id,
      job_id: binding.job_id,
      correlation: 'exact-canonical-ingress-binding',
    },
    no_external_actions: ['no ID creation', 'no provider call', 'no Drupal write', 'no proof promotion', 'no publication', 'no email send'],
  };
  if (binding.public_preview_delivery_id !== undefined) document.public_preview_delivery_id = binding.public_preview_delivery_id;
  return document;
}

function refreshedArtifacts(baseDna, bindingPath, bindingHash) {
  const bundleRoot = dirname(bindingPath);
  const seenPaths = new Set();
  const artifacts = baseDna.artifacts.map(function (artifact) {
    if (!isObject(artifact)) fail('Prepared Build DNA artifact ledger contains an invalid entry.');
    const path = resolveInsideRepo(artifact.path, 'Build DNA artifact path');
    if (!(path === bundleRoot || path.startsWith(bundleRoot + '/'))) {
      fail('Prepared Build DNA artifact must stay inside its own bundle: ' + artifact.path);
    }
    const relativePath = repoRelative(path);
    if (seenPaths.has(relativePath)) fail('Prepared Build DNA artifact ledger contains duplicate path ' + relativePath + '.');
    seenPaths.add(relativePath);
    requireExistingFile(path, 'Build DNA artifact');
    return { ...artifact, path: relativePath, sha256: fileHash(path) };
  });
  const bindingRelative = repoRelative(bindingPath);
  if (seenPaths.has(bindingRelative)) fail('Prepared Build DNA already names a runtime-binding artifact.');
  artifacts.push({
    role: 'runtime-binding',
    path: bindingRelative,
    sha256: bindingHash,
    retention: 'restricted-local',
    rights_status: 'canonical-runtime-correlation',
  });
  return artifacts;
}

function boundManifest(manifest, document, bindingHash) {
  return {
    ...manifest,
    campaign_id: document.campaign_id,
    job_id: document.job_id,
    event_id: document.callback_event_id,
    source_lane: REQUIRED_SOURCE_LANE,
    runtime_binding: {
      schema: BINDING_SCHEMA,
      status: 'bound',
      artifact: 'runtime-binding.json',
      sha256: bindingHash,
      binding_input_sha256: document.binding_input_sha256,
      prospect_id: document.prospect_id,
      proof_campaign_id: document.proof_campaign_id,
      campaign_id: document.campaign_id,
      source_lane: document.source_lane,
      job_id: document.job_id,
      callback_event_id: document.callback_event_id,
      ...(document.public_preview_delivery_id === undefined ? {} : { public_preview_delivery_id: document.public_preview_delivery_id }),
    },
  };
}

function boundDna(baseDna, document, bindingHash, artifacts) {
  const root = document.bundle;
  const run = {
    schema: 'famtastic.build-dna-run.v1',
    status: 'runtime-bound-pending-local-finalization',
    binding_schema: BINDING_SCHEMA,
    binding_artifact: document.bundle + '/runtime-binding.json',
    binding_sha256: bindingHash,
    prospect_id: document.prospect_id,
    proof_campaign_id: document.proof_campaign_id,
    campaign_id: document.campaign_id,
    source_lane: document.source_lane,
    job_id: document.job_id,
    callback_event_id: document.callback_event_id,
    callback: document.callback,
    started_at: document.run_started_at,
    bound_at: document.bound_at,
  };
  if (document.public_preview_delivery_id !== undefined) run.public_preview_delivery_id = document.public_preview_delivery_id;
  return {
    ...baseDna,
    classification: 'runtime-bound-local-preparation',
    runtime_binding: {
      schema: BINDING_SCHEMA,
      status: 'bound',
      importable: false,
      artifact: 'runtime-binding.json',
      sha256: bindingHash,
      reason: 'Runtime identities are exact, but provider art, browser QA, independent review, Drupal projection, owner approval, and delivery remain gated.',
    },
    run,
    recipe: {
      ...baseDna.recipe,
      source_lane: REQUIRED_SOURCE_LANE,
      runtime_binding_contract: BINDING_SCHEMA,
    },
    correlation: {
      ...baseDna.correlation,
      prospect_id: document.prospect_id,
      proof_campaign_id: document.proof_campaign_id,
      campaign_id: document.campaign_id,
      source_lane: REQUIRED_SOURCE_LANE,
      job_id: document.job_id,
      callback_event_id: document.callback_event_id,
      runtime_binding_sha256: bindingHash,
      ...(document.public_preview_delivery_id === undefined ? {} : { public_preview_delivery_id: document.public_preview_delivery_id }),
    },
    artifacts,
    retrieval: {
      ...baseDna.retrieval,
      filesystem: {
        ...(isObject(baseDna.retrieval && baseDna.retrieval.filesystem) ? baseDna.retrieval.filesystem : {}),
        status: 'runtime-bound-local-preparation',
        root,
        build_dna: document.bundle + '/build-dna.json',
        runtime_binding: document.bundle + '/runtime-binding.json',
      },
      database: {
        status: 'not_registered',
        required_operation: 'Finalization, browser QA, independent review, canonical signed-asset import, and then immutable Drupal Build DNA registration remain required.',
      },
    },
    integrity: {
      ...(isObject(baseDna.integrity) ? baseDna.integrity : {}),
      artifact_hash_algorithm: 'sha256',
      build_dna_status: 'runtime-bound-local-preparation-with-real-artifact-hashes',
      runtime_binding_sha256: bindingHash,
    },
    completion: {
      ...(isObject(baseDna.completion) ? baseDna.completion : {}),
      status: 'gated',
      open_gates: [
        'No paid image generation was executed',
        'No browser screenshots or independent visual approval were executed',
        'No Drupal projection, owner approval, publication, or customer email occurred',
      ],
    },
  };
}

function validateDna(bundle) {
  const result = spawnSync(process.execPath, [
    join(repositoryRoot, 'website-delivery-swarm/scripts/validate-build-dna.mjs'),
    join(bundle, 'build-dna.json'),
    repositoryRoot,
  ], { cwd: repositoryRoot, encoding: 'utf8' });
  if (result.status !== 0) fail('Build DNA validator failed: ' + (result.stderr || result.stdout).trim());
  return result.stdout.trim().split('\n');
}

function planBinding(input, bindingValue, cohortBundle, boundAt) {
  const expectedBundle = resolveInsideRepo(cohortBundle.bundle, 'cohort bundle path');
  const binding = normalizeBinding(bindingValue, input.cohort.campaign_id, expectedBundle);
  const prepared = assertUnboundBundle(binding.bundle, input.cohort.campaign_id);
  const document = bindingDocument(binding, prepared.dna, input.inputHash, boundAt);
  const bindingText = json(document);
  const bindingHash = sha256(Buffer.from(bindingText, 'utf8'));
  const manifest = boundManifest(prepared.manifest, document, bindingHash);
  return {
    binding,
    prepared,
    document,
    bindingText,
    bindingHash,
    manifest,
  };
}

function applyBinding(plan) {
  writeFileSync(plan.prepared.bindingPath, plan.bindingText);
  writeJson(plan.prepared.manifestPath, plan.manifest);
  const artifacts = refreshedArtifacts(plan.prepared.dna, plan.prepared.bindingPath, plan.bindingHash);
  const dna = boundDna(plan.prepared.dna, plan.document, plan.bindingHash, artifacts);
  writeJson(plan.prepared.dnaPath, dna);
  return validateDna(plan.binding.bundle);
}

function boundCohortManifest(cohort, inputHash, boundAt) {
  return {
    ...cohort,
    runtime_binding_status: 'bound-canonical-runtime',
    runtime_binding_contract: BINDING_SCHEMA,
    runtime_binding_input_sha256: inputHash,
    runtime_bound_at: boundAt,
  };
}

function main() {
  const args = options(process.argv.slice(2));
  const inputPath = resolve(args.input);
  const input = loadInput(inputPath);
  const byBundle = new Map();
  input.value.bindings.forEach(function (binding) {
    const path = resolveInsideRepo(requireObject(binding, 'bindings[]').bundle, 'bindings[].bundle');
    if (byBundle.has(path)) fail('Runtime binding input contains a duplicate bundle mapping.');
    byBundle.set(path, binding);
  });
  const boundAt = new Date().toISOString();
  const plans = input.cohort.bundles.map(function (cohortBundle) {
    const path = resolveInsideRepo(cohortBundle.bundle, 'cohort bundle path');
    const supplied = byBundle.get(path);
    if (!supplied) fail('Runtime binding input is missing a cohort bundle mapping: ' + cohortBundle.bundle);
    return planBinding(input, supplied, cohortBundle, boundAt);
  });
  if (args.dryRun) {
    console.log('PASS: dry-run validated ' + plans.length + ' exact canonical runtime binding(s).');
    plans.forEach(function (plan) {
      console.log('PLAN: ' + plan.binding.bundle_relative + ' → prospect ' + plan.document.prospect_id + ', proof campaign ' + plan.document.proof_campaign_id + ', public campaign ' + plan.document.campaign_id + ', job ' + plan.document.job_id + ', callback ' + plan.document.callback_event_id);
    });
    console.log('DRY RUN: no cohort file, Drupal record, provider, proof, promotion, publication, or email was changed.');
    return;
  }
  plans.forEach(function (plan) {
    const validation = applyBinding(plan);
    console.log('PASS: bound ' + plan.binding.bundle_relative + ' to its exact canonical runtime identities.');
    validation.forEach(function (line) { console.log(line); });
  });
  writeJson(input.cohortPath, boundCohortManifest(input.cohort, input.inputHash, boundAt));
  console.log('STATUS: local runtime binding only; provider art, Drupal registration/import, owner approval, publication, and email remain blocked.');
}

try {
  main();
}
catch (error) {
  console.error('FAIL: ' + error.message);
  process.exit(1);
}
