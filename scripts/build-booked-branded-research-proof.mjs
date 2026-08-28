#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { existsSync } from 'node:fs';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(repositoryRoot, 'frontend/public/showcase/booked-and-branded-pilot');
const researchRoot = join(publicRoot, 'research');
const outputRoot = join(publicRoot, 'research-proof-lab');
const data = JSON.parse(await readFile(join(researchRoot, 'research-proof-data.json'), 'utf8'));
const sources = JSON.parse(await readFile(join(researchRoot, 'source-manifest.json'), 'utf8'));
const ledger = JSON.parse(await readFile(join(researchRoot, 'component-decisions.json'), 'utf8'));
const publicBase = '/showcase/booked-and-branded-pilot';
const labBase = `${publicBase}/research-proof-lab`;

const esc = value => String(value)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;');
const hash = value => createHash('sha256').update(value).digest('hex');
const decisionById = new Map(ledger.decisions.map(item => [item.id, item]));
const sourceById = new Map([...sources.market_sources, ...sources.design_sources].map(item => [item.id, item]));
sourceById.set('kimi-transcript', { id: 'kimi-transcript', title: 'User-supplied Kimi transcript', url: `${labBase}/#kimi-clean-room` });

function validate() {
  if (data.templates.length !== 4) throw new Error(`Expected four research templates; received ${data.templates.length}.`);
  const templateIds = new Set();
  const recipeHashes = new Set();
  for (const template of data.templates) {
    if (templateIds.has(template.id)) throw new Error(`Duplicate template id ${template.id}.`);
    templateIds.add(template.id);
    if (!Array.isArray(template.recipe) || template.recipe.length < 9) throw new Error(`${template.id} has an incomplete one-page recipe.`);
    recipeHashes.add(hash(JSON.stringify(template.recipe)));
    for (const decisionId of template.decision_ids) {
      if (!decisionById.has(decisionId)) throw new Error(`${template.id} references missing decision ${decisionId}.`);
    }
  }
  if (recipeHashes.size !== data.templates.length) throw new Error('The four research templates must have four unique recipe signatures.');
  for (const decision of ledger.decisions) {
    for (const sourceId of decision.sources) {
      if (decisionById.has(sourceId)) continue;
      if (!sourceById.has(sourceId) && !['market-01'].includes(sourceId)) {
        throw new Error(`${decision.id} references missing source or decision ${sourceId}.`);
      }
    }
  }
}

