#!/usr/bin/env node
/**
 * generate-plates.mjs — Tier-2 (Gemini Flash Lite) plate/texture generator.
 *
 * v2 (2026-09-05). Extends the v1 script (which proved the model id, endpoint,
 * keychain credential lookup, cost constant and per-prompt aspect ratio) with
 * everything the full campaign plate + texture library needs:
 *
 *   - reads either library shape: the v1 flat `prompts[]` array, or the v2
 *     `topics[].variants[]` shape used by prompt-library.json and
 *     ../textures/texture-library.json
 *   - assembles the prompt at run time from ordered segments, in the order the
 *     OpenAI image-prompting cookbook prescribes (use case -> scene/background
 *     -> subject -> key details -> camera/light -> palette -> reserved negative
 *     space -> exclusions -> preserve list). The exact assembled string is
 *     recorded in the receipt, so the audit trail is the literal text sent.
 *   - `--library` / `--out-root` / `--receipt` so the same primitive drives the
 *     plate library and the texture library instead of forking a second script
 *   - `--campaign`, `--status`, `--palette` selectors, `--concurrency`,
 *     `--retries`, `--dry-run`
 *   - measures real pixel dimensions of every output (JPEG SOFn / PNG IHDR
 *     parsed from the returned bytes) rather than trusting the requested label
 *
 * Usage:
 *   node generate-plates.mjs --dry-run
 *   node generate-plates.mjs --campaign platform-dependency --max-cost-usd 1.00
 *   node generate-plates.mjs --library ../textures/texture-library.json \
 *        --out-root ../textures --receipt ../textures/generation-receipt.json \
 *        --max-cost-usd 1.50
 *
 * Credential: macOS Keychain, service "FAMtastic.Gemini.Image",
 * account "famtastic-gemini-image-worker" — never read from env vars.
 */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const MODEL = 'gemini-3.1-flash-lite-image';
const ENDPOINT = `https://generativelanguage.googleapis.com/v1/models/${MODEL}:generateContent`;
const COST_PER_IMAGE_1K_USD = 0.0336;
const SERVICE = 'FAMtastic.Gemini.Image';
const ACCOUNT = 'famtastic-gemini-image-worker';

const sha256 = (bytes) => createHash('sha256').update(bytes).digest('hex');

function apiKey() {
  return execFileSync('/usr/bin/security', [
    'find-generic-password', '-s', SERVICE, '-a', ACCOUNT, '-w',
  ], { encoding: 'utf8' }).trim();
}

function parseArgs(argv) {
  const out = {
    ids: null, campaigns: null, palettes: null, statuses: null,
    library: 'prompt-library.json', outRoot: ROOT, receipt: null,
    maxCostUsd: 1.0, concurrency: 4, retries: 1, dryRun: false, force: false,
  };
  const list = (s) => s.split(',').map((x) => x.trim()).filter(Boolean);
  for (let i = 2; i < argv.length; i += 1) {
    const a = argv[i];
    if (a === '--ids') out.ids = list(argv[++i]);
    else if (a === '--campaign') out.campaigns = list(argv[++i]);
    else if (a === '--palette') out.palettes = list(argv[++i]);
    else if (a === '--status') out.statuses = list(argv[++i]);
    else if (a === '--library') out.library = argv[++i];
    else if (a === '--out-root') out.outRoot = path.resolve(ROOT, argv[++i]);
    else if (a === '--receipt') out.receipt = argv[++i];
    else if (a === '--max-cost-usd') out.maxCostUsd = Number(argv[++i]);
    else if (a === '--concurrency') out.concurrency = Math.max(1, Number(argv[++i]));
    else if (a === '--retries') out.retries = Math.max(0, Number(argv[++i]));
    else if (a === '--dry-run') out.dryRun = true;
    else if (a === '--force') out.force = true;
    else throw new Error(`Unknown argument: ${a}`);
  }
  return out;
}

const extensionFor = (mime) => (mime === 'image/png' ? 'png' : mime === 'image/webp' ? 'webp' : 'jpg');

/**
 * Real measured pixel dimensions from the returned bytes. The provider's "1K"
 * label does NOT mean 1024 on a side (16:9 comes back 1376x768), so the receipt
 * records what actually arrived, not what was asked for.
 */
