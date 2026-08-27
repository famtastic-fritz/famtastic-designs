#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, readFile, rename, rm, writeFile } from 'node:fs/promises';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(repositoryRoot, 'frontend/public/showcase/booked-and-branded-pilot');
const defaultPlan = join(publicRoot, 'creative-system.json');
const dataPath = join(publicRoot, 'pilot-data.json');
const model = 'gemini-3.1-flash-lite-image';
const service = 'FAMtastic.Gemini.Image';
const account = 'famtastic-gemini-image-worker';
const imageCostUsd = 0.0336;
const sha256 = value => createHash('sha256').update(value).digest('hex');

function fail(message) {
  throw new Error(message);
}

function parse(argv) {
  const options = { execute: false, preflight: false };
  for (let index = 2; index < argv.length; index += 1) {
    const key = argv[index];
    if (key === '--execute') options.execute = true;
    else if (key === '--preflight') options.preflight = true;
    else if (key === '--help' || key === '-h') options.help = true;
    else if (key.startsWith('--')) {
      const value = argv[++index];
      if (!value || value.startsWith('--')) fail(`Missing value for ${key}.`);
      options[key.slice(2)] = value;
    }
    else fail(`Unexpected argument: ${key}`);
  }
  return options;
}

function apiKey() {
  return execFileSync('/usr/bin/security', [
    'find-generic-password', '-s', service, '-a', account, '-w'
  ], { encoding: 'utf8' }).trim();
}

async function api(url, key, options = {}) {
  const response = await fetch(url, {
    ...options,
    signal: AbortSignal.timeout(60_000),
    headers: { 'x-goog-api-key': key, ...(options.headers || {}) }
  });
  const raw = await response.text();
  let body;
  try { body = JSON.parse(raw); }
  catch { body = { raw_text_sha256: sha256(raw) }; }
  if (!response.ok) fail(`Gemini request failed (${response.status}): ${JSON.stringify(body)}`);
  return { body, raw };
}

function extractImage(response) {
  if (response.output_image?.data) {
    return { data: response.output_image.data, mime: response.output_image.mime_type || 'image/jpeg' };
  }
  for (const step of response.steps || []) {
    for (const block of step.content || []) {
      const candidate = block.image || block;
      const mime = candidate?.mime_type || candidate?.mimeType;
      if (candidate?.data && String(mime || '').startsWith('image/')) return { data: candidate.data, mime };
    }
  }
  return null;
}

function promptFor(plan, business, direction) {
  const archetype = plan.archetypes[direction.id];
  const businessSystem = plan.businesses[business.slug];
  if (!archetype || !businessSystem?.scenes?.[direction.id]) fail(`Missing creative system for ${business.slug}/${direction.id}.`);
  return [
    `Create one wholly new premium 16:9 photographic frame for the fictional ${business.name} concept in ${business.location}. The entire output must be an uninterrupted realistic photograph, not a poster, ad layout, mood board, website, interface, or graphic design.`,
    plan.image_rules.reference_rule,
    `Direction: ${archetype.name}. ${archetype.image_direction}`,
    `Scene: ${businessSystem.scenes[direction.id]}`,
    `Use these motifs only as subtle physical composition cues: ${businessSystem.motifs.join(', ')}. Preserve generous naturally empty photographic space on one side so FAMtastic can place real HTML typography outside the image.`,
    `Palette and material continuity should feel related to the attached reference without copying it. ${plan.image_rules.exclusions}`,
    'ABSOLUTE PHOTO-ONLY RULE: render no letters, words, numbers, logos, symbols, signs, labels, watermarks, captions, graphic overlays, interface panels, charts, badges, appointment data, brand marks, or pseudo-text anywhere in the image. Remove or turn away every readable package, card, notebook, placard, calendar, and screen. If a phone is present, show only its back or a completely dark screen at an oblique angle. Native HTML—not the image—will provide every word and UI element.'
  ].join('\n\n');
}

