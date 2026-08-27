#!/usr/bin/env node
/**
 * Local-only adapter from a canonically bound Beauty / Hair / Braiding proof
 * cohort to one Gemini Flash Lite worker input per lead.
 *
 * It copies each a/b/c prompt as exact UTF-8 bytes into a JSON string and
 * carries the source file SHA-256. It does not call Gemini, Keychain, Drupal,
 * Site Studio, the proof importer, production, mail, or a scheduler.
 */

import { createHash } from 'node:crypto';
import { existsSync, lstatSync, mkdirSync, mkdtempSync, readFileSync, renameSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { basename, dirname, isAbsolute, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, '../../..');
const WORKER_INPUT_SCHEMA = 'famtastic.gemini-flash-lite-image-worker-input.v1';
const HANDOFF_SCHEMA = 'famtastic.beauty-proof-gemini-flash-lite-handoff.v1';
const RUNTIME_BINDING_SCHEMA = 'famtastic.beauty-proof-runtime-binding.v1';
const REQUIRED_SOURCE_LANE = 'verified_cold';
const REQUIRED_PACKAGE_PROFILE = 'anonymous_safe_medium_ultra_v1';
const REQUIRED_DIRECTIONS = ['a', 'b', 'c'];
const SAFE_FILE_PART = /^[a-z0-9][a-z0-9._-]{0,119}$/;

function fail(message) {
  throw new Error(message);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function json(value) {
  return JSON.stringify(value, null, 2) + '\n';
}

function writeJson(path, value) {
  writeFileSync(path, json(value), { flag: 'wx' });
}

function isObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function cleanText(value, label, maximum = 1000) {
  if (value === undefined || value === null) fail(label + ' is required.');
  const text = String(value).trim();
  if (!text) fail(label + ' is required.');
  if (text.length > maximum) fail(label + ' exceeds ' + maximum + ' characters.');
  return text;
}

function positiveId(value, label) {
  if (!Number.isSafeInteger(value) || value < 1) fail(label + ' must be a positive integer.');
  return value;
}

function safeReference(value, label, maximum = 255) {
  const text = cleanText(value, label, maximum);
  if (!/^[A-Za-z0-9][A-Za-z0-9._:-]{1,254}$/.test(text)) fail(label + ' contains unsafe characters.');
  return text;
}

function exactDirectionIds(value, label) {
  if (!Array.isArray(value) || value.length !== REQUIRED_DIRECTIONS.length || value.some(function (item, index) { return item !== REQUIRED_DIRECTIONS[index]; })) {
    fail(label + ' must be exactly a, b, c.');
  }
  return value;
}

function requireExistingFile(path, label) {
  if (!existsSync(path) || !statSync(path).isFile()) fail(label + ' does not exist or is not a file: ' + path);
}

function readJson(path, label) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    fail((label || path) + ' is not valid JSON: ' + error.message);
  }
}

function repoRelative(path) {
  const absolute = resolve(path);
  if (!(absolute === repositoryRoot || absolute.startsWith(repositoryRoot + '/'))) {
    fail('Cohort artifacts must stay inside this repository: ' + absolute);
  }
  return relative(repositoryRoot, absolute).split('\\').join('/');
}

function resolveInsideRepo(value, label) {
  const absolute = resolve(repositoryRoot, cleanText(value, label, 4000));
  repoRelative(absolute);
  return absolute;
}

function sidecarHash(path) {
  return sha256(readFileSync(path));
}

function exactValue(value, expected, label) {
  if (value !== expected) fail(label + ' does not match the immutable runtime binding.');
}

function parseOptions(argv) {
  const options = { cohort: '', output: '', dryRun: false };
  for (let index = 2; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--cohort') options.cohort = argv[++index] || '';
    else if (argument === '--output') options.output = argv[++index] || '';
    else if (argument === '--dry-run') options.dryRun = true;
    else if (argument === '--help' || argument === '-h') {
      console.log('Usage: node website-delivery-swarm/cohorts/beauty-hair-braiding/prepare-gemini-flash-lite-worker-input.mjs --cohort /absolute/path/to/cohort-manifest.json --output /absolute/private/new-output-directory [--dry-run]');
      process.exit(0);
    } else fail('Unknown argument: ' + argument);
  }
  if (!options.cohort) fail('--cohort is required.');
  if (!options.output) fail('--output is required.');
  return options;
}

