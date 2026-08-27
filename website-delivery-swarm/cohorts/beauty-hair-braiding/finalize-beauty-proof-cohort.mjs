#!/usr/bin/env node
/**
 * Local-only finalizer for a prepared Beauty / Hair / Braiding proof cohort.
 *
 * This tool does not call Gemini, Drupal, the proof promoter, mail, or a live
 * service. It only turns externally supplied, receipt-backed source artwork
 * into portable WebP hero assets inside an already prepared local cohort.
 */

import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, '../../..');
const INPUT_SCHEMA = 'famtastic.beauty-proof-cohort-finalizer-input.v1';
const FINALIZATION_SCHEMA = 'famtastic.beauty-proof-cohort-finalization.v1';
const ASSET_SCHEMA = 'famtastic.signed-proof-assets.v1';
const REQUIRED_SOURCE_LANE = 'verified_cold';
const REQUIRED_PACKAGE_PROFILE = 'anonymous_safe_medium_ultra_v1';
const REQUIRED_DIRECTIONS = ['a', 'b', 'c'];
const REQUIRED_MODEL = 'gemini-3.1-flash-lite-image';
const MAX_HERO_BYTES = 1500000;
const MAX_HTML_BYTES = 500000;

function fail(message) {
  throw new Error(message);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function fileHash(path) {
  return sha256(readFileSync(path));
}

function readJson(path, label) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    fail((label || path) + ' is not valid JSON: ' + error.message);
  }
}

function json(value) {
  return JSON.stringify(value, null, 2) + '\n';
}

function writeJson(path, value) {
  writeFileSync(path, json(value));
}

function cleanText(value, label, max = 1000, required = true) {
  if (value === undefined || value === null || value === '') {
    if (required) fail(label + ' is required.');
    return '';
  }
  const text = String(value).trim();
  if (!text && required) fail(label + ' is required.');
  if (text.length > max) fail(label + ' exceeds ' + max + ' characters.');
  return text;
}

function isObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function requireObject(value, label) {
  if (!isObject(value)) fail(label + ' must be an object.');
  return value;
}

function exactStringArray(value, expected, label) {
  if (!Array.isArray(value) || value.length !== expected.length || value.some(function (item, index) { return item !== expected[index]; })) {
    fail(label + ' must be exactly ' + expected.join(', ') + '.');
  }
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

function requireExistingFile(path, label) {
  if (!existsSync(path) || !statSync(path).isFile()) fail(label + ' does not exist or is not a file: ' + path);
}

function requireSafeReceipt(value, path) {
  const forbidden = /(?:api[_-]?key|authorization|access[_-]?token|refresh[_-]?token|password|secret)/i;
  const visit = function (node, keyPath) {
    if (Array.isArray(node)) {
      node.forEach(function (item, index) { visit(item, keyPath + '[' + index + ']'); });
      return;
    }
    if (!isObject(node)) return;
    Object.entries(node).forEach(function ([key, item]) {
      if (forbidden.test(key)) fail('Provider receipt contains a forbidden credential-like field at ' + keyPath + '.' + key + ': ' + path);
      visit(item, keyPath + '.' + key);
    });
  };
  visit(value, 'receipt');
}

function imageMime(buffer) {
  if (buffer.length >= 8 && buffer.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) return 'image/png';
  if (buffer.length >= 3 && buffer[0] === 0xff && buffer[1] === 0xd8 && buffer[2] === 0xff) return 'image/jpeg';
  if (buffer.length >= 12 && buffer.subarray(0, 4).toString('ascii') === 'RIFF' && buffer.subarray(8, 12).toString('ascii') === 'WEBP') return 'image/webp';
  return '';
}

function requireIso(value, label) {
  const text = cleanText(value, label, 80);
  if (Number.isNaN(Date.parse(text))) fail(label + ' must be an ISO date-time.');
  return text;
}

function validHash(value, label) {
  const text = cleanText(value, label, 64);
  if (!/^[a-f0-9]{64}$/i.test(text)) fail(label + ' must be a SHA-256 hex digest.');
  return text.toLowerCase();
}

function numberOrNull(value, label) {
  if (value === undefined || value === null || value === '') return null;
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) fail(label + ' must be a non-negative finite number.');
  return value;
}

function receiptCost(receipt) {
  const cost = isObject(receipt.cost) ? receipt.cost : {};
  const actual = numberOrNull(cost.usd ?? receipt.cost_usd, 'receipt cost_usd');
  const expected = numberOrNull(cost.expected_usd ?? receipt.cost_usd_expected_per_image_output, 'receipt expected cost');
  const result = {
    status: actual === null ? 'receipt-recorded-without-billed-cost' : 'receipt-recorded',
    currency: 'USD',
    receipt_status: 'provider-receipt-validated',
  };
  if (actual !== null) result.amount = actual;
  if (expected !== null) result.expected_amount = expected;
  return result;
}