function page({ title, description, body, className = '' }) {
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${esc(title)}</title>
  <meta name="description" content="${esc(description)}">
  <meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
  <meta name="referrer" content="no-referrer">
  <link rel="stylesheet" href="${labBase}/research-proof.css?v=20260827-r1">
</head>
<body class="${esc(className)}">
${body}
</body>
</html>`;
}

function ribbon() {
  return '<div class="rp-ribbon"><strong>Fictional research proof</strong><span>No real business, customer, appointment, price quote, payment, or email</span></div>';
}

function action(href, label, kind = '') {
  return `<a class="rp-button ${esc(kind)}" href="${esc(href)}">${esc(label)} <span aria-hidden="true">↗</span></a>`;
}

function section(template, instanceId, componentId, variant, body, decisions = []) {
  const ids = decisions.length ? decisions : template.decision_ids;
  return `<section id="${esc(instanceId)}" class="rp-section rp-${esc(componentId)} rp-${esc(variant)}" data-section-id="${esc(instanceId)}" data-component-id="${esc(componentId)}" data-component-variant="${esc(variant)}" data-decision-ids="${esc(ids.join(' '))}">${body}</section>`;
}

function mediaPath(template, file) {
  const relative = `research-proof-lab/assets/${template.slug}/${file}`;
  return existsSync(join(publicRoot, relative)) ? `${publicBase}/${relative}` : `${publicBase}/template-lab/assets/${template.slug === 'crown-ledger' ? 'crown-craft' : template.slug === 'coil-ritual' ? 'coil-clay' : template.slug === 'barrio-signal' ? 'palmera-press' : 'saltline-prism'}-material.webp`;
}

function hero(template) {
  const heroVariant = template.recipe.find(item => item.startsWith('hero-'));
  const image = mediaPath(template, 'premium.webp');
  const visual = `<div class="rp-hero-visual" data-slot-id="hero-media"><img data-field-id="hero.media.src" src="${esc(image)}" alt="Fictional editorial parent artwork for ${esc(template.business)}" width="1536" height="1024"><span class="rp-media-index">P / 01</span><div class="rp-orbit" aria-hidden="true"></div></div>`;
  const copy = `<div class="rp-hero-copy" data-slot-id="hero-copy"><p class="rp-overline" data-field-id="hero.overline">${esc(template.name)} · ${esc(template.location)}</p><h1 data-field-id="hero.headline">${esc(template.headline)}</h1><p class="rp-lede" data-field-id="hero.subhead">${esc(template.subhead)}</p><div class="rp-actions" data-slot-id="hero-actions">${action('#booking-bridge', 'See the booking path')}${action('#services', 'Explore services', 'ghost')}</div></div>`;
  return section(template, 'hero', 'hero.research-led.v1', heroVariant, `<div class="rp-shell rp-hero-grid">${template.slug === 'barrio-signal' ? `${visual}${copy}` : `${copy}${visual}`}</div>`, template.decision_ids.filter(id => ['shape-01','shape-02','shape-03','type-01','type-02','type-03','cta-01','motion-01','motion-02','media-01','media-02','media-03','media-04'].includes(id)));
}

function navigation(template) {
  return `<nav class="rp-nav" data-section-id="navigation" data-component-id="navigation.research-nav.v1" data-component-variant="${esc(template.recipe[0])}"><a class="rp-wordmark" href="#top"><span>${esc(template.business.slice(0, 2).toUpperCase())}</span><b>${esc(template.business)}</b></a><div><a href="#services">Services</a><a href="#work">Work</a><a href="#owner-console">Owner view</a>${action('#booking-bridge', 'Book / Request')}</div></nav>`;
}

function thesis(template) {
  return section(template, 'market-proof', 'proof.market-wedge.v1', template.recipe.includes('care-thesis') ? 'care-thesis' : template.recipe.includes('consultation-thesis') ? 'consultation-thesis' : 'research-contrast', `<div class="rp-shell rp-thesis-grid"><div><p class="rp-kicker">Research-backed market wedge</p><h2>${esc(template.thesis)}</h2></div><div><p>Marketplace tools already do discovery, calendars, reminders, client records, and payments well. This concept does not pretend to rebuild all of that for $199.</p><p><strong>FAMtastic adds the owner-controlled brand layer:</strong> a custom domain, clear services, better story, direct contact, a portable booking slot, consented proof, and a component path that can grow.</p></div></div>`, ['market-01','booking-01','foundation-01']);
}

function services(template) {
  const items = template.services.map((item, index) => `<article data-repeater-id="services" data-item-id="service-${index + 1}"><span>${String(index + 1).padStart(2, '0')} · ${esc(item.time)}</span><h3 data-field-id="service.name">${esc(item.name)}</h3><p data-field-id="service.preparation">${esc(item.prep)}</p><div><b data-field-id="service.price">${esc(item.price)}</b><em>Details →</em></div></article>`).join('');
  const variant = template.recipe.find(item => item.startsWith('service-'));
  return section(template, 'services', 'services.research-menu.v1', variant, `<div class="rp-shell"><header class="rp-section-head"><p class="rp-kicker">Services before scattered DMs</p><h2>Make the decision feel easier.</h2><p>Fictional demonstration prices. A real build uses owner-approved services, timing, preparation, and policies.</p></header><div class="rp-service-grid">${items}</div></div>`, template.decision_ids.filter(id => id.startsWith('type-') || id.startsWith('shape-')));
}

function work(template) {
  const files = ['candidate-01.webp', 'candidate-02.webp', 'candidate-03.webp'];
  const labels = ['Environment', 'Process', 'Result / detail'];
  const items = files.map((file, index) => `<figure data-repeater-id="work-media" data-item-id="work-${index + 1}"><img src="${esc(mediaPath(template, file))}" alt="Fictional ${esc(labels[index].toLowerCase())} reference-led candidate for ${esc(template.business)}" width="1536" height="1024"><figcaption><b>${String(index + 2).padStart(2, '0')}</b><span>${esc(labels[index])}</span><small>Reference-led candidate · provider cost not reported</small></figcaption></figure>`).join('');
  const variant = template.recipe.find(item => item === 'work-atlas' || item === 'work-filmstrip');
  return section(template, 'work', 'gallery.reference-led-series.v1', variant, `<div class="rp-shell"><header class="rp-section-head"><p class="rp-kicker">One premium parent · three reference-led candidates</p><h2>A reusable media world, not one lonely hero.</h2><p>The page recipe stays frozen while media slots test environment, process, and result/detail moments. Native HTML owns every word and control.</p></header><div class="rp-work-grid">${items}</div></div>`, template.decision_ids.filter(id => id.startsWith('media-') || id.startsWith('motion-')));
}

function booking(template) {
  const options = ['Keep current provider', 'Square / reviewed widget', 'Google or Cal.com', 'FAMtastic request-to-book'];
  return section(template, 'booking-bridge', 'booking.portable-bridge.v1', 'four-path', `<div class="rp-shell rp-booking-grid"><div><p class="rp-kicker">No forced overnight switch</p><h2>${esc(template.booking_label)}</h2><p>${esc(template.booking_note)}</p><div class="rp-actions">${action('#owner-console', 'See the owner flow')}${action('#research-notes', 'Why this component exists', 'ghost')}</div></div><ol>${options.map((option, index) => `<li><b>0${index + 1}</b><span>${esc(option)}</span></li>`).join('')}</ol></div>`, ['market-01','booking-01','cta-01']);
}

function ownerConsole(template) {
  return section(template, 'owner-console', 'operations.phone-console.v1', 'starter-mobile', `<div class="rp-shell rp-console-grid"><div class="rp-console-copy"><p class="rp-kicker">The upgrade path starts with something useful</p><h2>Manage the essentials from the phone already in the owner’s hand.</h2><ul>${template.facts.map(item => `<li>${esc(item)}</li>`).join('')}</ul></div><div class="rp-phone" aria-label="Static fictional phone owner console"><div class="rp-phone-top"><span>9:41</span><span>Booked &amp; Branded</span><span>•••</span></div><small>${esc(template.business)}</small><h3>Today’s front door</h3><div class="rp-metric"><b>4</b><span>booking taps<br><small>fictional sample</small></span></div><div class="rp-phone-row"><span>New request</span><b>1</b></div><div class="rp-phone-row"><span>Review to approve</span><b>1</b></div><div class="rp-phone-row"><span>Hours</span><b>Open</b></div><div class="rp-phone-actions"><span>Services</span><span>Requests</span><span>Reviews</span><span>Share QR</span></div></div></div>`, ['booking-01','foundation-01']);
}

function foundation(template) {
  const variant = template.recipe.includes('location-first') ? 'map-first' : 'contact-first';
  return section(template, 'location-contact', 'foundation.contact-location.v1', variant, `<div class="rp-shell"><header class="rp-section-head"><p class="rp-kicker">The basics never disappeared</p><h2>Find it. Trust it. Contact it.</h2><p>Custom domain, one forwarding address, protected contact, call/text/social links, service area or location, accurate hours, arrival notes, and an optional owner-approved map.</p></header><div class="rp-foundation-grid"><article><small>Location</small><h3>${esc(template.location)}</h3><p>Fictional appointment-only studio. Exact address and map require owner approval.</p></article><article><small>Contact</small><h3>hello@${esc(template.slug)}.example</h3><p>Demonstration forwarding address. No mailbox or form delivery is active.</p></article><article><small>Owner payment</small><h3>Your QR. Your account.</h3><p>${esc(data.offer.payment_boundary)}</p></article></div></div>`, ['foundation-01']);
}

function shayClose(template) {
  return section(template, 'shay-close', 'concierge.shay-close.v1', 'human-authority', `<div class="rp-shell rp-shay-grid"><span class="rp-shay-orb">S</span><div><p class="rp-kicker">The FAMtastic business face</p><h2>Shay makes the next step understandable.</h2><p>${esc(data.offer.shay)}</p></div><div class="rp-price"><small>Proposed starter</small><strong>${esc(data.offer.price)}</strong><span>${esc(data.offer.renewal)}</span>${action('#top', 'Review this proof')}</div></div>`, ['cta-01','foundation-01']);
}

function researchNotes(template) {
  const notes = template.decision_ids.map(id => decisionById.get(id)).map(decision => `<article data-decision-id="${esc(decision.id)}"><small>${esc(decision.id)} · ${esc(decision.confidence)}</small><h3>${esc(decision.component_scope.join(' · '))}</h3><p>${esc(decision.decision)}</p><div>${decision.sources.map(sourceId => { const source = sourceById.get(sourceId); const url = source?.url || (decisionById.has(sourceId) ? `${labBase}/#decisions` : `${labBase}/#source-${sourceId}`); return `<a href="${esc(url)}" rel="noreferrer">${esc(sourceId)}</a>`; }).join('')}</div></article>`).join('');
  return section(template, 'research-notes', 'proof.component-decisions.v1', 'cited-ledger', `<div class="rp-shell"><header class="rp-section-head"><p class="rp-kicker">Decision ledger</p><h2>Why each component looks and behaves this way.</h2><p>These citations explain the choice and its confidence. They do not claim that any single visual device guarantees conversion.</p></header><div class="rp-notes-grid">${notes}</div></div>`, template.decision_ids);
}

function templatePage(template) {
  const vars = Object.entries(template.palette).map(([key, value]) => `--rp-${key}:${value}`).join(';');
  const body = `${ribbon()}<div id="top" class="rp-template rp-template-${esc(template.slug)}" data-page-template-id="${esc(template.id)}" data-page-template-version="1" data-recipe-signature="${hash(JSON.stringify(template.recipe))}" style="${vars}">${navigation(template)}<main>${hero(template)}${thesis(template)}${services(template)}${work(template)}${booking(template)}${ownerConsole(template)}${foundation(template)}${shayClose(template)}${researchNotes(template)}<section class="rp-footer" data-section-id="footer" data-component-id="navigation.research-footer.v1" data-component-variant="${esc(template.recipe.at(-1))}"><div><b>${esc(template.business)}</b><span>${esc(template.location)} · Fictional local proof</span></div><a href="${labBase}/">Compare all four research recipes →</a></section></main></div>`;
  return page({ title: `${template.business} — ${template.name}`, description: `${template.name}, a fictional research-backed Booked & Branded one-page component recipe.`, className: `research-template-page ${template.slug}`, body });
}

function labPage() {
  const cards = data.templates.map((template, index) => `<article class="rp-lab-card" style="--rp-ink:${template.palette.ink};--rp-paper:${template.palette.paper};--rp-accent:${template.palette.accent};--rp-accent2:${template.palette.accent2}"><a href="${labBase}/templates/${esc(template.slug)}/"><img src="${esc(mediaPath(template, 'premium.webp'))}" alt="Fictional parent artwork for ${esc(template.name)}" width="1536" height="1024"></a><div><small>0${index + 1} · ${esc(template.archetype)}</small><h2>${esc(template.name)}</h2><p>${esc(template.thesis)}</p><ul><li>${esc(template.shape)}</li><li>${esc(template.type)}</li></ul>${action(`${labBase}/templates/${template.slug}/`, 'Open the complete page')}</div></article>`).join('');
  const competitors = sources.market_sources.map(source => `<a id="source-${esc(source.id)}" href="${esc(source.url)}" rel="noreferrer"><b>${esc(source.publisher)}</b><span>${esc(source.use)}</span></a>`).join('');
  const designSources = sources.design_sources.map(source => `<a id="source-${esc(source.id)}" href="${esc(source.url)}" rel="noreferrer"><b>${esc(source.title)}</b><span>${esc(source.use)}</span></a>`).join('');
  const decisions = ledger.decisions.map(item => `<li><code>${esc(item.id)}</code><span>${esc(item.decision)}</span><small>${esc(item.confidence)}</small></li>`).join('');
  const body = `${ribbon()}<main class="rp-lab"><section class="rp-lab-hero"><div class="rp-shell"><a class="rp-back" href="${publicBase}/component-lab/">← Component Lab</a><p class="rp-kicker">FAMtastic Research Proof Lab · live showcase</p><h1>Research the market.<br><em>Compose the difference.</em><br>Prove every part.</h1><p>Four new one-page recipes built from competitor facts, design research, stable components, and a transparent decision ledger. Kimi contributed clean-room patterns—not copied code.</p><div class="rp-actions">${action('#templates', 'Open the four templates')}${action('#competitors', 'Read the competitor map', 'ghost')}${action('#decisions', 'Inspect every decision', 'ghost')}</div></div></section><section class="rp-lab-principle"><div class="rp-shell"><b>Marketplaces discover and schedule.</b><b>FAMtastic helps the owner become memorable and more direct.</b><b>The two can work together on day one.</b></div></section><section id="templates" class="rp-lab-templates"><div class="rp-shell"><header><p class="rp-kicker">Four from scratch</p><h2>Different recipes.<br>Different emotional jobs.</h2><p>Each template keeps the complete website foundation and a portable booking bridge. The visual grammar, component variants, content rhythm, and media story change.</p></header><div>${cards}</div></div></section><section id="competitors" class="rp-lab-research"><div class="rp-shell"><header><p class="rp-kicker">Competitive baseline</p><h2>Respect what they do well.<br>Build where the owner still needs more.</h2><p>These are current official vendor sources, not fabricated complaints. FAMtastic’s argument is ownership, expression, clarity, portability, and an upgrade path—not fear.</p></header><div class="rp-source-grid">${competitors}</div></div></section><section class="rp-lab-research rp-lab-design"><div class="rp-shell"><header><p class="rp-kicker">Design evidence</p><h2>Research informs the composition.<br>It does not replace judgment or testing.</h2></header><div class="rp-source-grid">${designSources}</div></div></section><section id="kimi-clean-room" class="rp-kimi"><div class="rp-shell"><div><p class="rp-kicker">Kimi clean-room extraction</p><h2>Keep the useful ideas.<br>Own the system.</h2><p>Transcript SHA-256: <code>${esc(data.kimi_clean_room.transcript_sha256)}</code></p><p>The live Kimi page returned a connection reset on August 27, 2026, so this lab makes no visual-parity or source-code claim.</p></div><div><h3>Portable patterns</h3><ul>${data.kimi_clean_room.portable_patterns.map(item => `<li>${esc(item)}</li>`).join('')}</ul><h3>Explicit exclusions</h3><ul>${data.kimi_clean_room.excluded.map(item => `<li>${esc(item)}</li>`).join('')}</ul></div></div></section><section id="decisions" class="rp-decision-index"><div class="rp-shell"><header><p class="rp-kicker">Machine-readable and human-readable</p><h2>Every component decision has an ID, reason, source trail, and confidence label.</h2></header><ol>${decisions}</ol></div></section><section class="rp-notebook"><div class="rp-shell"><div><p class="rp-kicker">NotebookLM handoff</p><h2>Packet ready. Notebook link still needed.</h2><p>The NotebookLM MCP is authenticated, but no notebook is registered. Its required next input is a NotebookLM share URL supplied by the owner. The source manifest and research questions are already frozen for import.</p></div><code>${esc(sources.notebooklm.status)}</code></div></section></main>`;
  return page({ title: 'Booked & Branded Research Proof Lab — FAMtastic Designs', description: 'Four fictional, research-backed Booked & Branded page recipes with competitor and component decision evidence.', className: 'research-proof-lab-page', body });
}

async function write(relative, contents) {
  const destination = join(outputRoot, relative);
  await mkdir(dirname(destination), { recursive: true });
  await writeFile(destination, contents);
}

validate();
await rm(join(outputRoot, 'templates'), { recursive: true, force: true });
await write('index.html', labPage());
for (const template of data.templates) await write(`templates/${template.slug}/index.html`, templatePage(template));

const report = {
  schema: 'famtastic.booked-branded-research-proof-build.v1',
  generated_at: new Date().toISOString(),
  template_count: data.templates.length,
  template_ids: data.templates.map(item => item.id),
  recipe_signatures: Object.fromEntries(data.templates.map(item => [item.id, hash(JSON.stringify(item.recipe))])),
  decision_count: ledger.decisions.length,
  source_count: sources.market_sources.length + sources.design_sources.length + 1,
  notebooklm_status: sources.notebooklm.status,
  media_contract: 'one parent premium slot plus three reference-led candidate slots per template; cost must remain provider_did_not_report unless a provider receipt reports it',
  production_changed: false,
  customer_data_used: false,
  email_sent: false
};
await write('build-report.json', JSON.stringify(report, null, 2) + '\n');
console.log(`Built ${report.template_count} research-backed one-page recipes with ${report.decision_count} cited component decisions.`);