function imageDimensions(buf) {
  if (buf.length > 24 && buf.readUInt32BE(0) === 0x89504e47) {
    return { width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
  }
  if (buf.length > 4 && buf[0] === 0xff && buf[1] === 0xd8) {
    let i = 2;
    while (i + 9 < buf.length) {
      if (buf[i] !== 0xff) { i += 1; continue; }
      const marker = buf[i + 1];
      if (marker === 0xd8 || marker === 0x01 || (marker >= 0xd0 && marker <= 0xd7)) { i += 2; continue; }
      const len = buf.readUInt16BE(i + 2);
      const isSOF = (marker >= 0xc0 && marker <= 0xcf)
        && marker !== 0xc4 && marker !== 0xc8 && marker !== 0xcc;
      if (isSOF) return { height: buf.readUInt16BE(i + 5), width: buf.readUInt16BE(i + 7) };
      i += 2 + len;
    }
  }
  return { width: null, height: null };
}

/**
 * Flatten either library shape into one list of runnable items, resolving the
 * topic-level fields (claim, object, palette, campaign) onto every variant.
 */
function flatten(library) {
  if (Array.isArray(library.prompts)) return library.prompts;
  const items = [];
  for (const topic of library.topics || []) {
    for (const v of topic.variants || []) {
      items.push({
        ...topic, variants: undefined, ...v,
        topic_id: topic.topic_id,
        topic_title: topic.title,
        campaign: v.campaign || topic.campaign,
        palette: v.palette || topic.palette,
        claim: v.claim || topic.claim,
        object: v.object || topic.object,
        blog_post_slug: v.blog_post_slug || topic.blog_post_slug,
      });
    }
  }
  return items;
}

/**
 * Cookbook prompt order: use case -> scene/background -> subject -> key details
 * -> camera and light -> palette -> reserved negative space -> exclusions ->
 * preserve list. Short labelled segments, not one paragraph.
 */
function assemblePrompt(item, library) {
  const shared = library.shared || {};
  const palette = (library.palettes || {})[item.palette];
  const negSpace = (shared.negative_space_clauses || {})[item.negative_space];
  const seg = [];
  const push = (label, text) => { if (text) seg.push(`${label}: ${text}`); };

  push('Intended use', item.use_case || shared.use_case_clause);
  push('Scene / background', item.scene);
  push('Subject', item.subject);
  push('Key details', item.details);
  push('Camera and light', item.camera);
  if (shared.photography_clause) seg.push(shared.photography_clause);
  if (palette) push('Colour world (mandatory)', palette.prompt_clause);
  if (negSpace) seg.push(negSpace);
  if (shared.no_text_clause) seg.push(shared.no_text_clause);
  if (shared.exclusion_clause) seg.push(shared.exclusion_clause);
  if (shared.preserve_clause) seg.push(shared.preserve_clause);
  return seg.join('\n');
}

async function callOnce(item, prompt, key) {
  const started = Date.now();
  const startedIso = new Date(started).toISOString();
  const body = {
    contents: [{ parts: [{ text: prompt }] }],
    generationConfig: {
      responseModalities: ['IMAGE'],
      imageConfig: { aspectRatio: item.aspect_ratio, imageSize: '1K' },
    },
  };
  let response; let payload; let httpStatus = null;
  try {
    response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-goog-api-key': key },
      body: JSON.stringify(body),
    });
    httpStatus = response.status;
    payload = await response.json().catch(() => ({}));
  } catch (networkError) {
    return { ok: false, startedIso, durationMs: Date.now() - started, httpStatus: null, error: `network_error: ${networkError.message}` };
  }
  const durationMs = Date.now() - started;
  if (!response.ok) {
    return { ok: false, startedIso, durationMs, httpStatus, error: JSON.stringify(payload).slice(0, 1200) };
  }
  const part = (payload.candidates || [])
    .flatMap((c) => (c.content && c.content.parts) || [])
    .find((p) => p.inlineData && p.inlineData.data);
  if (!part) {
    const finish = (payload.candidates || []).map((c) => c.finishReason).filter(Boolean).join(',');
    return {
      ok: false, startedIso, durationMs, httpStatus,
      blocked: finish.includes('SAFETY') || finish.includes('PROHIBITED'),
      error: `no_inline_image_data finishReason=${finish || 'none'}: ${JSON.stringify(payload).slice(0, 900)}`,
    };
  }
  return {
    ok: true, startedIso, durationMs, httpStatus,
    bytes: Buffer.from(part.inlineData.data, 'base64'),
    mimeType: part.inlineData.mimeType || 'image/jpeg',
    usage: payload.usageMetadata || null,
  };
}