function normalizeReceipt(receiptPath, sourceImage, promptHash, requestedResultId) {
  requireExistingFile(receiptPath, 'Provider receipt');
  const raw = readFileSync(receiptPath);
  const receipt = readJson(receiptPath, 'Provider receipt');
  requireSafeReceipt(receipt, receiptPath);
  if (receipt.schema && receipt.schema !== 'famtastic.gemini-image-receipt.v1') {
    fail('Provider receipt schema must be famtastic.gemini-image-receipt.v1 when declared.');
  }
  const provider = cleanText(receipt.provider, 'receipt.provider', 120);
  if (!['google-gemini-api', 'gemini-developer-api'].includes(provider)) fail('receipt.provider must identify the Gemini Developer API.');
  const api = cleanText(receipt.api, 'receipt.api', 120);
  if (!['generateContent', 'interactions'].includes(api)) fail('receipt.api must be generateContent or interactions.');
  const model = cleanText(receipt.model, 'receipt.model', 160);
  if (model !== REQUIRED_MODEL) fail('receipt.model must be ' + REQUIRED_MODEL + '.');
  if (receipt.status !== 'completed') fail('receipt.status must be completed.');
  const startedAt = requireIso(receipt.started_at, 'receipt.started_at');
  const completedAt = requireIso(receipt.completed_at, 'receipt.completed_at');
  if (Date.parse(completedAt) < Date.parse(startedAt)) fail('receipt.completed_at cannot be before receipt.started_at.');
  const evidence = receipt.usage_metadata || receipt.usageMetadata || receipt.response_sha256 || receipt.interaction_id;
  if (!evidence || (isObject(evidence) && Object.keys(evidence).length === 0)) fail('Provider receipt needs non-empty usage_metadata, response_sha256, or interaction_id evidence.');
  if (!Array.isArray(receipt.results) || receipt.results.length === 0) fail('receipt.results must be a non-empty array.');
  const resultId = cleanText(requestedResultId, 'receipt_result_id', 240);
  const result = receipt.results.find(function (item) { return isObject(item) && item.id === resultId; });
  if (!result) fail('Provider receipt does not contain receipt_result_id ' + resultId + '.');
  const sourceBytes = readFileSync(sourceImage);
  const mime = imageMime(sourceBytes);
  if (!['image/png', 'image/jpeg'].includes(mime)) fail('External hero image must be a PNG or JPEG source image.');
  const sourceSha = sha256(sourceBytes);
  if (validHash(result.sha256, 'receipt result sha256') !== sourceSha) fail('Provider receipt result hash does not match supplied hero image.');
  if (!Number.isInteger(result.bytes) || result.bytes !== sourceBytes.length) fail('Provider receipt result bytes do not match supplied hero image.');
  if (cleanText(result.mime_type, 'receipt result mime_type', 80) !== mime) fail('Provider receipt result mime_type does not match supplied hero image.');
  if (!Number.isInteger(result.duration_ms) || result.duration_ms <= 0) fail('receipt result duration_ms must be a positive integer.');
  if (validHash(result.prompt_sha256, 'receipt result prompt_sha256') !== promptHash) fail('Provider receipt result prompt hash does not match the exact generated prompt artifact.');
  const normalized = {
    schema: 'famtastic.gemini-image-provider-receipt.v1',
    status: 'provider-receipt-validated',
    provider,
    api,
    model,
    started_at: startedAt,
    completed_at: completedAt,
    provider_evidence: receipt.usage_metadata || receipt.usageMetadata || receipt.response_sha256 || receipt.interaction_id,
    provider_receipt_source_sha256: sha256(raw),
    cost: receiptCost(receipt),
    result: {
      id: resultId,
      sha256: sourceSha,
      bytes: sourceBytes.length,
      mime_type: mime,
      duration_ms: result.duration_ms,
      prompt_sha256: promptHash,
    },
    privacy: 'Normalized local receipt intentionally excludes credentials and inline image bytes.',
  };
  return { sourceBytes, sourceSha, sourceMime: mime, sourceSize: sourceBytes.length, normalized, sourceReceiptSha: normalized.provider_receipt_source_sha256 };
}

function cwebpAvailable() {
  const result = spawnSync('cwebp', ['-version'], { encoding: 'utf8' });
  if (result.status !== 0) fail('cwebp is required for portable hero normalization; install libwebp before finalizing.');
}