function loadCohort(path) {
  requireExistingFile(path, 'Cohort manifest');
  const cohort = readJson(path, 'Cohort manifest');
  if (!isObject(cohort) || cohort.schema !== 'famtastic.beauty-proof-cohort-output.v1') fail('Cohort manifest has an unsupported schema.');
  if (!isObject(cohort.source) || cohort.source.source_lane !== REQUIRED_SOURCE_LANE) fail('Cohort manifest must be source_lane=' + REQUIRED_SOURCE_LANE + '.');
  if (cohort.package_profile !== REQUIRED_PACKAGE_PROFILE) fail('Cohort manifest must use package_profile=' + REQUIRED_PACKAGE_PROFILE + '.');
  if (cohort.runtime_binding_status !== 'bound-canonical-runtime' || cohort.runtime_binding_contract !== RUNTIME_BINDING_SCHEMA) {
    fail('Cohort manifest must record a completed canonical runtime binding before worker-input preparation.');
  }
  if (!Array.isArray(cohort.bundles) || cohort.bundles.length === 0 || cohort.selected_count !== cohort.bundles.length) {
    fail('Cohort manifest must contain every selected bundle exactly once.');
  }
  const campaignId = safeReference(cohort.campaign_id, 'cohort campaign_id', 128);
  return { cohort, campaignId, cohortHash: sidecarHash(path), cohortPath: path };
}

function readExactPrompt(path, direction) {
  requireExistingFile(path, 'Prompt for direction ' + direction);
  const bytes = readFileSync(path);
  const prompt = bytes.toString('utf8');
  if (!Buffer.from(prompt, 'utf8').equals(bytes)) fail('Prompt for direction ' + direction + ' is not exact UTF-8 and cannot safely cross a JSON worker boundary.');
  if (!prompt.trim()) fail('Prompt for direction ' + direction + ' is empty after trim().');
  return { prompt, sha256: sha256(bytes), bytes: bytes.length };
}

function prepareBundle(bundle, cohortCampaignId) {
  if (!isObject(bundle)) fail('Cohort bundle entry must be an object.');
  const root = resolveInsideRepo(bundle.bundle, 'cohort bundle path');
  requireExistingFile(join(root, 'manifest.json'), 'Prepared bundle manifest');
  requireExistingFile(join(root, 'runtime-binding.json'), 'Prepared runtime binding');
  const manifest = readJson(join(root, 'manifest.json'), 'Prepared bundle manifest');
  const bindingPath = join(root, 'runtime-binding.json');
  const binding = readJson(bindingPath, 'Prepared runtime binding');
  if (!isObject(manifest) || manifest.source_lane !== REQUIRED_SOURCE_LANE || (manifest.input_snapshot || {}).package_profile !== REQUIRED_PACKAGE_PROFILE) fail('Prepared bundle must retain verified_cold source lane and anonymous three-proof profile.');
  if (!isObject(manifest.runtime_binding) || manifest.runtime_binding.status !== 'bound') fail('Prepared bundle manifest must record its immutable runtime binding.');
  if (!isObject(binding) || binding.schema !== RUNTIME_BINDING_SCHEMA || binding.status !== 'bound') fail('Prepared bundle requires a bound runtime-binding.json sidecar.');
  const bindingHash = sidecarHash(bindingPath);
  if (manifest.runtime_binding.sha256 !== bindingHash) fail('Prepared bundle manifest runtime-binding hash does not match its immutable sidecar.');
  const prospectId = positiveId(binding.prospect_id, 'runtime binding prospect_id');
  const proofCampaignId = positiveId(binding.proof_campaign_id, 'runtime binding proof_campaign_id');
  const publicPreviewDeliveryId = positiveId(binding.public_preview_delivery_id, 'runtime binding public_preview_delivery_id');
  const campaignId = safeReference(binding.campaign_id, 'runtime binding campaign_id', 128);
  exactValue(campaignId, cohortCampaignId, 'Runtime binding campaign_id');
  exactValue(manifest.campaign_id, campaignId, 'Prepared bundle campaign_id');
  const jobId = safeReference(binding.job_id, 'runtime binding job_id');
  const callbackEventId = safeReference(binding.callback_event_id, 'runtime binding callback_event_id');
  if (/^(?:local-|beauty-proof:)/i.test(jobId) || /^(?:local-|beauty-proof:)/i.test(callbackEventId)) {
    fail('Runtime binding still contains a local job or callback placeholder.');
  }
  exactDirectionIds(manifest.input_snapshot && manifest.input_snapshot.direction_ids, 'Prepared bundle direction IDs');
  const slug = cleanText(bundle.slug || basename(root), 'cohort bundle slug', 120).toLowerCase();
  if (!SAFE_FILE_PART.test(slug)) fail('Cohort bundle slug is unsafe for a worker input filename.');
  const imagePrompts = REQUIRED_DIRECTIONS.map(function (direction) {
    const sourcePrompt = readExactPrompt(join(root, direction, 'gemini-flash-lite-image-prompt.txt'), direction);
    return {
      direction_id: direction,
      filename: direction + '-hero.png',
      prompt: sourcePrompt.prompt,
      prompt_sha256: sourcePrompt.sha256,
    };
  });
  return {
    outputFilename: slug + '.image-prompts.json',
    workerInput: {
      schema: WORKER_INPUT_SCHEMA,
      request_id: jobId,
      source: {
        source_lane: REQUIRED_SOURCE_LANE,
        package_profile: REQUIRED_PACKAGE_PROFILE,
        campaign_id: campaignId,
        prospect_id: prospectId,
        proof_campaign_id: proofCampaignId,
        public_preview_delivery_id: publicPreviewDeliveryId,
        callback_event_id: callbackEventId,
        runtime_binding_sha256: bindingHash,
        cohort_bundle: repoRelative(root),
      },
      expected_directions: REQUIRED_DIRECTIONS,
      image_prompts: imagePrompts,
    },
  };
}

