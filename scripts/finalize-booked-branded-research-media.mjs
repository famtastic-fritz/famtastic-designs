#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const root = join(repositoryRoot, 'frontend/public/showcase/booked-and-branded-pilot');
const promptsPath = join(root, 'research/media-prompts.json');
const prompts = JSON.parse(await readFile(promptsPath, 'utf8'));
const files = ['premium', 'candidate-01', 'candidate-02', 'candidate-03'];
const sha256 = buffer => createHash('sha256').update(buffer).digest('hex');
const artifacts = [];

for (const template of prompts.templates) {
  let parentSha = null;
  for (const [index, basename] of files.entries()) {
    const pngPath = join(root, `research-proof-lab/assets/${template.slug}/${basename}.png`);
    const webpPath = join(root, `research-proof-lab/assets/${template.slug}/${basename}.webp`);
    const png = await readFile(pngPath);
    const webp = await readFile(webpPath);
    const pngSha = sha256(png);
    if (index === 0) parentSha = pngSha;
    artifacts.push({
      template_slug: template.slug,
      role: index === 0 ? 'premium-parent' : 'reference-led-candidate',
      candidate_index: index === 0 ? null : index,
      prompt: index === 0 ? template.parent_prompt : template.candidate_prompts[index - 1],
      reference_parent_png_sha256: index === 0 ? null : parentSha,
      source_png: {
        path: `frontend/public/showcase/booked-and-branded-pilot/research-proof-lab/assets/${template.slug}/${basename}.png`,
        bytes: png.byteLength,
        sha256: pngSha
      },
      web_delivery: {
        path: `frontend/public/showcase/booked-and-branded-pilot/research-proof-lab/assets/${template.slug}/${basename}.webp`,
        bytes: webp.byteLength,
        sha256: sha256(webp),
        conversion: index === 0 ? 'cwebp q92' : 'cwebp q90'
      }
    });
  }
}

const receipt = {
  schema: 'famtastic.reference-led-media-receipt.v1',
  created_at: new Date().toISOString(),
  provider: prompts.provider,
  model_status: prompts.model_status,
  cost_status: prompts.cost_status,
  provider_generation_count: artifacts.length,
  premium_parent_count: artifacts.filter(item => item.role === 'premium-parent').length,
  reference_led_candidate_count: artifacts.filter(item => item.role === 'reference-led-candidate').length,
  selected_composition_count: artifacts.length,
  encoding_count: artifacts.length * 2,
  pricing_claim: 'No candidate is labeled cheaper in the evidence because the provider did not report per-generation cost.',
  customer_data_used: false,
  production_changed: false,
  artifacts
};
await writeFile(join(root, 'research-proof-lab/media-generation-receipt.json'), JSON.stringify(receipt, null, 2) + '\n');
console.log(`Recorded ${receipt.premium_parent_count} premium parents and ${receipt.reference_led_candidate_count} reference-led candidates (${receipt.encoding_count} retained encodings).`);