function normalizeWebp(sourceImage, tempRoot) {
  const output = join(tempRoot, 'hero-' + sha256(sourceImage).slice(0, 12) + '.webp');
  const result = spawnSync('cwebp', ['-quiet', '-q', '95', '-m', '6', sourceImage, '-o', output], { encoding: 'utf8' });
  if (result.status !== 0 || !existsSync(output)) fail('cwebp failed for ' + sourceImage + ': ' + (result.stderr || result.stdout || 'unknown error').trim());
  const bytes = readFileSync(output);
  if (imageMime(bytes) !== 'image/webp') fail('cwebp did not emit a valid WebP asset for ' + sourceImage + '.');
  if (bytes.length === 0 || bytes.length > MAX_HERO_BYTES) fail('Normalized hero asset must be between 1 and ' + MAX_HERO_BYTES + ' bytes.');
  return bytes;
}

function options(argv) {
  const value = { input: '', dryRun: false };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--input') value.input = argv[++index] || '';
    else if (argv[index] === '--dry-run') value.dryRun = true;
    else if (argv[index] === '--help' || argv[index] === '-h') {
      console.log('Usage: node website-delivery-swarm/cohorts/beauty-hair-braiding/finalize-beauty-proof-cohort.mjs --input /secure/finalizer-input.json [--dry-run]');
      process.exit(0);
    } else fail('Unknown argument: ' + argv[index]);
  }
  if (!value.input) fail('--input is required.');
  return value;
}

function loadInput(path) {
  requireExistingFile(path, 'Finalizer input');
  const value = readJson(path, 'Finalizer input');
  requireObject(value, 'Finalizer input');
  if (value.schema !== INPUT_SCHEMA) fail('Finalizer input schema must be ' + INPUT_SCHEMA + '.');
  if (cleanText(value.source_lane, 'source_lane', 80) !== REQUIRED_SOURCE_LANE) fail('source_lane must be ' + REQUIRED_SOURCE_LANE + '.');
  if (cleanText(value.package_profile, 'package_profile', 120) !== REQUIRED_PACKAGE_PROFILE) fail('package_profile must be ' + REQUIRED_PACKAGE_PROFILE + '.');
  const cohortPath = resolveInsideRepo(value.cohort_manifest, 'cohort_manifest');
  requireExistingFile(cohortPath, 'cohort_manifest');
  const cohort = readJson(cohortPath, 'cohort_manifest');
  if (cohort.schema !== 'famtastic.beauty-proof-cohort-output.v1') fail('cohort_manifest has an unsupported schema.');
  if (!isObject(cohort.source) || cohort.source.source_lane !== REQUIRED_SOURCE_LANE) fail('cohort_manifest source.source_lane must be ' + REQUIRED_SOURCE_LANE + '.');
  if (cohort.package_profile !== REQUIRED_PACKAGE_PROFILE) fail('cohort_manifest package_profile must be ' + REQUIRED_PACKAGE_PROFILE + '.');
  if (!Array.isArray(cohort.bundles) || cohort.bundles.length === 0 || cohort.selected_count !== cohort.bundles.length) fail('cohort_manifest must contain every selected bundle exactly once.');
  if (!Array.isArray(value.bundles) || value.bundles.length !== cohort.bundles.length) fail('Finalizer input must map every cohort bundle exactly once.');
  return { value, inputHash: fileHash(path), cohortPath, cohort };
}

function assetDescriptor(direction, webp) {
  return {
    asset_id: 'hero',
    relative_path: 'hero.webp',
    media_type: 'image/webp',
    sha256: sha256(webp),
    size_bytes: webp.length,
    artifact_path: direction + '/assets/hero.webp',
  };
}

function injectHero(html, directionName) {
  const pattern = /<svg class="art"(?:\s|>)[\s\S]*?<\/svg>/g;
  const matches = html.match(pattern) || [];
  if (matches.length !== 1) fail('Expected exactly one local SVG art fallback before finalization.');
  const image = '<img class="art art--provider-hero" src="assets/hero.webp" alt="Original concept artwork for the ' + directionName.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + ' direction" decoding="async">';
  const replaced = html.replace(pattern, image);
  const closingStyleCount = (replaced.match(/<\/style>/g) || []).length;
  if (closingStyleCount !== 1) fail('Expected exactly one style block in the proof page.');
  return replaced.replace('</style>', '.art--provider-hero{object-fit:cover;object-position:center}</style>');
}

function pageChecks(html, hero, direction) {
  const anchors = [...html.matchAll(/<a\b[^>]*href="(#[-a-z0-9_]+)"/gi)].map(function (match) { return match[1].slice(1); });
  const checks = {
    one_h1: (html.match(/<h1\b/gi) || []).length === 1,
    noindex: /<meta name="robots" content="noindex,nofollow,noarchive">/i.test(html),
    self_contained: !/(?:src|href)\s*=\s*"https?:\/\/|url\(\s*https?:\/\//i.test(html),
    no_active_content: !/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i.test(html),
    callback_size_limit: Buffer.byteLength(html, 'utf8') <= MAX_HTML_BYTES,
    no_contact_email: !/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i.test(html),
    proof_boundary: /PRIVATE WEBSITE CONCEPT/i.test(html),
    anchors_resolve: anchors.every(function (id) { return html.includes('id="' + id + '"'); }),
    provider_hero_injected: html.includes('src="assets/hero.webp"') && html.includes('art--provider-hero'),
    svg_fallback_removed: !/<svg class="art"/i.test(html),
    hero_asset_valid: imageMime(hero) === 'image/webp' && hero.length > 0 && hero.length <= MAX_HERO_BYTES,
  };
  const passed = Object.values(checks).every(Boolean);
  if (!passed) fail('Final static QA failed for direction ' + direction + '.');
  return {
    passed,
    bytes: Buffer.byteLength(html, 'utf8'),
    sha256: sha256(Buffer.from(html, 'utf8')),
    hero_sha256: sha256(hero),
    hero_bytes: hero.length,
    checks,
  };
}