function requireNewPrivateOutput(value) {
  if (!isAbsolute(value)) fail('--output must be an absolute operator-only directory outside this repository.');
  const output = resolve(value);
  const insideRepository = output === repositoryRoot || output.startsWith(repositoryRoot + '/');
  if (insideRepository) fail('--output must remain outside the repository because it contains exact prompt material.');
  try {
    lstatSync(output);
    fail('Output directory must not already exist: ' + output);
  } catch (error) {
    if (!String(error && error.code || '').includes('ENOENT')) throw error;
  }
  return output;
}

function handoffManifest(loaded, prepared) {
  return {
    schema: HANDOFF_SCHEMA,
    status: 'offline-worker-input-prepared',
    source_lane: REQUIRED_SOURCE_LANE,
    package_profile: REQUIRED_PACKAGE_PROFILE,
    campaign_id: loaded.campaignId,
    cohort_manifest: repoRelative(loaded.cohortPath),
    cohort_manifest_sha256: loaded.cohortHash,
    no_external_actions: ['no Gemini provider call', 'no macOS Keychain read', 'no Drupal write', 'no Site Studio dispatch', 'no proof import', 'no production deployment', 'no mail or scheduler action'],
    bundles: prepared.map(function (item) {
      const text = json(item.workerInput);
      return {
        output_file: item.outputFilename,
        output_sha256: sha256(Buffer.from(text, 'utf8')),
        request_id: item.workerInput.request_id,
        prospect_id: item.workerInput.source.prospect_id,
        proof_campaign_id: item.workerInput.source.proof_campaign_id,
        public_preview_delivery_id: item.workerInput.source.public_preview_delivery_id,
        runtime_binding_sha256: item.workerInput.source.runtime_binding_sha256,
        directions: item.workerInput.image_prompts.map(function (prompt) {
          return { direction_id: prompt.direction_id, filename: prompt.filename, prompt_sha256: prompt.prompt_sha256 };
        }),
      };
    }),
  };
}

function writeOutput(output, prepared, manifest) {
  mkdirSync(dirname(output), { recursive: true });
  const staging = mkdtempSync(join(dirname(output), '.' + basename(output) + '.staging-'));
  try {
    prepared.forEach(function (item) { writeJson(join(staging, item.outputFilename), item.workerInput); });
    writeJson(join(staging, 'handoff-manifest.json'), manifest);
    renameSync(staging, output);
  } catch (error) {
    rmSync(staging, { recursive: true, force: true });
    throw error;
  }
}

function main() {
  const options = parseOptions(process.argv);
  const cohortPath = resolveInsideRepo(options.cohort, '--cohort');
  const output = requireNewPrivateOutput(options.output);
  const loaded = loadCohort(cohortPath);
  const prepared = loaded.cohort.bundles.map(function (bundle) { return prepareBundle(bundle, loaded.campaignId); });
  if (new Set(prepared.map(function (item) { return item.outputFilename; })).size !== prepared.length) fail('Cohort maps two leads to the same worker-input filename.');
  const manifest = handoffManifest(loaded, prepared);
  if (options.dryRun) {
    console.log('PASS: dry-run validated ' + prepared.length + ' canonically bound verified_cold lead(s) with exact a, b, c prompt bytes.');
    prepared.forEach(function (item) { console.log('PLAN: ' + item.outputFilename + ' → ' + item.workerInput.image_prompts.map(function (prompt) { return prompt.direction_id + ':' + prompt.filename + ':' + prompt.prompt_sha256.slice(0, 12); }).join(', ')); });
    console.log('DRY RUN: no prompt file, provider, Keychain, Drupal record, proof, production route, mail, or scheduler was changed.');
    return;
  }
  writeOutput(output, prepared, manifest);
  console.log('PASS: wrote ' + prepared.length + ' offline Gemini Flash Lite worker input file(s) to ' + output + '.');
  console.log('STATUS: local handoff only; provider execution, receipt finalization, browser QA, review, import, owner approval, and email remain blocked.');
}

try {
  main();
} catch (error) {
  process.stderr.write('GEMINI_FLASH_LITE_COHORT_ADAPTER_ERROR: ' + error.message + '\n');
  process.exitCode = 1;
}