async function generateOne(item, library, key, opts) {
  const prompt = assemblePrompt(item, library);
  const attempts = [];
  for (let attempt = 0; attempt <= opts.retries; attempt += 1) {
    // eslint-disable-next-line no-await-in-loop
    const r = await callOnce(item, prompt, key);
    attempts.push({ attempt: attempt + 1, http_status: r.httpStatus, ok: r.ok, blocked: r.blocked || false, error: r.ok ? null : r.error, duration_ms: r.durationMs });
    if (r.ok) {
      const ext = extensionFor(r.mimeType);
      const rel = (item.output_file || `${item.id}.${ext}`).replace(/\.(png|jpe?g|webp)$/i, `.${ext}`);
      const abs = path.join(opts.outRoot, rel);
      // eslint-disable-next-line no-await-in-loop
      await mkdir(path.dirname(abs), { recursive: true });
      // eslint-disable-next-line no-await-in-loop
      await writeFile(abs, r.bytes);
      const dims = imageDimensions(r.bytes);
      return {
        id: item.id,
        topic_id: item.topic_id || null,
        campaign: item.campaign || null,
        palette: item.palette || null,
        aspect_ratio: item.aspect_ratio,
        surface: item.surface || null,
        tier: item.tier || '2 cheap-multiplier',
        blog_post_slug: item.blog_post_slug || null,
        started_at: r.startedIso,
        completed_at: new Date().toISOString(),
        duration_ms: r.durationMs,
        http_status: r.httpStatus,
        ok: true,
        attempts,
        output_file: rel,
        mime_type: r.mimeType,
        bytes: r.bytes.length,
        dimensions: dims,
        sha256: sha256(r.bytes),
        cost_usd: COST_PER_IMAGE_1K_USD,
        usage_metadata: r.usage,
        prompt_sent: prompt,
      };
    }
    if (r.blocked) break; // a safety block will not clear on a bare retry
    if (attempt < opts.retries) {
      // eslint-disable-next-line no-await-in-loop
      await new Promise((res) => { setTimeout(res, 1500 * (attempt + 1)); });
    }
  }
  const last = attempts[attempts.length - 1];
  return {
    id: item.id,
    topic_id: item.topic_id || null,
    campaign: item.campaign || null,
    palette: item.palette || null,
    aspect_ratio: item.aspect_ratio,
    surface: item.surface || null,
    started_at: attempts[0] ? new Date().toISOString() : null,
    completed_at: new Date().toISOString(),
    http_status: last.http_status,
    ok: false,
    blocked: last.blocked,
    attempts,
    error: last.error,
    cost_usd: 0,
    prompt_sent: prompt,
  };
}

async function runPool(items, worker, concurrency) {
  const results = new Array(items.length);
  let next = 0;
  const runners = Array.from({ length: Math.min(concurrency, items.length) }, async () => {
    for (;;) {
      const i = next; next += 1;
      if (i >= items.length) return;
      results[i] = await worker(items[i], i);
    }
  });
  await Promise.all(runners);
  return results;
}

