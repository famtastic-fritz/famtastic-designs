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
  const payload = {
    schema: 'famtastic.signed-proof-assets.callback.v1',
    source_manifest_sha256: sha256(readFileSync(manifestPath)),
    campaign_id: manifest.campaign_id,
    job_id: manifest.job_id,
    event_id: manifest.event_id,
    variants: DIRECTIONS.map(function (direction) { return serializeDirection(bundle, manifest, direction); }),
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
