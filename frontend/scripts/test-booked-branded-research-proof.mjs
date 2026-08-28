#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { access, readFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const repositoryRoot = resolve(frontendRoot, '..');
const root = join(frontendRoot, 'public/showcase/booked-and-branded-pilot');
const data = JSON.parse(await readFile(join(root, 'research/research-proof-data.json'), 'utf8'));
const ledger = JSON.parse(await readFile(join(root, 'research/component-decisions.json'), 'utf8'));
const sources = JSON.parse(await readFile(join(root, 'research/source-manifest.json'), 'utf8'));
const registry = JSON.parse(await readFile(join(root, 'component-system.json'), 'utf8'));
const receipt = JSON.parse(await readFile(join(root, 'research-proof-lab/media-generation-receipt.json'), 'utf8'));
const sha256 = buffer => createHash('sha256').update(buffer).digest('hex');

if (data.templates.length !== 4) throw new Error('Research proof must contain exactly four templates.');
if (new Set(data.templates.map(item => item.id)).size !== 4) throw new Error('Research page-template IDs must be unique.');
if (new Set(data.templates.map(item => sha256(JSON.stringify(item.recipe)))).size !== 4) throw new Error('Research page recipes must be structurally distinct.');

const registered = new Set(registry.research_proof_lab.component_definitions.map(item => item.id));
const decisions = new Set(ledger.decisions.map(item => item.id));
const sourceIds = new Set([...sources.market_sources, ...sources.design_sources].map(item => item.id));
sourceIds.add('kimi-transcript');

for (const decision of ledger.decisions) {
  for (const sourceId of decision.sources) {
    if (!sourceIds.has(sourceId) && !decisions.has(sourceId)) throw new Error(`${decision.id} references missing source ${sourceId}.`);
  }
}

const expectedComponents = [
  'navigation.research-nav.v1',
  'hero.research-led.v1',
  'proof.market-wedge.v1',
  'services.research-menu.v1',
  'gallery.reference-led-series.v1',
  'booking.portable-bridge.v1',
  'operations.phone-console.v1',
  'foundation.contact-location.v1',
  'concierge.shay-close.v1',
  'proof.component-decisions.v1',
  'navigation.research-footer.v1'
];
for (const id of expectedComponents) if (!registered.has(id)) throw new Error(`Component registry is missing ${id}.`);

for (const template of data.templates) {
  const path = join(root, `research-proof-lab/templates/${template.slug}/index.html`);
  const html = await readFile(path, 'utf8');
  if (!html.includes(`data-page-template-id="${template.id}"`)) throw new Error(`${template.slug} lost its page identity.`);
  if (!html.includes('data-slot-id="hero-media"')) throw new Error(`${template.slug} lost its parent media slot.`);
  if (!html.includes('data-repeater-id="work-media"')) throw new Error(`${template.slug} lost its candidate media repeater.`);
  if (!html.includes('prefers-reduced-motion')) {
    const css = await readFile(join(root, 'research-proof-lab/research-proof.css'), 'utf8');
    if (!css.includes('@media (prefers-reduced-motion: reduce)')) throw new Error('Research templates have no reduced-motion contract.');
  }
  for (const decisionId of template.decision_ids) {
    if (!decisions.has(decisionId)) throw new Error(`${template.slug} references unknown decision ${decisionId}.`);
    if (!html.includes(`data-decision-id="${decisionId}"`)) throw new Error(`${template.slug} does not render decision ${decisionId}.`);
  }
  for (const basename of ['premium', 'candidate-01', 'candidate-02', 'candidate-03']) {
    await access(join(root, `research-proof-lab/assets/${template.slug}/${basename}.webp`));
  }
  if (/Booksy (?:is bad|is ugly|steals)|guaranteed bookings|guaranteed revenue/i.test(html)) throw new Error(`${template.slug} contains an unapproved or adversarial marketing claim.`);
}

if (receipt.provider_generation_count !== 16 || receipt.premium_parent_count !== 4 || receipt.reference_led_candidate_count !== 12) {
  throw new Error('Media receipt must record four parents and twelve candidates.');
}
for (const artifact of receipt.artifacts) {
  const png = await readFile(join(repositoryRoot, artifact.source_png.path));
  const webp = await readFile(join(repositoryRoot, artifact.web_delivery.path));
  if (sha256(png) !== artifact.source_png.sha256) throw new Error(`${artifact.source_png.path} hash drifted.`);
  if (sha256(webp) !== artifact.web_delivery.sha256) throw new Error(`${artifact.web_delivery.path} hash drifted.`);
  if (artifact.role === 'reference-led-candidate' && !artifact.reference_parent_png_sha256) throw new Error(`${artifact.source_png.path} lost parent lineage.`);
}

const lab = await readFile(join(root, 'research-proof-lab/index.html'), 'utf8');
if (!lab.includes(sources.notebooklm.status)) throw new Error('Research lab hides the NotebookLM boundary.');
if (!lab.includes(data.kimi_clean_room.transcript_sha256)) throw new Error('Research lab hides the Kimi clean-room source hash.');

console.log('PASS: 4 distinct research recipes, 11 stable components, 16 cited decisions, and 4 parent + 12 reference-led media compositions are internally consistent.');