function stageForArt(root, direction, item) {
  const receipt = item.receipt;
  const cost = receipt.cost;
  return {
    stage_id: 'preview-art-' + direction,
    attempt: 1,
    capability: 'original-preview-art',
    execution: {
      provider: { id: receipt.provider },
      model: { id: receipt.model, status: 'provider-receipt-validated' },
      transport: receipt.api,
      timing: { status: 'receipt-recorded', started_at: receipt.started_at, completed_at: receipt.completed_at, duration_ms: receipt.result.duration_ms },
      cost,
      prompt: { artifact: root + '/' + direction + '/gemini-flash-lite-image-prompt.txt', sha256: receipt.result.prompt_sha256 },
      input: { externally_supplied_source_asset_sha256: receipt.result.sha256, media_type: receipt.result.mime_type, bytes: receipt.result.bytes },
      output: { asset: item.asset, source_result_id: receipt.result.id },
      receipt: {
        artifact: root + '/' + direction + '/gemini-provider-receipt.json',
        sha256: item.localReceiptSha,
        provider_receipt_source_sha256: receipt.provider_receipt_source_sha256,
      },
    },
    result: { status: 'passed', note: 'Externally supplied hero image matched the exact prompt hash and a validated Gemini provider receipt; local WebP normalization did not invoke a provider.' },
  };
}

function buildDna(bundle, baseDna, finalization, files, revision) {
  const root = repoRelative(bundle.root);
  const stages = baseDna.stages.filter(function (stage) { return stage.stage_id !== 'preview-art'; }).map(function (stage) {
    if (stage.stage_id === 'prototype-construction') {
      return {
        ...stage,
        execution: { ...stage.execution, output: { artifacts: ['a/index.html', 'b/index.html', 'c/index.html', 'a/assets/hero.webp', 'b/assets/hero.webp', 'c/assets/hero.webp'] } },
        result: { status: 'passed', note: 'Receipt-backed provider hero assets are linked by portable local WebP paths; browser QA remains a separate gate.' },
      };
    }
    if (stage.stage_id === 'static-quality-assurance') {
      return {
        ...stage,
        execution: { ...stage.execution, output: { artifact: root + '/quality-report.json' } },
        result: { status: 'passed', note: 'Local static safety and asset-link checks passed after receipt-backed hero injection.' },
      };
    }
    if (stage.stage_id === 'browser-quality-assurance') {
      return { ...stage, result: { status: 'gated', reason: 'Receipt-backed art is now linked; desktop and 390px browser screenshots are still required.' } };
    }
    return stage;
  });
  const prototypeIndex = stages.findIndex(function (stage) { return stage.stage_id === 'prototype-construction'; });
  if (prototypeIndex < 0) fail('Prepared Build DNA is missing prototype-construction.');
  stages.splice(prototypeIndex, 0, ...bundle.directions.map(function (item) { return stageForArt(root, item.direction, item); }));
  const finalizationIndex = stages.findIndex(function (stage) { return stage.stage_id === 'prototype-construction'; });
  stages.splice(finalizationIndex, 0, {
    stage_id: 'local-art-finalization',
    attempt: 1,
    capability: 'provider-receipt-validation-and-portable-asset-normalization',
    execution: {
      provider: { id: 'deterministic-local' },
      model: { id: 'cwebp-q95-m6', status: 'executed-local' },
      timing: { status: 'recorded', duration_ms: 0 },
      cost: { status: 'not_incurred', currency: 'USD' },
      input: { artifact: root + '/finalization-report.json' },
      output: { asset_contract: ASSET_SCHEMA, variants: REQUIRED_DIRECTIONS.map(function (direction) { return direction + '/assets/hero.webp'; }) },
    },
    result: { status: 'passed', note: 'Local finalizer consumed pre-existing provider outputs only; it made no Gemini API, production, promotion, or email call.' },
  });
  return {
    ...baseDna,
    classification: 'locally-finalized-with-externally-supplied-provider-receipts',
    finalized_at: finalization.finalized_at,
    repository: { ...baseDna.repository, revision, worktree_state: 'local-finalizer-artifacts-not-promoted' },
    recipe: {
      ...baseDna.recipe,
      version: 'beauty-hair-braiding-cohort-finalizer.v1',
      source_lane: REQUIRED_SOURCE_LANE,
      package_profile: REQUIRED_PACKAGE_PROFILE,
      asset_contract: ASSET_SCHEMA,
      creative_controls: {
        ...(baseDna.recipe.creative_controls || {}),
        original_asset_route: 'Externally supplied Gemini Flash Lite output, verified against an exact prompt hash and provider receipt, then normalized locally to linked WebP assets',
      },
    },
    correlation: { ...baseDna.correlation, source_lane: REQUIRED_SOURCE_LANE, package_profile: REQUIRED_PACKAGE_PROFILE, finalizer_input_sha256: finalization.input_sha256 },
    stages,
    artifacts: files.map(function (file) {
      return {
        role: file.role,
        path: repoRelative(file.path),
        sha256: fileHash(file.path),
        retention: file.retention || 'restricted-local',
        rights_status: file.rights_status || 'operator-generated',
      };
    }),
    retrieval: {
      filesystem: { status: 'locally-finalized-not-promoted', root, build_dna: root + '/build-dna.json', asset_contract: ASSET_SCHEMA },
      database: { status: 'not_registered', required_operation: 'Use the canonical Drupal registration and signed-asset importer only after browser QA and owner approval.' },
      site_studio: { status: 'not_created', required_operation: 'Copy this immutable receipt-backed Build DNA into an eligible handoff only after the remaining gates close.' },
    },
    integrity: { artifact_hash_algorithm: 'sha256', build_dna_status: 'receipt-backed-local-finalization-with-real-artifact-hashes' },
    completion: {
      status: 'gated',
      open_gates: [
        'Desktop and 390px browser screenshots plus visual repair if needed',
        'Independent visual review and rights review',
        'Canonical Drupal registration, signed-asset import, owner approval, proof publication, and transactional outbox delivery',
      ],
    },
  };
}