async function main() {
  const args = parseArgs(process.argv);
  const libPath = path.resolve(ROOT, args.library);
  const library = JSON.parse(await readFile(libPath, 'utf8'));
  const receiptPath = path.resolve(ROOT, args.receipt || path.join(path.dirname(libPath), 'generation-receipt.json'));

  let items = flatten(library);
  if (args.ids) { const s = new Set(args.ids); items = items.filter((p) => s.has(p.id)); }
  if (args.campaigns) { const s = new Set(args.campaigns); items = items.filter((p) => s.has(p.campaign)); }
  if (args.palettes) { const s = new Set(args.palettes); items = items.filter((p) => s.has(p.palette)); }
  if (args.statuses) { const s = new Set(args.statuses); items = items.filter((p) => s.has(p.status)); }
  else if (!args.ids && !args.force) items = items.filter((p) => p.status !== 'generated' && p.status !== 'procedural');

  if (items.length === 0) { process.stderr.write('No matching library entries.\n'); process.exitCode = 1; return; }

  if (args.dryRun) {
    for (const item of items) {
      process.stdout.write(`\n===== ${item.id}  [${item.palette}] ${item.aspect_ratio} -> ${item.output_file}\n`);
      process.stdout.write(`${assemblePrompt(item, library)}\n`);
    }
    process.stdout.write(`\n${items.length} entries. Estimated cost if run: $${(items.length * COST_PER_IMAGE_1K_USD).toFixed(4)}\n`);
    return;
  }

  const expectedCost = Number((items.length * COST_PER_IMAGE_1K_USD).toFixed(4));
  if (expectedCost > args.maxCostUsd) {
    process.stderr.write(`Refusing: expected cost $${expectedCost} for ${items.length} images exceeds ceiling $${args.maxCostUsd}\n`);
    process.exitCode = 1; return;
  }

  const key = apiKey();
  process.stdout.write(`Generating ${items.length} images (concurrency ${args.concurrency}), ceiling $${args.maxCostUsd}, expected $${expectedCost}\n`);
  let done = 0;
  const results = await runPool(items, async (item) => {
    const r = await generateOne(item, library, key, { retries: args.retries, outRoot: args.outRoot });
    done += 1;
    process.stdout.write(r.ok
      ? `[${done}/${items.length}] OK   ${r.id}  ${r.dimensions.width}x${r.dimensions.height}  ${r.bytes}B  ${r.duration_ms}ms\n`
      : `[${done}/${items.length}] FAIL ${r.id}  http=${r.http_status} blocked=${r.blocked}  ${String(r.error).slice(0, 220)}\n`);
    return r;
  }, args.concurrency);

  // Merge with any prior receipt rather than clobbering it (v1 bug: a 1-image
  // retry run erased a 7-image run's usage_metadata before it was read back).
  let priorResults = [];
  try {
    const prior = JSON.parse(await readFile(receiptPath, 'utf8'));
    if (Array.isArray(prior.results)) priorResults = prior.results;
  } catch { /* no prior receipt */ }
  const byId = new Map(priorResults.map((r) => [r.id, r]));
  for (const r of results) byId.set(r.id, r);
  const merged = Array.from(byId.values());
  const succeeded = merged.filter((r) => r.ok);

  const runSucceeded = results.filter((r) => r.ok).length;
  const receipt = {
    schema: 'famtastic.tier2-plate-generation-receipt.v2',
    generated_at: new Date().toISOString(),
    library: path.relative(ROOT, libPath),
    provider: 'google-gemini-api',
    api: 'generateContent',
    model: MODEL,
    endpoint: ENDPOINT,
    credential_source: `macos_keychain:${SERVICE}:${ACCOUNT}`,
    cost_per_image_usd_1k: COST_PER_IMAGE_1K_USD,
    cost_note: "Published per-image rate for one Gemini 3.1 Flash Lite Image 1K output. The Gemini Developer API generateContent response carries no per-call invoiced dollar amount, so measured spend is (successful images x published per-image rate) — the same basis every prior Gemini receipt in this repo uses. Token usage per call is recorded in usage_metadata as corroborating evidence. A blocked/safety-filtered image is not charged (confirmed by the provider's own error message) and is excluded from total_cost_usd.",
    last_run: {
      requested: items.length,
      succeeded: runSucceeded,
      failed: items.length - runSucceeded,
      cost_usd: Number((runSucceeded * COST_PER_IMAGE_1K_USD).toFixed(4)),
      concurrency: args.concurrency,
    },
    requested_count: merged.length,
    succeeded_count: succeeded.length,
    failed_count: merged.length - succeeded.length,
    total_cost_usd: Number((succeeded.length * COST_PER_IMAGE_1K_USD).toFixed(4)),
    results: merged,
  };
  await mkdir(path.dirname(receiptPath), { recursive: true });
  await writeFile(receiptPath, `${JSON.stringify(receipt, null, 2)}\n`);
  process.stdout.write(`\nDone. ${runSucceeded}/${items.length} succeeded this run ($${receipt.last_run.cost_usd}). Library total to date: ${succeeded.length} images, $${receipt.total_cost_usd}\n`);
  if (runSucceeded < items.length) process.exitCode = 1;
}

main().catch((error) => {
  process.stderr.write(`FATAL: ${error.message}\n`);
  process.exitCode = 1;
});
