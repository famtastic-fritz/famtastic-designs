#!/usr/bin/env node
/**
 * Assembles one finalized, runtime-bound Beauty / Hair / Braiding proof
 * bundle into the narrow verified-cold callback transport.
 *
 * This is intentionally a local file transformation only. It cannot call an
 * image provider, Drupal, a promoter, a mailer, or a dispatcher. The resulting
 * file must still pass the separately owner-gated
 * `famtastic:verified-cold-proof-import` command before any proof records are
 * registered. Do not use `promote-local-proof-godaddy.sh` for this lane.
 */

import { createHash } from 'node:crypto';
import { existsSync, lstatSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';

const CALLBACK_SCHEMA = 'famtastic.verified-cold-proof-callback.v1';
const ASSET_CALLBACK_SCHEMA = 'famtastic.signed-proof-assets.callback.v1';
const RUNTIME_BINDING_SCHEMA = 'famtastic.beauty-proof-runtime-binding.v1';
const DIRECTIONS = ['a', 'b', 'c'];
const MAX_HTML_BYTES = 500000;
const MAX_THUMBNAIL_BYTES = 1500000;
const MAX_CALLBACK_BYTES = 24 * 1024 * 1024;

function fail(message) {
  throw new Error(message);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function readJson(path, label) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  }
  catch (error) {
    fail((label || path) + ' is not valid JSON: ' + error.message);
  }
}

function requireRegularFile(path, label, maximumBytes = Number.MAX_SAFE_INTEGER) {
  if (!existsSync(path)) fail(label + ' is missing: ' + path);
  const stat = lstatSync(path);
  if (!stat.isFile() || stat.isSymbolicLink()) fail(label + ' must be a non-symlink regular file: ' + path);
  if (stat.size < 1 || stat.size > maximumBytes) fail(label + ' is empty or exceeds its bounded size limit.');
  return stat;
}

function requireDirectory(path, label) {
  if (!existsSync(path)) fail(label + ' is missing: ' + path);
  const stat = lstatSync(path);
  if (!stat.isDirectory() || stat.isSymbolicLink()) fail(label + ' must be a non-symlink directory: ' + path);
}

function isObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function positiveId(value, label) {
  if (!Number.isSafeInteger(value) || value < 1) fail(label + ' must be a positive integer.');
  return value;
}

function safeReference(value, label, maximum = 255) {
  const text = String(value || '').trim();
  if (text.length > maximum || !/^[A-Za-z0-9][A-Za-z0-9._:-]{1,254}$/.test(text) || /^(?:local-|beauty-proof:)/i.test(text)) {
    fail(label + ' is not a canonical non-local reference.');
  }
  return text;
}

function validHash(value, label) {
  const text = String(value || '').trim().toLowerCase();
  if (!/^[a-f0-9]{64}$/.test(text)) fail(label + ' must be a SHA-256 hex digest.');
  return text;
}

function iso(value, label) {
  const text = String(value || '').trim();
  if (!text || text.length > 80 || Number.isNaN(Date.parse(text))) fail(label + ' must be an ISO date-time.');
  return text;
}

function imageMime(bytes) {
  if (bytes.length >= 8 && bytes.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) return 'image/png';
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
  return '';
}

function exactDirectionIds(value, label) {
  if (!Array.isArray(value) || value.length !== DIRECTIONS.length || value.some(function (item, index) { return item !== DIRECTIONS[index]; })) {
    fail(label + ' must be exactly a, b, c.');
  }
}

function options(argv) {
  const result = { bundle: '', assets: '', output: '' };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--bundle') result.bundle = argv[++index] || '';
    else if (argv[index] === '--assets') result.assets = argv[++index] || '';
    else if (argv[index] === '--output') result.output = argv[++index] || '';
    else if (argv[index] === '--help' || argv[index] === '-h') {
      console.log('Usage: node website-delivery-swarm/cohorts/beauty-hair-braiding/assemble-verified-cold-callback.mjs --bundle /absolute/finalized-bundle --assets /absolute/signed-assets.callback.json --output /absolute/private/verified-cold.callback.json');
      process.exit(0);
    }
    else fail('Unknown argument: ' + argv[index]);
  }
  if (!result.bundle || !result.assets || !result.output) fail('--bundle, --assets, and --output are required.');
  return result;
}