function gitRevision() {
  const result = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: repositoryRoot, encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : 'unavailable';
}

function validateDna(bundle) {
  const result = spawnSync(process.execPath, [join(repositoryRoot, 'website-delivery-swarm/scripts/validate-build-dna.mjs'), join(bundle.root, 'build-dna.json'), repositoryRoot], { cwd: repositoryRoot, encoding: 'utf8' });
  if (result.status !== 0) fail('Build DNA validator failed: ' + (result.stderr || result.stdout).trim());
  return result.stdout.trim().split('\n');
}

function plannedBundle(inputBundle, cohortBundle, cohortCampaignId, inputHash, tempRoot) {
  if (!isObject(inputBundle)) fail('Each finalizer bundles entry must be an object.');
  const suppliedBundle = resolveInsideRepo(inputBundle.bundle, 'bundles[].bundle');
  const expectedBundle = resolveInsideRepo(cohortBundle.bundle, 'cohort bundle path');
  if (suppliedBundle !== expectedBundle) fail('Finalizer bundle mapping does not match the cohort bundle path.');
  requireExistingFile(join(suppliedBundle, 'manifest.json'), 'Prepared bundle manifest');
  const manifest = readJson(join(suppliedBundle, 'manifest.json'), 'Prepared bundle manifest');
  if (manifest.campaign_id !== cohortCampaignId) fail('Prepared bundle campaign ID does not match cohort manifest.');
  exactStringArray(manifest.input_snapshot && manifest.input_snapshot.direction_ids, REQUIRED_DIRECTIONS, 'Prepared bundle direction IDs');
  if ((manifest.input_snapshot || {}).package_profile !== REQUIRED_PACKAGE_PROFILE) fail('Prepared bundle package profile must be ' + REQUIRED_PACKAGE_PROFILE + '.');
  const directions = requireObject(inputBundle.directions, 'bundles[].directions');
  const directionKeys = Object.keys(directions).sort();
  if (directionKeys.join(',') !== REQUIRED_DIRECTIONS.join(',')) fail('Each finalizer bundle must supply exactly a, b, and c direction inputs.');
  const baseDna = readJson(join(suppliedBundle, 'build-dna.json'), 'Prepared Build DNA');
  if (!baseDna.stages.some(function (stage) { return stage.stage_id === 'preview-art' && stage.result && stage.result.status === 'gated'; })) fail('Prepared Build DNA must still contain the declared preview-art gate.');
  const bundleRelative = repoRelative(suppliedBundle);
  const directionsPlan = REQUIRED_DIRECTIONS.map(function (direction) {
    const supplied = requireObject(directions[direction], 'bundles[].directions.' + direction);
    const image = resolve(cleanText(supplied.image, 'hero image for direction ' + direction, 4000));
    const receiptPath = resolve(cleanText(supplied.receipt, 'provider receipt for direction ' + direction, 4000));
    requireExistingFile(image, 'Hero image for direction ' + direction);
    const promptPath = join(suppliedBundle, direction, 'gemini-flash-lite-image-prompt.txt');
    requireExistingFile(promptPath, 'Generated prompt for direction ' + direction);
    const promptHash = fileHash(promptPath);
    const receiptResultId = cleanText(supplied.receipt_result_id, 'receipt_result_id for direction ' + direction, 240);
    const receiptInfo = normalizeReceipt(receiptPath, image, promptHash, receiptResultId);
    const webp = normalizeWebp(image, tempRoot);
    const designPath = join(suppliedBundle, direction, 'design-dna.json');
    const design = readJson(designPath, 'Direction DNA');
    if (!design.visual_asset || design.visual_asset.status !== 'planned_not_executed') fail('Direction ' + direction + ' is not an unfinalized prepared proof.');
    const directionName = cleanText(design.direction_name, 'Direction ' + direction + ' name', 200);
    const htmlPath = join(suppliedBundle, direction, 'index.html');
    const html = injectHero(readFileSync(htmlPath, 'utf8'), directionName);
    const asset = assetDescriptor(direction, webp);
    const localReceipt = receiptInfo.normalized;
    const localReceiptText = json(localReceipt);
    const localReceiptSha = sha256(Buffer.from(localReceiptText, 'utf8'));
    const pageQa = pageChecks(html, webp, direction);
    return {
      direction,
      directionName,
      image,
      receiptPath,
      receipt: localReceipt,
      sourceReceiptSha: receiptInfo.sourceReceiptSha,
      localReceiptText,
      localReceiptSha,
      sourceSha: receiptInfo.sourceSha,
      sourceMime: receiptInfo.sourceMime,
      sourceSize: receiptInfo.sourceSize,
      webp,
      asset,
      html,
      htmlPath,
      designPath,
      design,
      promptPath,
      pageQa,
    };
  });
  return {
    root: suppliedBundle,
    bundleRelative,
    cohortBundle,
    manifest,
    baseDna,
    directions: directionsPlan,
    inputHash,
  };
}

