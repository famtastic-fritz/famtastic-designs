#!/usr/bin/env node
/**
 * Serializes finalized local proof assets into the canonical callback `assets`
 * wire shape. This tool is deliberately local-only: it never uploads, imports,
 * publishes, sends mail, or invokes an image provider.
 */

import { createHash } from 'node:crypto';
import { existsSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { resolve, join } from 'node:path';

const ASSET_SCHEMA = 'famtastic.signed-proof-assets.v1';
const RUNTIME_BINDING_SCHEMA = 'famtastic.beauty-proof-runtime-binding.v1';
const DIRECTIONS = ['a', 'b', 'c'];
const MAX_ASSET_BYTES = 1500000;

function fail(message) {
  throw new Error(message);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function readJson(path, label) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    fail((label || path) + ' is not valid JSON: ' + error.message);
  }
}

function requireFile(path, label) {
  if (!existsSync(path) || !statSync(path).isFile()) fail(label + ' is missing: ' + path);
}

function imageMime(bytes) {
  if (bytes.length >= 8 && bytes.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) return 'image/png';
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
  if (bytes.length >= 12 && bytes.subarray(0, 4).toString('ascii') === 'RIFF' && bytes.subarray(8, 12).toString('ascii') === 'WEBP') return 'image/webp';
  return '';
}

function safePath(value) {
  if (typeof value !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._-]{0,95}(\/[A-Za-z0-9][A-Za-z0-9._-]{0,95}){0,5}$/.test(value) || value.startsWith('.')) fail('Asset relative_path is unsafe.');
  return value;
}

function positiveId(value, label) {
  if (!Number.isSafeInteger(value) || value < 1) fail(label + ' must be a positive integer.');
  return value;
}

function safeReference(value, label, maximum = 255) {
  const text = String(value || '').trim();
  if (!/^[A-Za-z0-9][A-Za-z0-9._:-]{1,254}$/.test(text) || text.length > maximum) fail(label + ' is unsafe.');
  return text;
}

function validHash(value, label) {
  const text = String(value || '').trim().toLowerCase();
  if (!/^[a-f0-9]{64}$/.test(text)) fail(label + ' must be a SHA-256 hex digest.');
  return text;
}

function runtimeBinding(bundle, manifest) {
  const path = join(bundle, 'runtime-binding.json');
  requireFile(path, 'Immutable runtime binding');
  const binding = readJson(path, 'Immutable runtime binding');
  if (!binding || typeof binding !== 'object' || Array.isArray(binding) || binding.schema !== RUNTIME_BINDING_SCHEMA || binding.status !== 'bound') {
    fail('Bundle is missing a bound immutable runtime binding.');
  }
  if (!manifest.runtime_binding || manifest.runtime_binding.schema !== RUNTIME_BINDING_SCHEMA || manifest.runtime_binding.status !== 'bound') {
    fail('Bundle manifest is missing its bound runtime binding summary.');
  }
  const hash = sha256(readFileSync(path));
  if (validHash(manifest.runtime_binding.sha256, 'manifest runtime binding sha256') !== hash) fail('Bundle manifest runtime binding hash does not match the sidecar.');
  const prospectId = positiveId(binding.prospect_id, 'runtime binding prospect_id');
  const proofCampaignId = positiveId(binding.proof_campaign_id, 'runtime binding proof_campaign_id');
  const campaignId = safeReference(binding.campaign_id, 'runtime binding campaign_id', 128);
  const jobId = safeReference(binding.job_id, 'runtime binding job_id');
  const eventId = safeReference(binding.callback_event_id, 'runtime binding callback_event_id');
  if (binding.source_lane !== 'verified_cold' || manifest.source_lane !== 'verified_cold' || manifest.campaign_id !== campaignId || manifest.job_id !== jobId || manifest.event_id !== eventId) {
    fail('Bundle manifest does not match its exact verified_cold runtime binding.');
  }
  if (/^(?:local-|beauty-proof:)/i.test(jobId) || /^(?:local-|beauty-proof:)/i.test(eventId)) fail('Runtime binding contains a local callback placeholder.');
  const output = {
    prospect_id: prospectId,
    proof_campaign_id: proofCampaignId,
    campaign_id: campaignId,
    job_id: jobId,
    event_id: eventId,
    source_lane: 'verified_cold',
    runtime_binding_sha256: hash,
  };
  if (binding.public_preview_delivery_id !== undefined) output.public_preview_delivery_id = positiveId(binding.public_preview_delivery_id, 'runtime binding public_preview_delivery_id');
  return output;
}

function finalBuildDna(bundle, binding) {
  const path = join(bundle, 'build-dna.json');
  requireFile(path, 'Final Build DNA');
  const dna = readJson(path, 'Final Build DNA');
  if (!dna || typeof dna !== 'object' || Array.isArray(dna) || dna.schema !== 'famtastic.build-dna.v1' || !dna.run || typeof dna.run !== 'object' || Array.isArray(dna.run)) {
    fail('Finalized bundle is missing a Build DNA run.');
  }
  const run = dna.run;
  if (run.status !== 'locally-finalized-not-imported' || run.prospect_id !== binding.prospect_id || run.proof_campaign_id !== binding.proof_campaign_id || run.campaign_id !== binding.campaign_id || run.source_lane !== binding.source_lane || run.job_id !== binding.job_id || run.callback_event_id !== binding.event_id || validHash(run.binding_sha256, 'Build DNA run binding sha256') !== binding.runtime_binding_sha256) {
    fail('Final Build DNA does not match the immutable runtime binding.');
  }
  return dna;
}