function runtimeBinding(bundle, manifest, dna) {
  const bindingPath = join(bundle, 'runtime-binding.json');
  requireRegularFile(bindingPath, 'Immutable runtime binding', 1024 * 1024);
  const bindingBytes = readFileSync(bindingPath);
  const binding = readJson(bindingPath, 'Immutable runtime binding');
  if (!isObject(binding) || binding.schema !== RUNTIME_BINDING_SCHEMA || binding.status !== 'bound') {
    fail('Bundle is missing a bound immutable runtime binding.');
  }
  const bindingSha = sha256(bindingBytes);
  const result = {
    prospect_id: positiveId(binding.prospect_id, 'runtime binding prospect_id'),
    proof_campaign_id: positiveId(binding.proof_campaign_id, 'runtime binding proof_campaign_id'),
    campaign_id: safeReference(binding.campaign_id, 'runtime binding campaign_id', 128),
    job_id: safeReference(binding.job_id, 'runtime binding job_id'),
    event_id: safeReference(binding.callback_event_id, 'runtime binding callback_event_id'),
    run_started_at: iso(binding.run_started_at, 'runtime binding run_started_at'),
    source_lane: 'verified_cold',
    runtime_binding_sha256: bindingSha,
  };
  if (binding.public_preview_delivery_id !== undefined) result.public_preview_delivery_id = positiveId(binding.public_preview_delivery_id, 'runtime binding public_preview_delivery_id');
  if (!isObject(manifest) || manifest.source_lane !== 'verified_cold' || manifest.campaign_id !== result.campaign_id || manifest.job_id !== result.job_id || manifest.event_id !== result.event_id || !isObject(manifest.runtime_binding) || validHash(manifest.runtime_binding.sha256, 'manifest runtime binding sha256') !== bindingSha) {
    fail('Bundle manifest does not match its immutable verified-cold runtime binding.');
  }
  if (!isObject(dna) || dna.schema !== 'famtastic.build-dna.v1' || !isObject(dna.run)) fail('Bundle Build DNA is missing a final run record.');
  const run = dna.run;
  if (run.status !== 'locally-finalized-not-imported'
    || run.prospect_id !== result.prospect_id
    || run.proof_campaign_id !== result.proof_campaign_id
    || run.campaign_id !== result.campaign_id
    || run.source_lane !== 'verified_cold'
    || run.job_id !== result.job_id
    || run.callback_event_id !== result.event_id
    || iso(run.started_at || run.run_started_at, 'Build DNA run started_at') !== result.run_started_at
    || validHash(run.binding_sha256, 'Build DNA run binding sha256') !== bindingSha) {
    fail('Bundle Build DNA does not match its immutable verified-cold runtime binding.');
  }
  if (result.public_preview_delivery_id !== undefined && run.public_preview_delivery_id !== result.public_preview_delivery_id) {
    fail('Bundle Build DNA does not match its runtime-bound public preview delivery.');
  }
  return result;
}

function assetVariants(path, binding) {
  requireRegularFile(path, 'Signed asset callback', MAX_CALLBACK_BYTES);
  const document = readJson(path, 'Signed asset callback');
  if (!isObject(document) || document.schema !== ASSET_CALLBACK_SCHEMA
    || document.prospect_id !== binding.prospect_id
    || document.proof_campaign_id !== binding.proof_campaign_id
    || document.campaign_id !== binding.campaign_id
    || document.job_id !== binding.job_id
    || document.event_id !== binding.event_id
    || document.source_lane !== 'verified_cold'
    || validHash(document.runtime_binding_sha256, 'signed asset runtime binding sha256') !== binding.runtime_binding_sha256) {
    fail('Signed asset callback does not match the finalized bundle runtime binding.');
  }
  const variants = document.variants;
  if (!Array.isArray(variants)) fail('Signed asset callback variants must be a list.');
  exactDirectionIds(variants.map(function (variant) { return variant && variant.direction_id; }), 'Signed asset callback direction IDs');
  variants.forEach(function (variant) {
    if (!isObject(variant) || !Array.isArray(variant.assets) || variant.assets.length < 1 || variant.assets.length > 4) {
      fail('Signed asset callback must include one to four assets for every direction.');
    }
    variant.assets.forEach(function (asset) {
      if (!isObject(asset) || !/^[a-z][a-z0-9_-]{0,63}$/.test(String(asset.asset_id || '')) || !/^[A-Za-z0-9][A-Za-z0-9._-]{0,95}$/.test(String(asset.relative_path || '')) || !['image/jpeg', 'image/png', 'image/webp', 'image/avif'].includes(asset.media_type) || validHash(asset.sha256, 'signed asset sha256') !== sha256(Buffer.from(String(asset.base64 || ''), 'base64'))) {
        fail('Signed asset callback contains an invalid asset.');
      }
    });
  });
  return { variants, sourceManifestSha256: validHash(document.source_manifest_sha256, 'signed asset source manifest sha256') };
}