function filesFor(bundle) {
  const files = [
    { role: 'redacted-intake', path: join(bundle.root, 'intake-redacted.json') },
    { role: 'research-evidence', path: join(bundle.root, 'research.json') },
    { role: 'image-prompt-manifest', path: join(bundle.root, 'image-prompts.json') },
    { role: 'promotion-manifest', path: join(bundle.root, 'manifest.json') },
    { role: 'static-qa-report', path: join(bundle.root, 'quality-report.json') },
    { role: 'promotion-readiness', path: join(bundle.root, 'promotion-readiness.json') },
    { role: 'owner-review-hub', path: join(bundle.root, 'index.html') },
    { role: 'run-report', path: join(bundle.root, 'run-report.md') },
    { role: 'local-finalization-report', path: join(bundle.root, 'finalization-report.json') },
  ];
  bundle.directions.forEach(function (item) {
    files.push({ role: 'proof-page-' + item.direction, path: item.htmlPath });
    files.push({ role: 'proof-thumbnail-' + item.direction, path: join(bundle.root, item.direction, 'thumbnail.png') });
    files.push({ role: 'direction-dna-' + item.direction, path: item.designPath });
    files.push({ role: 'image-prompt-' + item.direction, path: item.promptPath });
    files.push({ role: 'proof-asset-manifest-' + item.direction, path: join(bundle.root, item.direction, 'assets.json'), rights_status: 'provider-output-pending-rights-review' });
    files.push({ role: 'provider-receipt-' + item.direction, path: join(bundle.root, item.direction, 'gemini-provider-receipt.json'), rights_status: 'provider-receipt' });
    files.push({ role: 'proof-asset-' + item.direction, path: join(bundle.root, item.direction, 'assets', 'hero.webp'), rights_status: 'provider-output-pending-rights-review' });
  });
  files.forEach(function (file) { requireExistingFile(file.path, 'Final Build DNA artifact'); });
  return files;
}

