#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { copyFile, mkdir, mkdtemp, readFile, rename, rm, writeFile } from 'node:fs/promises';
import { basename, dirname, join, resolve } from 'node:path';

const args = Object.fromEntries(process.argv.slice(2).reduce((pairs, item, index, list) => {
  if (item.startsWith('--')) pairs.push([item.slice(2), list[index + 1]]);
  return pairs;
}, []));
for (const key of ['initial', 'corrected', 'replacement', 'output']) {
  if (!args[key]) throw new Error(`--${key} is required.`);
}

const initial = resolve(args.initial);
const corrected = resolve(args.corrected);
const replacement = resolve(args.replacement);
const output = resolve(args.output);
const sha256 = value => createHash('sha256').update(value).digest('hex');
const readJson = async path => JSON.parse(await readFile(path, 'utf8'));
const receipts = {
  initial: await readJson(join(initial, 'generation-receipt.json')),
  corrected: await readJson(join(corrected, 'generation-receipt.json')),
  replacement: await readJson(join(replacement, 'generation-receipt.json'))
};
if (receipts.initial.artifacts.length !== 12 || receipts.corrected.artifacts.length !== 12 || receipts.replacement.artifacts.length !== 1) {
  throw new Error('Unexpected provider attempt counts.');
}
const replacementArtifact = receipts.replacement.artifacts[0];
if (`${replacementArtifact.business_slug}/${replacementArtifact.direction_id}` !== 'palmera-fade-society/b') {
  throw new Error('The targeted replacement does not match Palmera direction B.');
}
const selected = receipts.corrected.artifacts.map(item => item.filename === replacementArtifact.filename ? replacementArtifact : item);
if (selected.length !== 12 || new Set(selected.map(item => `${item.business_slug}/${item.direction_id}`)).size !== 12) {
  throw new Error('Final selection is not an exact 4x3 set.');
}

const staging = await mkdtemp(join(dirname(output), `.${basename(output)}.staging-`));
try {
  for (const item of selected) {
    const sourceRoot = item.filename === replacementArtifact.filename ? replacement : corrected;
    const bytes = await readFile(join(sourceRoot, item.filename));
    if (bytes.length !== item.bytes || sha256(bytes) !== item.sha256) throw new Error(`Provider receipt mismatch for ${item.filename}.`);
    await copyFile(join(sourceRoot, item.filename), join(staging, item.filename));
  }
  const allReceipts = await Promise.all([
    readFile(join(initial, 'generation-receipt.json')),
    readFile(join(corrected, 'generation-receipt.json')),
    readFile(join(replacement, 'generation-receipt.json'))
  ]);
  const estimatedCost = Number((25 * 0.0336).toFixed(4));
  const finalReceipt = {
    schema: 'famtastic.gemini-flash-lite-reference-image-receipt.v1',
    status: 'complete_after_primary_quality_repair',
    request_id: 'booked-branded-v2-20260827-final',
    provider: 'google-gemini-api',
    api: 'interactions',
    model: receipts.corrected.model,
    credential_source: receipts.corrected.credential_source,
    credential_value_retained: false,
    requested_output: receipts.corrected.requested_output,
    image_count: 12,
    provider_generation_count: 25,
    estimated_cost_usd: estimatedCost,
    cost_ceiling_usd: 1,
    cost_status: 'estimated_pending_provider_reconciliation',
    started_at: receipts.initial.started_at,
    completed_at: receipts.replacement.completed_at,
    quality_selection: {
      initial_batch: { status: 'rejected', reason: 'Generated typography or interface overlays violated the photo-only quality contract.', receipt_sha256: sha256(allReceipts[0]) },
      corrected_batch: { status: 'selected_except_one', reason: 'Eleven photo-only outputs passed; Palmera direction B retained generated sign text.', receipt_sha256: sha256(allReceipts[1]) },
      targeted_replacement: { status: 'selected', reason: 'Photo-only replacement passed primary visual review.', receipt_sha256: sha256(allReceipts[2]) }
    },
    artifacts: selected
  };
  const promptManifest = {
    schema: 'famtastic.booked-branded-reference-prompts.v1',
    request_id: finalReceipt.request_id,
    provider: finalReceipt.provider,
    api: finalReceipt.api,
    model: finalReceipt.model,
    status: 'complete_after_primary_quality_repair',
    prompts: selected.map(({ business_slug, direction_id, direction_name, filename, reference_path, reference_sha256, prompt, prompt_sha256 }) => ({ business_slug, direction_id, direction_name, filename, reference_path, reference_sha256, prompt, prompt_sha256 }))
  };
  await writeFile(join(staging, 'generation-receipt.json'), `${JSON.stringify(finalReceipt, null, 2)}\n`, { flag: 'wx' });
  await writeFile(join(staging, 'prompt-manifest.json'), `${JSON.stringify(promptManifest, null, 2)}\n`, { flag: 'wx' });
  await rename(staging, output);
}
catch (error) {
  await rm(staging, { recursive: true, force: true });
  throw error;
}
await mkdir(resolve('docs/evidence/booked-branded-four-proof-pilot/provider-receipts'), { recursive: true });
await copyFile(join(initial, 'generation-receipt.json'), resolve('docs/evidence/booked-branded-four-proof-pilot/provider-receipts/01-initial-rejected.json'));
await copyFile(join(corrected, 'generation-receipt.json'), resolve('docs/evidence/booked-branded-four-proof-pilot/provider-receipts/02-corrected-batch.json'));
await copyFile(join(replacement, 'generation-receipt.json'), resolve('docs/evidence/booked-branded-four-proof-pilot/provider-receipts/03-targeted-replacement.json'));
process.stdout.write('BOOKED_BRANDED_IMAGE_SET_FINALIZED images=12 provider_generations=25 estimated_cost_usd=0.8400\n');