function callbackVariants(bundle, dna, serialized) {
  const artifacts = Array.isArray(dna.artifacts) ? dna.artifacts : [];
  return DIRECTIONS.map(function (direction) {
    const htmlPath = join(bundle, direction, 'index.html');
    requireRegularFile(htmlPath, direction + ' proof HTML', MAX_HTML_BYTES);
    const html = readFileSync(htmlPath, 'utf8');
    if (!html || /<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i.test(html)) {
      fail(direction + ' proof HTML is missing or contains active content.');
    }
    const expectedHtml = artifacts.some(function (artifact) {
      return isObject(artifact) && artifact.role === 'proof-page-' + direction && artifact.sha256 === sha256(Buffer.from(html, 'utf8'));
    });
    if (!expectedHtml) fail('Build DNA is missing the exact proof page hash for direction ' + direction + '.');
    const thumbnailCandidates = [join(bundle, direction, 'thumbnail.png'), join(bundle, direction, 'thumbnail.jpg'), join(bundle, direction, 'thumbnail.jpeg')];
    const thumbnailPath = thumbnailCandidates.find(function (path) { return existsSync(path); });
    if (!thumbnailPath) fail('A PNG or JPEG thumbnail is required for direction ' + direction + '.');
    requireRegularFile(thumbnailPath, direction + ' proof thumbnail', MAX_THUMBNAIL_BYTES);
    const thumbnail = readFileSync(thumbnailPath);
    const thumbnailMime = imageMime(thumbnail);
    if (!thumbnailMime) fail(direction + ' proof thumbnail is not a PNG or JPEG.');
    const designPath = join(bundle, direction, 'design-dna.json');
    requireRegularFile(designPath, direction + ' design DNA', 1024 * 1024);
    const design = readJson(designPath, direction + ' design DNA');
    if (!isObject(design)) fail(direction + ' design DNA must be an object.');
    const signed = serialized.variants.find(function (entry) { return entry.direction_id === direction; });
    if (!signed) fail('Signed assets are missing direction ' + direction + '.');
    signed.assets.forEach(function (asset) {
      const found = artifacts.some(function (artifact) {
        return isObject(artifact) && artifact.role === 'proof-asset-' + direction && artifact.sha256 === asset.sha256;
      });
      if (!found) fail('Build DNA is missing the exact signed asset hash for direction ' + direction + '.');
    });
    return {
      direction_id: direction,
      html,
      thumbnail_base64: thumbnail.toString('base64'),
      thumbnail_media_type: thumbnailMime,
      design_dna: design,
      assets: signed.assets,
    };
  });
}

function main() {
  const args = options(process.argv.slice(2));
  const bundle = resolve(args.bundle);
  const assetsPath = resolve(args.assets);
  const output = resolve(args.output);
  requireDirectory(bundle, 'Finalized proof bundle');
  if (existsSync(output)) fail('Refusing to overwrite an existing callback output.');
  requireDirectory(dirname(output), 'Callback output parent');
  const manifestPath = join(bundle, 'manifest.json');
  const dnaPath = join(bundle, 'build-dna.json');
  requireRegularFile(manifestPath, 'Bundle manifest', 4 * 1024 * 1024);
  requireRegularFile(dnaPath, 'Final Build DNA', 10 * 1024 * 1024);
  const manifest = readJson(manifestPath, 'Bundle manifest');
  const dnaBytes = readFileSync(dnaPath);
  const dna = readJson(dnaPath, 'Final Build DNA');
  const binding = runtimeBinding(bundle, manifest, dna);
  const serialized = assetVariants(assetsPath, binding);
  const variants = callbackVariants(bundle, dna, serialized);
  const payload = {
    schema: CALLBACK_SCHEMA,
    event_id: binding.event_id,
    campaign_id: binding.campaign_id,
    job_id: binding.job_id,
    source_lane: 'verified_cold',
    prospect_id: binding.prospect_id,
    proof_campaign_id: binding.proof_campaign_id,
    run_started_at: binding.run_started_at,
    runtime_binding_sha256: binding.runtime_binding_sha256,
    build_dna_sha256: sha256(dnaBytes),
    signed_asset_manifest_sha256: serialized.sourceManifestSha256,
    ...(binding.public_preview_delivery_id === undefined ? {} : { public_preview_delivery_id: binding.public_preview_delivery_id }),
    variants,
    local_only: true,
    no_external_actions: ['no image provider call', 'no Drupal write', 'no proof promotion', 'no publication', 'no email send'],
  };
  const json = JSON.stringify(payload, null, 2) + '\n';
  if (Buffer.byteLength(json, 'utf8') > MAX_CALLBACK_BYTES) fail('Assembled callback exceeds the signed callback size limit.');
  writeFileSync(output, json, { flag: 'wx', mode: 0o600 });
  console.log('PASS: assembled one ' + CALLBACK_SCHEMA + ' payload with 3 signed directions.');
  console.log('Output: ' + output);
  console.log('STATUS: local callback assembly only; import, owner review, publication, and email remain locked.');
}

try {
  main();
}
catch (error) {
  console.error('FAIL: ' + error.message);
  process.exit(1);
}