function assertDnaAssetHashes(dna, variants) {
  const artifacts = Array.isArray(dna.artifacts) ? dna.artifacts : [];
  variants.forEach(function (variant) {
    variant.assets.forEach(function (asset) {
      const found = artifacts.some(function (artifact) {
        return artifact && artifact.role === 'proof-asset-' + variant.direction_id && artifact.sha256 === asset.sha256;
      });
      if (!found) fail('Final Build DNA is missing the exact signed asset hash for direction ' + variant.direction_id + '.');
    });
  });
}

function options(argv) {
  const value = { bundle: '', output: '' };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--bundle') value.bundle = argv[++index] || '';
    else if (argv[index] === '--output') value.output = argv[++index] || '';
    else if (argv[index] === '--help' || argv[index] === '-h') {
      console.log('Usage: node website-delivery-swarm/cohorts/beauty-hair-braiding/serialize-signed-proof-assets.mjs --bundle artifacts/.../lead --output /secure/callback-assets.json');
      process.exit(0);
    } else fail('Unknown argument: ' + argv[index]);
  }
  if (!value.bundle || !value.output) fail('--bundle and --output are required.');
  return value;
}

function manifestAssetsForDirection(manifest, direction) {
  const variants = manifest.proof_assets && manifest.proof_assets.variants;
  if (!Array.isArray(variants)) fail('Bundle manifest has no signed proof asset variants.');
  const variant = variants.find(function (entry) { return entry && entry.direction_id === direction; });
  if (!variant || !Array.isArray(variant.assets) || variant.assets.length === 0) fail('Bundle manifest is missing assets for direction ' + direction + '.');
  return variant.assets;
}

function serializeDirection(bundle, manifest, direction) {
  const storedPath = join(bundle, direction, 'assets.json');
  requireFile(storedPath, direction + ' stored asset manifest');
  const stored = readJson(storedPath, direction + ' stored asset manifest');
  if (!Array.isArray(stored) || stored.length === 0 || stored.length > 4) fail(direction + ' stored asset manifest is invalid.');
  const expected = manifestAssetsForDirection(manifest, direction);
  if (expected.length !== stored.length) fail(direction + ' root and stored asset manifests disagree.');
  const seen = new Set();
  const assets = stored.map(function (asset, index) {
    if (!asset || typeof asset !== 'object') fail(direction + ' asset manifest contains an invalid entry.');
    const id = String(asset.asset_id || '').trim();
    const relativePath = safePath(asset.relative_path);
    const mediaType = String(asset.media_type || '').trim().toLowerCase();
    const artifactPath = String(asset.artifact_path || '').trim();
    if (!/^[a-z][a-z0-9_-]{0,63}$/.test(id) || seen.has(id) || !['image/jpeg', 'image/png', 'image/webp', 'image/avif'].includes(mediaType) || artifactPath !== direction + '/assets/' + relativePath) fail(direction + ' asset manifest contains unsafe fields.');
    seen.add(id);
    const expectedAsset = expected.find(function (item) { return item.asset_id === id && item.relative_path === relativePath; });
    if (!expectedAsset || expectedAsset.sha256 !== asset.sha256 || Number(expectedAsset.size_bytes) !== Number(asset.size_bytes)) fail(direction + ' root and stored asset manifests disagree for ' + id + '.');
    const artifact = join(bundle, direction, 'assets', relativePath);
    requireFile(artifact, direction + ' asset ' + relativePath);
    const bytes = readFileSync(artifact);
    if (bytes.length < 1 || bytes.length > MAX_ASSET_BYTES) fail(direction + ' asset exceeds the 1.5 MB signed-asset limit.');
    if (imageMime(bytes) !== mediaType) fail(direction + ' asset MIME does not match its bytes.');
    const hash = sha256(bytes);
    if (hash !== asset.sha256 || hash !== expectedAsset.sha256 || bytes.length !== Number(asset.size_bytes)) fail(direction + ' asset hash or size does not match its manifest.');
    return { asset_id: id, relative_path: relativePath, media_type: mediaType, base64: bytes.toString('base64'), sha256: hash, _sort: index };
  });
  assets.sort(function (left, right) { return left.relative_path.localeCompare(right.relative_path); });
  return { direction_id: direction, assets: assets.map(function (asset) { const { _sort, ...wire } = asset; return wire; }) };
}

function main() {
  const args = options(process.argv.slice(2));
  const bundle = resolve(args.bundle);
  const output = resolve(args.output);
  const manifestPath = join(bundle, 'manifest.json');
  requireFile(manifestPath, 'Bundle manifest');
  const manifest = readJson(manifestPath, 'Bundle manifest');
  if (manifest.proof_asset_contract !== ASSET_SCHEMA || !manifest.proof_assets || manifest.proof_assets.schema !== ASSET_SCHEMA) fail('Bundle is not a finalized signed-proof-asset bundle.');
  const binding = runtimeBinding(bundle, manifest);
  const dna = finalBuildDna(bundle, binding);
  const variants = DIRECTIONS.map(function (direction) { return serializeDirection(bundle, manifest, direction); });
  assertDnaAssetHashes(dna, variants);
  const payload = {
    schema: 'famtastic.signed-proof-assets.callback.v1',
    source_manifest_sha256: sha256(readFileSync(manifestPath)),
    ...binding,
    variants,
    local_only: true,
    no_external_actions: ['no upload', 'no Drupal import', 'no publication', 'no email send'],
  };
  writeFileSync(output, JSON.stringify(payload, null, 2) + '\n');
  console.log('PASS: serialized ' + payload.variants.length + ' signed-asset callback variants locally.');
  console.log('Output: ' + output);
  console.log('STATUS: no upload, Drupal import, publication, or email was performed.');
}

try {
  main();
} catch (error) {
  console.error('FAIL: ' + error.message);
  process.exit(1);
}
