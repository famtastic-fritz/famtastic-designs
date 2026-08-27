#!/usr/bin/env node
/**
 * Gemini Flash Lite Image-only worker.
 *
 * Provenance: imported from the authenticated public-preview worker at
 * `0e3366a2b181a5a7570f0ef2ce2de72704b0a275`,
 * `website-delivery-swarm/gemini_flash_lite_image_worker.mjs`.
 * Source SHA-256: ed539310bc389f22aa47928a10dbae9a4422bd9a9a4ffaf5b6e8655c8c225d8a.
 * This local bridge preserves that route's credential boundary while adding
 * offline input/receipt validation for the verified-cold proof cohort.
 *
 * --validate-input and --validate-receipt are deliberately offline. They do
 * not query the macOS Keychain, make a provider request, write image files,
 * create a Drupal record, publish a proof, or send email.
 */

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync } from 'node:fs';
import { mkdir, mkdtemp, readFile, rename, rm, writeFile } from 'node:fs/promises';
import { basename, dirname, extname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const GEMINI_FLASH_LITE_IMAGE_MODEL = 'gemini-3.1-flash-lite-image';
export const GEMINI_FLASH_LITE_RECEIPT_SCHEMA = 'famtastic.gemini-flash-lite-image-receipt.v1';
export const GEMINI_FLASH_LITE_INPUT_SCHEMA = 'famtastic.gemini-flash-lite-image-worker-input.v1';

const SERVICE = 'FAMtastic.Gemini.Image';
const ACCOUNT = 'famtastic-gemini-image-worker';
const COST_PER_1K_IMAGE_USD = 0.0336;
const IMAGE_MIME_TYPES = new Set(['image/png', 'image/jpeg']);
const FILENAME_EXTENSIONS = new Set(['.png', '.jpg', '.jpeg']);
const DIRECTION_PATTERN = /^[a-z][a-z0-9_-]{0,63}$/;
const SHA256_PATTERN = /^[a-f0-9]{64}$/i;

export function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function fail(message) {
  throw new Error(message);
}

function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function nonEmptyObject(value) {
  return isPlainObject(value) && Object.keys(value).length > 0;
}

function requireExactUtf8Text(value, label) {
  if (typeof value !== 'string') fail(label + ' must be a string.');
  const bytes = Buffer.from(value, 'utf8');
  if (bytes.toString('utf8') !== value) fail(label + ' must round-trip as exact UTF-8 bytes.');
  return { text: value, bytes };
}

function requireDirection(value, label) {
  if (typeof value !== 'string' || value.trim() !== value || !DIRECTION_PATTERN.test(value)) {
    fail(label + ' must be a safe non-empty direction_id.');
  }
  return value;
}

function requireFilename(value, label) {
  if (typeof value !== 'string' || !value || value.trim() !== value) fail(label + ' must be a non-empty filename.');
  if (value !== basename(value) || value.includes('\\') || value === '.' || value === '..') {
    fail(label + ' must be a bare filename without path separators.');
  }
  if (!FILENAME_EXTENSIONS.has(extname(value).toLowerCase())) {
    fail(label + ' must end in .png, .jpg, or .jpeg.');
  }
  return value;
}

function requireSha256(value, label) {
  if (typeof value !== 'string' || !SHA256_PATTERN.test(value)) fail(label + ' must be a SHA-256 hex digest.');
  return value.toLowerCase();
}

function requirePositiveInteger(value, label) {
  if (!Number.isSafeInteger(value) || value <= 0) fail(label + ' must be a positive integer.');
  return value;
}

function requireIsoTimestamp(value, label) {
  if (typeof value !== 'string' || Number.isNaN(Date.parse(value))) fail(label + ' must be an ISO date-time.');
  return value;
}

function exactUniqueStrings(value, label) {
  if (!Array.isArray(value) || value.length === 0) fail(label + ' must be a non-empty array.');
  const checked = value.map(function (item, index) { return requireDirection(item, label + '[' + index + ']'); });
  if (new Set(checked).size !== checked.length) fail(label + ' must not contain duplicate directions.');
  return checked;
}

/**
 * Validates a worker payload without normalizing the prompt itself. `trim()`
 * is used only to reject an all-whitespace prompt; the exact original UTF-8
 * bytes become both the provider request text and the receipt hash.
 */
export function normalizePromptPayload(parsed) {
  const payload = Array.isArray(parsed) ? { image_prompts: parsed } : parsed;
  if (!isPlainObject(payload)) fail('Prompt file must be an object or an image_prompts array.');
  if (payload.schema && payload.schema !== GEMINI_FLASH_LITE_INPUT_SCHEMA) {
    fail('Prompt file schema must be ' + GEMINI_FLASH_LITE_INPUT_SCHEMA + ' when declared.');
  }
  if (!Array.isArray(payload.image_prompts) || payload.image_prompts.length === 0) {
    fail('Prompt file must contain a non-empty image_prompts array.');
  }
  const expectedDirections = payload.expected_directions === undefined
    ? null
    : exactUniqueStrings(payload.expected_directions, 'expected_directions');
  const directions = new Set();
  const filenames = new Set();
  const prompts = payload.image_prompts.map(function (item, index) {
    if (!isPlainObject(item)) fail('image_prompts[' + index + '] must be an object.');
    const directionId = requireDirection(item.direction_id ?? item.id, 'image_prompts[' + index + '].direction_id');
    const filename = requireFilename(item.filename, 'image_prompts[' + index + '].filename');
    if (directions.has(directionId)) fail('Prompt file contains a duplicate direction_id: ' + directionId + '.');
    if (filenames.has(filename)) fail('Prompt file contains a duplicate filename: ' + filename + '.');
    directions.add(directionId);
    filenames.add(filename);
    const prompt = requireExactUtf8Text(item.prompt, 'image_prompts[' + index + '].prompt');
    if (!prompt.text.trim()) fail('image_prompts[' + index + '].prompt must contain non-whitespace text.');
    const promptHash = sha256(prompt.bytes);
    if (item.prompt_sha256 !== undefined && requireSha256(item.prompt_sha256, 'image_prompts[' + index + '].prompt_sha256') !== promptHash) {
      fail('image_prompts[' + index + '].prompt_sha256 does not match the exact prompt bytes.');
    }
    return {
      direction_id: directionId,
      filename,
      prompt: prompt.text,
      prompt_bytes: prompt.bytes,
      prompt_sha256: promptHash,
    };
  });
  if (expectedDirections) {
    if (prompts.length !== expectedDirections.length || expectedDirections.some(function (direction) { return !directions.has(direction); })) {
      fail('Prompt file is incomplete: image_prompts must contain exactly expected_directions.');
    }
  }
  return { prompts, expected_directions: expectedDirections };
}

function inlineImageParts(response) {
  if (!isPlainObject(response) || !Array.isArray(response.candidates) || response.candidates.length === 0) {
    fail('Gemini returned no candidate result.');
  }
  return response.candidates.flatMap(function (candidate) {
    return Array.isArray(candidate && candidate.content && candidate.content.parts) ? candidate.content.parts : [];
  }).filter(function (part) {
    return isPlainObject(part) && isPlainObject(part.inlineData) && typeof part.inlineData.data === 'string' && part.inlineData.data.length > 0;
  });
}

function strictBase64(value, label) {
  if (typeof value !== 'string' || !/^[A-Za-z0-9+/]+={0,2}$/.test(value) || value.length % 4 !== 0) {
    fail(label + ' must be non-empty base64 data.');
  }
  const bytes = Buffer.from(value, 'base64');
  if (!bytes.length) fail(label + ' decoded to an empty image.');
  return bytes;
}

/**
 * Converts one actual provider response into a receipt artifact. This pure
 * function is intentionally unit-testable without a credential or network.
 */
export function artifactFromProviderResponse(response, promptItem, startedAt, completedAt) {
  const item = promptItem && promptItem.prompt_bytes ? promptItem : normalizePromptPayload({ image_prompts: [promptItem] }).prompts[0];
  if (!nonEmptyObject(response && response.usageMetadata)) {
    fail('Gemini response for ' + item.direction_id + ' is missing non-empty usageMetadata evidence.');
  }
  const parts = inlineImageParts(response);
  if (parts.length !== 1) fail('Gemini response for ' + item.direction_id + ' must contain exactly one inline image result.');
  const inlineData = parts[0].inlineData;
  const bytes = strictBase64(inlineData.data, 'Gemini image data for ' + item.direction_id);
  const mimeType = String(inlineData.mimeType || '');
  if (!IMAGE_MIME_TYPES.has(mimeType)) fail('Gemini response for ' + item.direction_id + ' must declare image/png or image/jpeg.');
  const duration = completedAt - startedAt;
  if (!Number.isSafeInteger(duration) || duration <= 0) fail('Gemini response duration for ' + item.direction_id + ' must be positive.');
  return {
    direction_id: item.direction_id,
    filename: item.filename,
    mime_type: mimeType,
    prompt_sha256: item.prompt_sha256,
    sha256: sha256(bytes),
    bytes: bytes.length,
    duration_ms: duration,
    usage_metadata: response.usageMetadata,
    _image_bytes: bytes,
  };
}

/**
 * Validates a complete, already-recorded worker receipt against the exact
 * planned prompt payload. It is used only offline by --validate-receipt and
 * by execute before any image or receipt file is committed to the output path.
 */
export function validateCompleteReceipt(receipt, promptPayload) {
  const normalized = promptPayload && Array.isArray(promptPayload.prompts)
    ? promptPayload
    : normalizePromptPayload(promptPayload);
  if (!isPlainObject(receipt)) fail('Worker receipt must be an object.');
  if (receipt.schema !== GEMINI_FLASH_LITE_RECEIPT_SCHEMA) fail('Worker receipt schema must be ' + GEMINI_FLASH_LITE_RECEIPT_SCHEMA + '.');
  if (receipt.status !== 'complete') fail('Worker receipt.status must be complete.');
  if (receipt.provider !== 'google-gemini-api' || receipt.api !== 'generateContent' || receipt.model !== GEMINI_FLASH_LITE_IMAGE_MODEL) {
    fail('Worker receipt must identify the Gemini Developer API generateContent model.');
  }
  requireIsoTimestamp(receipt.completed_at, 'Worker receipt.completed_at');
  if (!Array.isArray(receipt.artifacts) || receipt.artifacts.length !== normalized.prompts.length) {
    fail('Worker receipt is incomplete: it must contain exactly one artifact for every planned prompt.');
  }
  const promptByDirection = new Map(normalized.prompts.map(function (item) { return [item.direction_id, item]; }));
  const directions = new Set();
  const filenames = new Set();
  receipt.artifacts.forEach(function (artifact, index) {
    if (!isPlainObject(artifact)) fail('Worker receipt artifact[' + index + '] must be an object.');
    const direction = requireDirection(artifact.direction_id, 'Worker receipt artifact[' + index + '].direction_id');
    const filename = requireFilename(artifact.filename, 'Worker receipt artifact[' + index + '].filename');
    if (directions.has(direction)) fail('Worker receipt contains a duplicate direction_id: ' + direction + '.');
    if (filenames.has(filename)) fail('Worker receipt contains a duplicate filename: ' + filename + '.');
    directions.add(direction);
    filenames.add(filename);
    const planned = promptByDirection.get(direction);
    if (!planned) fail('Worker receipt contains an unexpected direction_id: ' + direction + '.');
    if (filename !== planned.filename) fail('Worker receipt filename does not match planned filename for direction ' + direction + '.');
    if (requireSha256(artifact.prompt_sha256, 'Worker receipt artifact[' + index + '].prompt_sha256') !== planned.prompt_sha256) {
      fail('Worker receipt prompt_sha256 does not match the exact planned prompt bytes for direction ' + direction + '.');
    }
    requireSha256(artifact.sha256, 'Worker receipt artifact[' + index + '].sha256');
    requirePositiveInteger(artifact.bytes, 'Worker receipt artifact[' + index + '].bytes');
    requirePositiveInteger(artifact.duration_ms, 'Worker receipt artifact[' + index + '].duration_ms');
    if (!IMAGE_MIME_TYPES.has(artifact.mime_type)) fail('Worker receipt artifact[' + index + '].mime_type must be image/png or image/jpeg.');
    if (!nonEmptyObject(artifact.usage_metadata)) fail('Worker receipt artifact[' + index + '] is missing non-empty provider usage_metadata evidence.');
  });
  if (directions.size !== promptByDirection.size || Array.from(promptByDirection.keys()).some(function (direction) { return !directions.has(direction); })) {
    fail('Worker receipt is incomplete: one or more planned directions are missing.');
  }
  return { prompts: normalized.prompts, artifacts: receipt.artifacts };
}

function parse(argv) {
  const result = { execute: false, preflight: false, validateInput: false, validateReceipt: false, help: false };
  const aliases = { '--validate-input': 'validateInput', '--validate-receipt': 'validateReceipt' };
  for (let index = 2; index < argv.length; index += 1) {
    const key = argv[index];
    if (key === '--execute') result.execute = true;
    else if (key === '--preflight') result.preflight = true;
    else if (key === '--validate-input' || key === '--validate-receipt') result[aliases[key]] = true;
    else if (key === '--help' || key === '-h') result.help = true;
    else if (key.startsWith('--')) {
      const value = argv[++index];
      if (value === undefined || value.startsWith('--')) fail('Missing value for ' + key + '.');
      result[key.slice(2)] = value;
    } else fail('Unexpected argument: ' + key);
  }
  return result;
}

function printUsage() {
  process.stdout.write('Usage: --preflight | --prompts <file> --output <new-dir> --execute --max-cost-usd <amount> [--request-id <id>] | --validate-input --prompts <file> | --validate-receipt --prompts <file> --receipt <file>\n');
}

async function parsePromptFileAsync(path) {
  let value;
  try {
    value = JSON.parse(await readFile(path, 'utf8'));
  } catch (error) {
    fail('Prompt file is not valid JSON: ' + error.message);
  }
  return normalizePromptPayload(value);
}

async function parseReceiptFile(path) {
  let value;
  try {
    value = JSON.parse(await readFile(path, 'utf8'));
  } catch (error) {
    fail('Worker receipt is not valid JSON: ' + error.message);
  }
  return value;
}

function apiKey() {
  return execFileSync('/usr/bin/security', ['find-generic-password', '-s', SERVICE, '-a', ACCOUNT, '-w'], { encoding: 'utf8' }).trim();
}

async function api(url, key, options = {}) {
  const response = await fetch(url, { ...options, headers: { 'x-goog-api-key': key, ...(options.headers || {}) } });
  const body = await response.json().catch(function () { return {}; });
  if (!response.ok) throw new Error('Gemini Image API request failed (' + response.status + '): ' + JSON.stringify(body));
  return body;
}

function expectedCost(prompts) {
  return Number((prompts.length * COST_PER_1K_IMAGE_USD).toFixed(4));
}

function receiptFor(artifacts, requestId, expected) {
  return {
    schema: GEMINI_FLASH_LITE_RECEIPT_SCHEMA,
    status: 'complete',
    request_id: requestId || '',
    provider: 'google-gemini-api',
    api: 'generateContent',
    model: GEMINI_FLASH_LITE_IMAGE_MODEL,
    credential_source: 'macos_keychain:' + SERVICE + ':' + ACCOUNT,
    credential_value_retained: false,
    requested_output: { aspect_ratio: '16:9', image_size: '1K' },
    estimated_cost_usd: expected,
    cost_status: 'estimated_pending_provider_reconciliation',
    artifacts: artifacts.map(function (artifact) {
      const { _image_bytes, ...safeArtifact } = artifact;
      return safeArtifact;
    }),
    completed_at: new Date().toISOString(),
  };
}

async function execute(options, prompts) {
  const ceiling = Number(options['max-cost-usd']);
  const expected = expectedCost(prompts);
  if (!Number.isFinite(ceiling) || ceiling < expected) fail('Image ceiling must cover ' + expected.toFixed(4) + ' USD.');
  const output = resolve(options.output);
  if (existsSync(output)) fail('Output directory must not already exist: ' + output);
  const key = apiKey();
  const artifacts = [];
  for (const item of prompts) {
    const started = Date.now();
    const response = await api('https://generativelanguage.googleapis.com/v1/models/' + GEMINI_FLASH_LITE_IMAGE_MODEL + ':generateContent', key, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({
        contents: [{ parts: [{ text: item.prompt }] }],
        generationConfig: { responseModalities: ['IMAGE'], imageConfig: { aspectRatio: '16:9', imageSize: '1K' } },
      }),
    });
    artifacts.push(artifactFromProviderResponse(response, item, started, Date.now()));
  }
  const receipt = receiptFor(artifacts, options['request-id'], expected);
  validateCompleteReceipt(receipt, { prompts });
  await mkdir(dirname(output), { recursive: true });
  const staging = await mkdtemp(resolve(dirname(output), '.' + basename(output) + '.staging-'));
  try {
    for (const artifact of artifacts) await writeFile(resolve(staging, artifact.filename), artifact._image_bytes, { flag: 'wx' });
    await writeFile(resolve(staging, 'generation-receipt.json'), JSON.stringify(receipt, null, 2) + '\n', { flag: 'wx' });
    await rename(staging, output);
  } catch (error) {
    await rm(staging, { recursive: true, force: true });
    throw error;
  }
  process.stdout.write('GEMINI_FLASH_LITE_IMAGE_GENERATION_COMPLETE images=' + artifacts.length + '\n');
}