function applyBundle(bundle, finalization, revision) {
  const manifest = { ...bundle.manifest };
  manifest.source_lane = REQUIRED_SOURCE_LANE;
  manifest.package_profile = REQUIRED_PACKAGE_PROFILE;
  manifest.proof_asset_contract = ASSET_SCHEMA;
  manifest.input_snapshot = { ...(manifest.input_snapshot || {}), direction_ids: REQUIRED_DIRECTIONS, package_profile: REQUIRED_PACKAGE_PROFILE };
  manifest.prompt_snapshot = 'Exact receipt-validated Gemini Flash Lite prompts and portable linked hero assets are present. Local finalization did not promote, publish, or send customer email.';
  manifest.proof_assets = {
    schema: ASSET_SCHEMA,
    asset_storage: 'signed-proof-assets-relative-path-v1',
    variants: bundle.directions.map(function (item) {
      return { direction_id: item.direction, assets: [{ asset_id: item.asset.asset_id, relative_path: item.asset.relative_path, media_type: item.asset.media_type, sha256: item.asset.sha256, size_bytes: item.asset.size_bytes, artifact_path: item.asset.artifact_path }] };
    }),
  };
  const prompts = readJson(join(bundle.root, 'image-prompts.json'), 'Image prompt manifest');
  prompts.execution_status = 'provider_receipt_validated';
  prompts.asset_contract = ASSET_SCHEMA;
  prompts.prompts = prompts.prompts.map(function (entry) {
    const item = bundle.directions.find(function (candidate) { return candidate.direction === entry.direction_id; });
    if (!item) fail('Image prompt manifest has an unexpected direction.');
    return {
      ...entry,
      execution_status: 'provider_receipt_validated',
      output_path: item.asset.artifact_path,
      cost_status: item.receipt.cost.status,
      provider_receipt_artifact: item.direction + '/gemini-provider-receipt.json',
      provider_receipt_sha256: item.localReceiptSha,
      provider_receipt_source_sha256: item.sourceReceiptSha,
      asset: item.asset,
    };
  });
  const readiness = readJson(join(bundle.root, 'promotion-readiness.json'), 'Promotion readiness');
  readiness.asset_contract = ASSET_SCHEMA;
  readiness.current_artwork = 'Receipt-backed Gemini Flash Lite hero images normalized locally to linked WebP assets; not yet browser-reviewed, independently reviewed, imported, published, or sent.';
  readiness.customer_delivery_ready = false;
  readiness.required_before_customer_delivery = [
    'Playwright desktop and 390px browser QA with screenshots for the receipt-backed hero assets',
    'Independent visual and rights review plus any required repair',
    'Canonical Drupal campaign/prospect/job mapping, signed-asset import, Build DNA registration, owner approval, and transactional outbox record',
  ];
  readiness.forbidden_actions_performed = [];
  const pages = {};
  bundle.directions.forEach(function (item) {
    const assetDirectory = join(bundle.root, item.direction, 'assets');
    if (existsSync(assetDirectory)) fail('Refusing to overwrite an existing hero asset directory for direction ' + item.direction + '.');
    if (existsSync(join(bundle.root, item.direction, 'assets.json'))) fail('Refusing to overwrite an existing asset manifest for direction ' + item.direction + '.');
    mkdirSync(assetDirectory, { recursive: true });
    writeFileSync(join(assetDirectory, 'hero.webp'), item.webp);
    writeJson(join(bundle.root, item.direction, 'assets.json'), [{ asset_id: item.asset.asset_id, relative_path: item.asset.relative_path, media_type: item.asset.media_type, sha256: item.asset.sha256, size_bytes: item.asset.size_bytes, artifact_path: item.asset.artifact_path }]);
    writeFileSync(join(bundle.root, item.direction, 'gemini-provider-receipt.json'), item.localReceiptText);
    writeFileSync(item.htmlPath, item.html);
    const design = {
      ...item.design,
      asset_manifest: [{ asset_id: item.asset.asset_id, relative_path: item.asset.relative_path, media_type: item.asset.media_type, sha256: item.asset.sha256, size_bytes: item.asset.size_bytes, artifact_path: item.asset.artifact_path }],
      visual_asset: {
        provider_route: item.receipt.api,
        provider: item.receipt.provider,
        model: item.receipt.model,
        status: 'provider_receipt_validated',
        prompt_artifact: item.direction + '/gemini-flash-lite-image-prompt.txt',
        prompt_sha256: item.receipt.result.prompt_sha256,
        source_image_sha256: item.sourceSha,
        source_image_bytes: item.sourceSize,
        source_image_media_type: item.sourceMime,
        provider_result_id: item.receipt.result.id,
        provider_receipt_artifact: item.direction + '/gemini-provider-receipt.json',
        provider_receipt_sha256: item.localReceiptSha,
        provider_receipt_source_sha256: item.sourceReceiptSha,
        asset_manifest: [item.asset],
      },
    };
    writeJson(item.designPath, design);
    pages[item.direction] = item.pageQa;
  });
  const quality = {
    schema: 'famtastic.beauty-proof-static-qa.v1',
    classification: 'receipt-backed-local-finalization',
    static_status: 'passed',
    browser_status: 'not_run',
    independent_visual_review_status: 'not_run',
    customer_delivery_status: 'blocked',
    asset_contract: ASSET_SCHEMA,
    checks: { exact_three_directions: true, all_provider_receipts_validated: true, all_linked_hero_assets_valid: true, all_static_checks_pass: true },
    pages,
    open_gates: [
      'Desktop and 390px Playwright screenshots after receipt-backed hero injection',
      'Independent visual and rights review plus any required repair',
      'Drupal Build DNA registration, signed-asset import, owner review, proof publication, and transactional outbox',
    ],
  };
  const finalizationReport = {
    schema: FINALIZATION_SCHEMA,
    classification: 'receipt-backed-local-finalization',
    finalized_at: finalization.finalized_at,
    finalizer_input_sha256: finalization.input_sha256,
    source_lane: REQUIRED_SOURCE_LANE,
    package_profile: REQUIRED_PACKAGE_PROFILE,
    asset_contract: ASSET_SCHEMA,
    no_external_actions: ['no Gemini API call', 'no Drupal write', 'no proof promotion', 'no publication', 'no email send'],
    bundle: bundle.bundleRelative,
    directions: bundle.directions.map(function (item) {
      return {
        direction_id: item.direction,
        asset: item.asset,
        source_image_sha256: item.sourceSha,
        source_image_media_type: item.sourceMime,
        source_image_bytes: item.sourceSize,
        provider_result_id: item.receipt.result.id,
        provider_receipt_source_sha256: item.sourceReceiptSha,
        normalized_receipt_sha256: item.localReceiptSha,
        prompt_sha256: item.receipt.result.prompt_sha256,
      };
    }),
  };
  const runReport = '# Receipt-backed local beauty proof finalization\n\n- Package: ' + REQUIRED_PACKAGE_PROFILE + '\n- Source lane: ' + REQUIRED_SOURCE_LANE + '\n- Directions: Safe (a), Medium FAMtastic (b), Ultra FAMtastic (c)\n- Hero route: externally supplied Gemini Flash Lite outputs validated against exact prompt hashes and local normalized `assets/hero.webp` files\n- Status: local only; no Gemini call, Drupal write, promotion, publication, or email.\n- Open gates: browser QA, independent visual/rights review, canonical signed-asset import, owner approval, and transactional outbox.\n';
  writeJson(join(bundle.root, 'manifest.json'), manifest);
  writeJson(join(bundle.root, 'image-prompts.json'), prompts);
  writeJson(join(bundle.root, 'promotion-readiness.json'), readiness);
  writeJson(join(bundle.root, 'quality-report.json'), quality);
  writeJson(join(bundle.root, 'finalization-report.json'), finalizationReport);
  writeFileSync(join(bundle.root, 'run-report.md'), runReport);
  const dna = buildDna(bundle, bundle.baseDna, finalization, filesFor(bundle), revision);
  writeJson(join(bundle.root, 'build-dna.json'), dna);
  return validateDna(bundle);
}

