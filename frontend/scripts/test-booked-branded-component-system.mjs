#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { access, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const repositoryRoot = resolve(frontendRoot, '..');
const publicRoot = join(frontendRoot, 'public/showcase/booked-and-branded-pilot');
const system = JSON.parse(await readFile(join(publicRoot, 'component-system.json'), 'utf8'));
const recipe = system.page_templates.find(item => item.id === system.image_only_proof.page_template_id);
if (!recipe) throw new Error('Image-only proof references a missing page template.');

const componentIds = new Set(system.components.map(item => item.id));
const expectedSections = ['header', 'main', 'footer'].flatMap(region => recipe.regions[region]);
if (expectedSections.length !== 9) throw new Error(`Expected 9 component instances; received ${expectedSections.length}.`);
for (const section of expectedSections) {
  if (!componentIds.has(section.component_id)) throw new Error(`Recipe references missing component ${section.component_id}.`);
}

const expectedSignature = expectedSections.map(section => `${section.instance_id}:${section.component_id}:${section.variant}`);
const normalizedHashes = new Set();
const evidence = [];

for (const variant of system.image_only_proof.variants) {
  const route = `component-lab/image-only/${variant.id}/index.html`;
  const path = join(publicRoot, route);
  const html = await readFile(path, 'utf8');
  const signature = [...html.matchAll(/data-section-id="([^"]+)" data-component-id="([^"]+)" data-component-variant="([^"]+)"/g)]
    .map(match => `${match[1]}:${match[2]}:${match[3]}`);
  if (JSON.stringify(signature) !== JSON.stringify(expectedSignature)) {
    throw new Error(`${variant.id} changed the frozen component signature.`);
  }
  if (!html.includes(`data-page-template-id="${recipe.id}"`)) throw new Error(`${variant.id} lost its page-template identity.`);
  const heroMedia = html.match(/data-field-id="hero\.media\.src" src="([^"]+)"/);
  if (!heroMedia || heroMedia[1] !== variant.media_src) throw new Error(`${variant.id} does not use its declared hero-media asset.`);
  const assetPath = join(publicRoot, variant.media_src.replace('/showcase/booked-and-branded-pilot/', ''));
  await access(assetPath);

  const normalized = html.replace(variant.media_src, '__FROZEN_HERO_MEDIA_SLOT__');
  const normalizedHash = createHash('sha256').update(normalized).digest('hex');
  normalizedHashes.add(normalizedHash);
  evidence.push({
    variant_id: variant.id,
    route,
    media_src: variant.media_src,
    component_signature: signature,
    normalized_html_sha256: normalizedHash,
  });
}

if (normalizedHashes.size !== 1) {
  throw new Error('The four image-only pages differ somewhere other than hero-media.src.');
}

const report = {
  schema: 'famtastic.component-system-proof.v1',
  generated_at: new Date().toISOString(),
  page_template_id: recipe.id,
  component_count: expectedSections.length,
  variant_count: evidence.length,
  allowed_change: system.image_only_proof.allowed_change,
  normalized_html_sha256: [...normalizedHashes][0],
  passed: true,
  variants: evidence,
};
const evidenceDir = join(repositoryRoot, 'docs/evidence/booked-branded-component-system');
await mkdir(evidenceDir, { recursive: true });
await writeFile(join(evidenceDir, 'image-only-proof.json'), JSON.stringify(report, null, 2) + '\n');

console.log(`PASS: ${evidence.length} pages share ${expectedSections.length} frozen component instances; only ${system.image_only_proof.allowed_change} changes.`);
console.log(`Normalized page hash: ${report.normalized_html_sha256}`);
