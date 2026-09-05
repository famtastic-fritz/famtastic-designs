#!/usr/bin/env node
/**
 * generate-plates.mjs — Tier-2 (Gemini Flash Lite) plate generator for the
 * prompt library at marketing/creative/plates/prompt-library.json.
 *
 * Adapted from the proven production worker
 * `website-delivery-swarm/gemini_flash_lite_image_worker.mjs` (model id,
 * endpoint, keychain credential lookup, cost constant) and from
 * `marketing/campaigns/and-if-it-is-rattler-lifers/experiments/lite-image-story-20260820/generate-supporting-images.mjs`
 * (per-asset aspect ratio instead of one fixed ratio for the whole run).
 * This script adds per-prompt aspect ratio support because the plate
 * library needs 16:9, 1:1, and 9:16 outputs in the same run, which the
 * production worker's fixed 16:9 imageConfig does not support.
 *
 * Usage:
 *   node generate-plates.mjs --ids p-hero-blog,a1-hero-blog,... --max-cost-usd 1.00
 *
 * Credential: macOS Keychain, service "FAMtastic.Gemini.Image",
 * account "famtastic-gemini-image-worker" — never read from env vars.
 */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const MODEL = 'gemini-3.1-flash-lite-image';
const ENDPOINT = `https://generativelanguage.googleapis.com/v1/models/${MODEL}:generateContent`;
const COST_PER_IMAGE_1K_USD = 0.0336;
const SERVICE = 'FAMtastic.Gemini.Image';
const ACCOUNT = 'famtastic-gemini-image-worker';

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

function apiKey() {
  return execFileSync('/usr/bin/security', [
    'find-generic-password', '-s', SERVICE, '-a', ACCOUNT, '-w',
  ], { encoding: 'utf8' }).trim();
}

function parseArgs(argv) {
  const out = { ids: null, maxCostUsd: 1.0 };
  for (let i = 2; i < argv.length; i += 1) {
    if (argv[i] === '--ids') out.ids = argv[++i].split(',').map((s) => s.trim()).filter(Boolean);
    else if (argv[i] === '--max-cost-usd') out.maxCostUsd = Number(argv[++i]);
  }
  return out;
}

const extensionFor = (mime) => (mime === 'image/png' ? 'png' : mime === 'image/webp' ? 'webp' : 'jpg');

async function generateOne(item, key) {
  const started = Date.now();
  const startedIso = new Date(started).toISOString();
  const requestBody = {
    contents: [{ parts: [{ text: item.prompt }] }],
    generationConfig: {
      responseModalities: ['IMAGE'],
      imageConfig: { aspectRatio: item.aspect_ratio, imageSize: '1K' },
    },
  };
  let response;
  let payload;
  let httpStatus = null;
  try {
    response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-goog-api-key': key },
      body: JSON.stringify(requestBody),
    });
    httpStatus = response.status;
    payload = await response.json().catch(() => ({}));
  } catch (networkError) {
    return {
      id: item.id,
      aspect_ratio: item.aspect_ratio,
      started_at: startedIso,
      completed_at: new Date().toISOString(),
      duration_ms: Date.now() - started,
      http_status: null,
      ok: false,
      error: 'network_error: ' + networkError.message,
    };
  }
  const durationMs = Date.now() - started;
  if (!response.ok) {
    return {
      id: item.id,
      aspect_ratio: item.aspect_ratio,
      started_at: startedIso,
      completed_at: new Date().toISOString(),
      duration_ms: durationMs,
      http_status: httpStatus,
      ok: false,
      error: JSON.stringify(payload).slice(0, 2000),
    };
  }
  const imagePart = (payload.candidates || [])
    .flatMap((c) => (c.content && c.content.parts) || [])
    .find((p) => p.inlineData && p.inlineData.data);
  if (!imagePart) {
    return {
      id: item.id,
      aspect_ratio: item.aspect_ratio,
      started_at: startedIso,
      completed_at: new Date().toISOString(),
      duration_ms: durationMs,
      http_status: httpStatus,
      ok: false,
      error: 'no_inline_image_data: ' + JSON.stringify(payload).slice(0, 2000),
    };
  }
  const bytes = Buffer.from(imagePart.inlineData.data, 'base64');
  const mimeType = imagePart.inlineData.mimeType || 'image/jpeg';
  const ext = extensionFor(mimeType);
  const requestedName = item.output_file || `${item.id}.${ext}`;
  const outputName = requestedName.replace(/\.(png|jpe?g|webp)$/i, `.${ext}`);
  const outputPath = path.join(ROOT, outputName);
  await writeFile(outputPath, bytes);
  return {
    id: item.id,
    aspect_ratio: item.aspect_ratio,
    surface: item.surface,
    tier: item.tier,
    blog_post_slug: item.blog_post_slug,
    started_at: startedIso,
    completed_at: new Date().toISOString(),
    duration_ms: durationMs,
    http_status: httpStatus,
    ok: true,
    output_file: outputName,
    mime_type: mimeType,
    bytes: bytes.length,
    sha256: sha256(bytes),
    cost_usd: COST_PER_IMAGE_1K_USD,
    usage_metadata: payload.usageMetadata || null,
  };
}