function main() {
  const args = options(process.argv.slice(2));
  const inputPath = resolve(args.input);
  const loaded = loadInput(inputPath);
  cwebpAvailable();
  const tempRoot = mkdtempSync(join(tmpdir(), 'famtastic-proof-finalizer-'));
  try {
    const mapped = new Map();
    loaded.value.bundles.forEach(function (item) {
      const root = resolveInsideRepo(item.bundle, 'bundles[].bundle');
      if (mapped.has(root)) fail('Finalizer input contains a duplicate bundle mapping.');
      mapped.set(root, item);
    });
    const bundles = loaded.cohort.bundles.map(function (cohortBundle) {
      const root = resolveInsideRepo(cohortBundle.bundle, 'cohort bundle path');
      const supplied = mapped.get(root);
      if (!supplied) fail('Finalizer input is missing a cohort bundle mapping: ' + cohortBundle.bundle);
      return plannedBundle(supplied, cohortBundle, loaded.cohort.campaign_id, loaded.inputHash, tempRoot);
    });
    const finalization = { finalized_at: new Date().toISOString(), input_sha256: loaded.inputHash };
    if (args.dryRun) {
      console.log('PASS: dry-run validated ' + bundles.length + ' verified_cold bundle(s) with exactly a, b, c receipt-backed hero assets.');
      bundles.forEach(function (bundle) {
        console.log('PLAN: ' + bundle.bundleRelative + ' → ' + bundle.directions.map(function (item) { return item.direction + ':assets/hero.webp(' + item.webp.length + ' bytes)'; }).join(', '));
      });
      console.log('DRY RUN: no cohort file, Drupal record, proof, provider call, promotion, or email was changed.');
      return;
    }
    const revision = gitRevision();
    bundles.forEach(function (bundle) {
      const validation = applyBundle(bundle, finalization, revision);
      console.log('PASS: finalized ' + bundle.bundleRelative + ' with linked receipt-backed WebP hero assets.');
      validation.forEach(function (line) { console.log(line); });
    });
    console.log('STATUS: local finalization only; customer delivery remains blocked by recorded gates.');
  } finally {
    rmSync(tempRoot, { recursive: true, force: true });
  }
}

try {
  main();
} catch (error) {
  console.error('FAIL: ' + error.message);
  process.exit(1);
}