async function loadJson(path, label) {
  try { return JSON.parse(await readFile(path, 'utf8')); }
  catch (error) { fail(`${label} is not valid JSON: ${error.message}`); }
}

function requirePlan(plan, data) {
  if (plan.schema !== 'famtastic.booked-branded-creative-system.v2') fail('Unsupported creative-system schema.');
  if (plan.image_rules?.model !== model || plan.image_rules?.api !== 'Gemini Developer API Interactions') fail('Creative system declares the wrong provider route.');
  if (!Array.isArray(data.businesses) || data.businesses.length !== 4) fail('Pilot data must contain exactly four businesses.');
  for (const business of data.businesses) {
    if (!plan.businesses?.[business.slug]) fail(`Creative system is missing ${business.slug}.`);
    if (!Array.isArray(business.directions) || business.directions.map(item => item.id).join(',') !== 'a,b,c') fail(`${business.slug} must contain directions a,b,c.`);
  }
}

async function execute(options) {
  const planPath = resolve(options.plan || defaultPlan);
  const output = resolve(options.output || '');
  if (!options.output) fail('--output is required.');
  if (existsSync(output)) fail(`Output directory must not already exist: ${output}`);
  const plan = await loadJson(planPath, 'Creative system');
  const data = await loadJson(dataPath, 'Pilot data');
  requirePlan(plan, data);

  let planned = [];
  for (const business of data.businesses) {
    const referencePath = join(publicRoot, 'assets', business.image);
    const referenceBytes = await readFile(referencePath);
    for (const direction of business.directions) {
      const prompt = promptFor(plan, business, direction);
      planned.push({
        business_slug: business.slug,
        business_name: business.name,
        direction_id: direction.id,
        direction_name: direction.name,
        filename: `${business.slug}-${direction.id}.jpg`,
        prompt,
        prompt_sha256: sha256(prompt),
        reference_path: referencePath,
        reference_repo_path: referencePath.slice(repositoryRoot.length + 1),
        reference_sha256: sha256(referenceBytes),
        reference_bytes: referenceBytes
      });
    }
  }

  if (options.only) {
    const requested = new Set(String(options.only).split(',').map(value => value.trim()).filter(Boolean));
    planned = planned.filter(item => requested.has(`${item.business_slug}/${item.direction_id}`));
    if (!planned.length || planned.length !== requested.size) fail('--only must contain unique known business-slug/direction pairs.');
  }

  const expectedCost = Number((planned.length * imageCostUsd).toFixed(4));
  const ceiling = Number(options['max-cost-usd']);
  if (!Number.isFinite(ceiling) || ceiling < expectedCost) fail(`--max-cost-usd must cover the estimated ${expectedCost.toFixed(4)} USD.`);
  if (ceiling > 1) fail('This pilot worker refuses a ceiling above the owner-authorized USD 1.00 maximum.');

  const key = apiKey();
  const staging = await mkdtemp(join(dirname(output), `.${basename(output)}.staging-`));
  const artifacts = [];
  const startedAt = new Date().toISOString();
  try {
    for (const item of planned) {
      const started = Date.now();
      const response = await api('https://generativelanguage.googleapis.com/v1beta/interactions', key, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          model,
          input: [
            { type: 'image', data: item.reference_bytes.toString('base64'), mime_type: 'image/webp' },
            { type: 'text', text: item.prompt }
          ],
          store: true,
          response_format: { type: 'image', mime_type: 'image/jpeg', aspect_ratio: '16:9', image_size: '1K' }
        })
      });
      const image = extractImage(response.body);
      if (!image?.data) fail(`Gemini returned no image for ${item.business_slug}/${item.direction_id}.`);
      if (image.mime !== 'image/jpeg') fail(`Gemini returned unsupported ${image.mime} for ${item.business_slug}/${item.direction_id}.`);
      const usage = response.body.usage || response.body.usage_metadata;
      if (!usage || typeof usage !== 'object' || !Object.keys(usage).length) fail(`Gemini returned no usage evidence for ${item.business_slug}/${item.direction_id}.`);
      const bytes = Buffer.from(image.data, 'base64');
      if (!bytes.length) fail(`Gemini returned empty image bytes for ${item.business_slug}/${item.direction_id}.`);
      await writeFile(join(staging, item.filename), bytes, { flag: 'wx' });
      artifacts.push({
        business_slug: item.business_slug,
        direction_id: item.direction_id,
        direction_name: item.direction_name,
        filename: item.filename,
        mime_type: image.mime,
        reference_path: item.reference_repo_path,
        reference_sha256: item.reference_sha256,
        prompt: item.prompt,
        prompt_sha256: item.prompt_sha256,
        provider_interaction_id: response.body.id || null,
        provider_status: response.body.status || null,
        provider_response_sha256: sha256(response.raw),
        usage,
        duration_ms: Date.now() - started,
        bytes: bytes.length,
        sha256: sha256(bytes)
      });
      process.stdout.write(`GEMINI_REFERENCE_IMAGE_COMPLETE ${item.business_slug}/${item.direction_id}\n`);
    }

    const receipt = {
      schema: 'famtastic.gemini-flash-lite-reference-image-receipt.v1',
      status: 'complete',
      request_id: options['request-id'] || 'booked-branded-v2-20260827',
      provider: 'google-gemini-api',
      api: 'interactions',
      model,
      credential_source: `macos_keychain:${service}:${account}`,
      credential_value_retained: false,
      requested_output: { aspect_ratio: '16:9', image_size: '1K', mime_type: 'image/jpeg' },
      image_count: artifacts.length,
      estimated_cost_usd: expectedCost,
      cost_ceiling_usd: ceiling,
      cost_status: 'estimated_pending_provider_reconciliation',
      started_at: startedAt,
      completed_at: new Date().toISOString(),
      artifacts
    };
    await writeFile(join(staging, 'generation-receipt.json'), `${JSON.stringify(receipt, null, 2)}\n`, { flag: 'wx' });
    await writeFile(join(staging, 'prompt-manifest.json'), `${JSON.stringify({
      schema: 'famtastic.booked-branded-reference-prompts.v1',
      request_id: receipt.request_id,
      provider: receipt.provider,
      api: receipt.api,
      model: receipt.model,
      reference_rule: plan.image_rules.reference_rule,
      prompts: artifacts.map(({ business_slug, direction_id, direction_name, filename, reference_path, reference_sha256, prompt, prompt_sha256 }) => ({ business_slug, direction_id, direction_name, filename, reference_path, reference_sha256, prompt, prompt_sha256 }))
    }, null, 2)}\n`, { flag: 'wx' });
    await rename(staging, output);
  }
  catch (error) {
    await rm(staging, { recursive: true, force: true });
    throw error;
  }
  process.stdout.write(`GEMINI_REFERENCE_IMAGE_BATCH_COMPLETE images=${artifacts.length} estimated_cost_usd=${expectedCost.toFixed(4)}\n`);
}

const options = parse(process.argv);
if (options.help) {
  process.stdout.write('Usage: --preflight | --execute --output <new-dir> --max-cost-usd <amount> [--plan <creative-system.json>] [--request-id <id>] [--only <slug/direction,...>]\n');
}
else if (options.preflight && !options.execute) {
  const key = apiKey();
  const response = await api(`https://generativelanguage.googleapis.com/v1/models/${model}`, key);
  if (!String(response.body.name || '').endsWith(model)) fail('Gemini preflight returned an unexpected model.');
  process.stdout.write('GEMINI_REFERENCE_IMAGE_PREFLIGHT_AUTHENTICATED\n');
}
else if (options.execute && !options.preflight) {
  await execute(options);
}
else {
  fail('Choose exactly one mode: --preflight or --execute.');
}