async function main() {
  const args = parseArgs(process.argv);
  const library = JSON.parse(await readFile(path.join(ROOT, 'prompt-library.json'), 'utf8'));
  const wanted = args.ids ? new Set(args.ids) : new Set(
    library.prompts.filter((p) => p.status === 'generated').map((p) => p.id),
  );
  const items = library.prompts.filter((p) => wanted.has(p.id));
  if (items.length === 0) {
    process.stderr.write('No matching prompt ids found.\n');
    process.exitCode = 1;
    return;
  }
  const expectedCost = Number((items.length * COST_PER_IMAGE_1K_USD).toFixed(4));
  if (expectedCost > args.maxCostUsd) {
    process.stderr.write(`Refusing: expected cost $${expectedCost} exceeds ceiling $${args.maxCostUsd}\n`);
    process.exitCode = 1;
    return;
  }
  const key = apiKey();
  const results = [];
  for (const item of items) {
    process.stdout.write(`Generating ${item.id} (${item.aspect_ratio})...\n`);
    const result = await generateOne(item, key);
    results.push(result);
    process.stdout.write(
      result.ok
        ? `  OK  ${result.output_file}  ${result.bytes}B  ${result.duration_ms}ms  $${result.cost_usd}\n`
        : `  FAIL  http=${result.http_status}  ${result.error}\n`,
    );
  }
  // Merge with any prior receipt rather than clobbering it: a later run for a
  // subset of ids (e.g. retrying one blocked/failed prompt) must not erase the
  // measured results of prompts generated in an earlier run. This was a real
  // bug hit while proving this script: a 7-image run's full receipt (with
  // usage_metadata) was overwritten by a follow-up 1-image retry run before
  // being read back. Fixed by merging on `id`, newest result wins per id.
  const receiptPath = path.join(ROOT, 'generation-receipt.json');
  let priorResults = [];
  try {
    const prior = JSON.parse(await readFile(receiptPath, 'utf8'));
    if (Array.isArray(prior.results)) priorResults = prior.results;
  } catch {
    // No prior receipt, or it was unreadable — start fresh.
  }
  const byId = new Map(priorResults.map((r) => [r.id, r]));
  for (const r of results) byId.set(r.id, r);
  const mergedResults = Array.from(byId.values());
  const succeeded = mergedResults.filter((r) => r.ok);
  const receipt = {
    schema: 'famtastic.tier2-plate-generation-receipt.v1',
    generated_at: new Date().toISOString(),
    provider: 'google-gemini-api',
    api: 'generateContent',
    model: MODEL,
    endpoint: ENDPOINT,
    credential_source: `macos_keychain:${SERVICE}:${ACCOUNT}`,
    cost_per_image_usd_1k: COST_PER_IMAGE_1K_USD,
    cost_note: 'Published per-image rate for one Gemini 3.1 Flash Lite Image 1K output. The Gemini Developer API generateContent response does not include a per-call invoiced dollar amount, so measured spend here is (successful images x published per-image rate), which is how every prior receipt in this repo (marketing/campaigns/and-if-it-is-rattler-lifers/evidence/) reports Gemini Flash Lite cost. Token usage per call is recorded in usage_metadata as corroborating evidence. A blocked/safety-filtered image is not charged (confirmed by the provider\'s own error message) and is not counted toward total_cost_usd.',
    requested_count: mergedResults.length,
    succeeded_count: succeeded.length,
    failed_count: mergedResults.length - succeeded.length,
    total_cost_usd: Number((succeeded.length * COST_PER_IMAGE_1K_USD).toFixed(4)),
    results: mergedResults,
  };
  await writeFile(receiptPath, `${JSON.stringify(receipt, null, 2)}\n`);
  process.stdout.write(
    `\nDone. ${succeeded.length}/${items.length} succeeded. Total measured cost: $${receipt.total_cost_usd}\n`,
  );
  if (succeeded.length < items.length) process.exitCode = 1;
}

main().catch((error) => {
  process.stderr.write('FATAL: ' + error.message + '\n');
  process.exitCode = 1;
});
