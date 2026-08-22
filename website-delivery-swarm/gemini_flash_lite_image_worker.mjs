#!/usr/bin/env node
/* Gemini Flash Lite Image-only worker; it never writes the Keychain secret. */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const MODEL = 'gemini-3.1-flash-lite-image';
const SERVICE = 'FAMtastic.Gemini.Image';
const ACCOUNT = 'famtastic-gemini-image-worker';
const COST_PER_1K_IMAGE_USD = 0.0336;
const sha256 = (value) => createHash('sha256').update(value).digest('hex');

function parse(argv) {
  const result = { execute: false, preflight: false };
  for (let i = 2; i < argv.length; i += 1) {
    const key = argv[i];
    if (key === '--execute' || key === '--preflight') result[key.slice(2)] = true;
    else if (key.startsWith('--')) result[key.slice(2)] = argv[++i];
    else throw new Error(`Unexpected argument: ${key}`);
  }
  return result;
}
function apiKey() {
  return execFileSync('/usr/bin/security', ['find-generic-password', '-s', SERVICE, '-a', ACCOUNT, '-w'], { encoding: 'utf8' }).trim();
}
async function api(url, key, options = {}) {
  const response = await fetch(url, { ...options, headers: { 'x-goog-api-key': key, ...(options.headers || {}) } });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(`Gemini Image API request failed (${response.status}): ${JSON.stringify(body)}`);
  return body;
}

async function main() {
  const options = parse(process.argv);
  if (options.preflight && (options.execute || options.prompts || options.output || options['max-cost-usd'])) throw new Error('--preflight cannot be combined with generation arguments.');
  const key = apiKey();
  if (options.preflight) {
    const model = await api(`https://generativelanguage.googleapis.com/v1/models/${MODEL}`, key);
    if (!String(model.name || '').endsWith(MODEL)) throw new Error('Gemini preflight returned an unexpected model.');
    process.stdout.write('GEMINI_FLASH_LITE_IMAGE_PREFLIGHT_AUTHENTICATED\n');
    return;
  }
  if (!options.execute || !options.prompts || !options.output || !options['max-cost-usd']) throw new Error('Usage: --preflight | --prompts <file> --output <dir> --execute --max-cost-usd <amount> [--request-id <id>]');
  const parsed = JSON.parse(await readFile(options.prompts, 'utf8'));
  const prompts = Array.isArray(parsed) ? parsed : parsed.image_prompts;
  if (!Array.isArray(prompts) || prompts.length === 0) throw new Error('Prompt file must contain image_prompts.');
  const expected = Number((prompts.length * COST_PER_1K_IMAGE_USD).toFixed(4));
  if (!Number.isFinite(Number(options['max-cost-usd'])) || Number(options['max-cost-usd']) < expected) throw new Error(`Image ceiling must cover ${expected.toFixed(4)} USD.`);
  const output = path.resolve(options.output); await mkdir(output, { recursive: true });
  const artifacts = [];
  for (const item of prompts) {
    const prompt = String(item.prompt || '').trim();
    const filename = path.basename(String(item.filename || item.output || ''));
    if (!prompt || !filename) throw new Error('Each prompt must include prompt and filename.');
    const started = Date.now();
    const response = await api(`https://generativelanguage.googleapis.com/v1/models/${MODEL}:generateContent`, key, {
      method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }], generationConfig: { responseModalities: ['IMAGE'], imageConfig: { aspectRatio: '16:9', imageSize: '1K' } },
      }),
    });
    const image = response.candidates?.flatMap((candidate) => candidate.content?.parts || []).find((part) => part.inlineData?.data)?.inlineData;
    if (!image?.data) throw new Error(`Gemini returned no image for ${filename}.`);
    const bytes = Buffer.from(image.data, 'base64'); await writeFile(path.join(output, filename), bytes);
    artifacts.push({ direction_id: item.direction_id || item.id || '', filename, mime_type: image.mimeType || 'image/jpeg', prompt_sha256: sha256(prompt), sha256: sha256(bytes), bytes: bytes.length, duration_ms: Date.now() - started, usage_metadata: response.usageMetadata || null });
  }
  const receipt = { schema: 'famtastic.gemini-flash-lite-image-receipt.v1', status: 'complete', request_id: options['request-id'] || '', provider: 'google-gemini-api', api: 'generateContent', model: MODEL, credential_source: `macos_keychain:${SERVICE}:${ACCOUNT}`, credential_value_retained: false, requested_output: { aspect_ratio: '16:9', image_size: '1K' }, estimated_cost_usd: expected, cost_status: 'estimated_pending_provider_reconciliation', artifacts, completed_at: new Date().toISOString() };
  await writeFile(path.join(output, 'generation-receipt.json'), `${JSON.stringify(receipt, null, 2)}\n`);
  process.stdout.write(`GEMINI_FLASH_LITE_IMAGE_GENERATION_COMPLETE images=${artifacts.length}\n`);
}
main().catch((error) => { process.stderr.write(`GEMINI_FLASH_LITE_IMAGE_WORKER_ERROR: ${error.message}\n`); process.exitCode = 1; });