async function main() {
  const options = parse(process.argv);
  if (options.help) {
    printUsage();
    return;
  }
  const modes = ['execute', 'preflight', 'validateInput', 'validateReceipt'].filter(function (key) { return options[key]; });
  if (modes.length !== 1) fail('Choose exactly one mode: --preflight, --execute, --validate-input, or --validate-receipt.');
  if (options.preflight) {
    if (options.prompts || options.output || options['max-cost-usd'] || options.receipt) fail('--preflight cannot be combined with generation or validation arguments.');
    const key = apiKey();
    const model = await api('https://generativelanguage.googleapis.com/v1/models/' + GEMINI_FLASH_LITE_IMAGE_MODEL, key);
    if (!String(model.name || '').endsWith(GEMINI_FLASH_LITE_IMAGE_MODEL)) fail('Gemini preflight returned an unexpected model.');
    process.stdout.write('GEMINI_FLASH_LITE_IMAGE_PREFLIGHT_AUTHENTICATED\n');
    return;
  }
  if (!options.prompts) fail('--prompts is required for this mode.');
  const parsedPrompts = await parsePromptFileAsync(options.prompts);
  if (options.validateInput) {
    if (options.output || options.receipt || options['max-cost-usd']) fail('--validate-input accepts only --prompts.');
    process.stdout.write('GEMINI_FLASH_LITE_IMAGE_INPUT_VALIDATED directions=' + parsedPrompts.prompts.map(function (item) { return item.direction_id; }).join(',') + '\n');
    return;
  }
  if (options.validateReceipt) {
    if (!options.receipt || options.output || options['max-cost-usd']) fail('--validate-receipt requires --prompts and --receipt only.');
    const receipt = await parseReceiptFile(options.receipt);
    validateCompleteReceipt(receipt, parsedPrompts);
    process.stdout.write('GEMINI_FLASH_LITE_IMAGE_RECEIPT_VALIDATED directions=' + parsedPrompts.prompts.map(function (item) { return item.direction_id; }).join(',') + '\n');
    return;
  }
  if (!options.output || options['max-cost-usd'] === undefined) fail('Usage: --prompts <file> --output <new-dir> --execute --max-cost-usd <amount> [--request-id <id>]');
  await execute(options, parsedPrompts.prompts);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch(function (error) {
    process.stderr.write('GEMINI_FLASH_LITE_IMAGE_WORKER_ERROR: ' + error.message + '\n');
    process.exitCode = 1;
  });
}
