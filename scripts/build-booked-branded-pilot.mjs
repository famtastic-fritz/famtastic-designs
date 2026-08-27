#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync } from 'node:fs';
import { mkdir, readFile, readdir, rm, stat, writeFile } from 'node:fs/promises';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(repositoryRoot, 'frontend/public/showcase/booked-and-branded-pilot');
const dataPath = join(publicRoot, 'pilot-data.json');
const data = JSON.parse(await readFile(dataPath, 'utf8'));
const creativePath = join(publicRoot, 'creative-system.json');
const creative = JSON.parse(await readFile(creativePath, 'utf8'));
const publicBase = '/showcase/booked-and-branded-pilot';
const canonicalBase = 'https://famtasticdesigns.com' + publicBase;
const assetRevision = '20260827-room-alignment';
const generatedImageRoot = join(publicRoot, 'assets/directions');
const generationReceiptPath = join(generatedImageRoot, 'generation-receipt.json');
const generatedPromptManifestPath = join(generatedImageRoot, 'prompt-manifest.json');
const materialReceiptPath = join(publicRoot, 'template-lab/material-generation-receipt.json');
const wowReceiptPath = join(publicRoot, 'wow-lab/image-generation-receipt.json');

function esc(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function possessive(value) {
  const text = String(value);
  return text.toLowerCase().endsWith('s') ? `${text}’` : `${text}’s`;
}

function systemFor(business, direction) {
  const archetype = creative.archetypes?.[direction.id];
  const businessSystem = creative.businesses?.[business.slug];
  if (!archetype || !businessSystem) throw new Error(`Creative system is incomplete for ${business.slug}/${direction.id}.`);
  return { archetype, businessSystem };
}

function directionImage(business, direction) {
  const filename = `${business.slug}-${direction.id}.jpg`;
  return existsSync(join(generatedImageRoot, filename))
    ? `${publicBase}/assets/directions/${filename}`
    : `${publicBase}/assets/${business.image}`;
}

function template({ title, description, body, className = '' }) {
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${esc(title)}</title>
  <meta name="description" content="${esc(description)}">
  <meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
  <meta name="referrer" content="no-referrer">
  <link rel="stylesheet" href="${publicBase}/styles.css?v=${assetRevision}">
</head>
<body class="${esc(className)}">
${body}
</body>
</html>`;
}

function ribbon() {
  return '<div class="demo-ribbon"><span>Fictional demonstration</span> No real business, client, review, appointment, payment, or email</div>';
}

function vars(business, direction) {
  const { ink, paper, accent, accent2 } = business.palette;
  const { archetype } = systemFor(business, direction);
  const display = archetype.type.display_family === 'Lora' ? 'Lora' : 'Metropolis';
  const body = archetype.type.body_family === 'Lora' ? 'Lora' : 'Metropolis';
  return `--ink:${esc(ink)};--paper:${esc(paper)};--accent:${esc(accent)};--accent2:${esc(accent2)};--display-font:${display};--body-font:${body};--shape-radius:${esc(archetype.shape.radius)}`;
}

function button(href, label, secondary = false) {
  return `<a class="button${secondary ? ' secondary' : ''}" href="${esc(href)}">${esc(label)} <span aria-hidden="true">→</span></a>`;
}

function pilotIndex() {
  const cards = data.businesses.map((business, index) => `
    <article class="pilot-card">
      <img class="pilot-card-image" src="${publicBase}/assets/${esc(business.image)}" alt="Fictional editorial hero created for ${esc(business.name)}" width="1672" height="941">
      <div class="pilot-card-body">
        <div class="pilot-card-meta"><span>${esc(business.location)}</span><span>${esc(business.operator)}</span></div>
        <h2>${esc(business.name)}</h2>
        <p>${esc(business.specialty)}.</p>
        <div class="pilot-card-actions">
          ${button(`${publicBase}/emails/${business.slug}/`, 'See the actual email')}
          ${button(`${publicBase}/rooms/${business.slug}/`, 'Open 3-proof room', true)}
        </div>
      </div>
    </article>`).join('');

  return template({
    title: 'Booked & Branded — Four-Proof Pilot',
    description: 'Four fictional appointment-business demonstrations with email previews, three design directions, and phone Booking Desk concepts.',
    className: 'pilot-page',
    body: `${ribbon()}
      <header class="pilot-hero">
        <div class="shell pilot-hero-grid">
          <div>
            <p class="kicker">FAMtastic Designs · Founding pilot proof</p>
            <h1>Booked <em>&</em><br>Branded.</h1>
            <p class="pilot-hero-copy">Four fictional businesses. Three real design directions each. One offer that lets an appointment professional own the front door and run the daily request flow from a phone.</p>
          </div>
          <aside class="pilot-note">
            <strong>${esc(creative.offer.price_hypothesis)} · package proposal</strong>
            <p>${esc(creative.offer.promise)}</p>
            ${button(`${publicBase}/package/`, 'See the complete package')}
            ${button(`${publicBase}/template-lab/`, 'Open the Template Lab', true)}
          </aside>
        </div>
      </header>
      <section class="specialist-rail"><div class="shell"><p class="kicker">Not three color swaps</p><div class="specialist-grid">${creative.specialists.map(item => `<article><span>${esc(item.label)}</span><p>${esc(item.job)}</p></article>`).join('')}</div></div></section>
      <main class="shell pilot-grid" id="proofs">${cards}</main>`
  });
}

function emailPage(business) {
  const roomUrl = `${publicBase}/rooms/${business.slug}/`;
  return template({
    title: `Email preview — ${business.name}`,
    description: `Fictional Booked & Branded outreach email for ${business.name}.`,
    className: 'email-page',
    body: `${ribbon()}
      <div class="mail-chrome">
        <div class="mail-toolbar"><span class="mail-dot"></span><span class="mail-dot"></span><span class="mail-dot"></span></div>
        <div class="mail-subject">
          <a class="back-link" href="${publicBase}/">← All four email proofs</a>
          <h1>What if ${esc(possessive(business.name))} booking page looked like its brand?</h1>
          <div class="sender">
            <span class="sender-mark">S</span>
            <span><strong>${esc(creative.shay.email_from)}</strong><small>${esc(creative.shay.title)} · to Demo recipient · No message sent</small></span>
            <small>Today · 2:30 PM</small>
          </div>
        </div>
        <article class="mail-body">
          <div class="mail-brand">FAMTASTIC DESIGNS</div>
          <h2>I built the alternative before asking you to imagine it.</h2>
          <p>Hi there,</p>
          <p>I’m <strong>Shay, FAMtastic Designs’ AI Business Concierge.</strong> I help owners understand what we built, gather the decisions the team needs, and bring Fritz in when price, scope, approval, or launch needs a person.</p>
          <p>For this fictional example, I found ${esc(business.name)} through a public booking-platform profile. Booking may already work—the opportunity is giving clients a place that looks and feels like <em>your</em> business before they choose a service.</p>
          <p>The focus on ${esc(business.specialty)} became the starting point. I coordinated separate shape, typography, message, and reference-image passes so the link below contains three complete visual worlds—not one template in three colors.</p>
          <p>The proposed <strong>${esc(creative.offer.price_hypothesis)} Booked &amp; Branded package</strong> includes:</p>
          <ul>
            <li>a selected custom mobile-ready direction;</li>
            <li>one useful booking path—keep the current link, connect an owner-controlled Google or Cal.com page, or use request-to-book;</li>
            <li>a one-owner phone Booking Desk starter when request-to-book is selected;</li>
            <li>up to 12 services, availability windows, and policy notes;</li>
            <li>their own Cash App or existing payment QR displayed on the site; and</li>
            <li>fresh consent-based testimonials and Shay-guided setup.</li>
          </ul>
          <p><strong>One year of hosting is included.</strong> Normal hosting is $9.99 a month beginning in month 13, and only after separate authorization.</p>
          <p><strong>Your QR. Your account. Your money.</strong> FAMtastic displays the business’s approved Cash App or existing payment QR; we do not process or receive the payment. Payment-processing and optional messaging costs are paid directly by the business to the providers it chooses.</p>
          <p>You can keep your current booking platform while testing the new front door. If the starter does the job, keep it simple. If reminders, deeper calendar setup, multi-staff scheduling, or growth tools would save time or win more appointments, those become optional upgrades—not surprise requirements.</p>
          <a class="mail-cta" href="${roomUrl}">See the 3 worlds I prepared for ${esc(business.name)} →</a>
          <p class="shay-signoff"><span class="shay-orb">S</span><span><strong>Shay</strong><br>${esc(creative.shay.title)}<br><small>with Fritz and the FAMtastic Designs team</small></span></p>
          <div class="mail-footer">
            <p><strong>Fictional proof only:</strong> this email was rendered for the Booked &amp; Branded product test and was not sent. The business, recipient, profile facts, and service details are fictional.</p>
            <p>FAMtastic Designs · 1729 NW St. Lucie West Blvd #1181 · Port Saint Lucie, FL 34986</p>
            <p>A real commercial message would also contain its verified public-source reason and a functioning one-click unsubscribe link.</p>
          </div>
        </article>
      </div>`
  });
}

function roomPage(business) {
  const directions = business.directions.map(direction => {
    const { archetype, businessSystem } = systemFor(business, direction);
    return `
    <article class="direction-card card-${esc(direction.id)}">
      <div class="direction-preview">
        <img src="${directionImage(business, direction)}" alt="Fictional ${esc(business.name)} direction ${esc(direction.id.toUpperCase())} preview" width="1376" height="768">
        <span class="direction-letter">${esc(direction.id)}</span>
      </div>
      <div class="direction-card-body">
        <small>${esc(direction.label)}</small>
        <h2>${esc(direction.name)}</h2>
        <p>${esc(direction.subhead)}</p>
        <ul class="direction-dna"><li>${esc(archetype.type.composition)}</li><li>${esc(archetype.shape.grammar)}</li><li>${esc(businessSystem.motifs.join(' · '))}</li></ul>
        ${button(`${publicBase}/proofs/${business.slug}/${direction.id}/`, 'Open working direction')}
      </div>
    </article>`;
  }).join('');
  return template({
    title: `${business.name} — Three private directions`,
    description: `Three fictional Booked & Branded directions for ${business.name}.`,
    className: 'room-page',
    body: `${ribbon()}
      <main class="shell">
        <header class="room-head">
          <a class="back-link" href="${publicBase}/emails/${business.slug}/">← Return to the email</a>
          <div class="eyebrow">Private concept-room simulation · 3 of 3 ready</div>
          <h1>${esc(business.name)}</h1>
          <p>Same business truth, three materially different compositions. Every direction shows the public site, a starter booking path, phone-friendly owner controls, the business’s own payment QR, and a fresh-testimonial path.</p>
          <div class="room-meta"><span>${esc(business.location)}</span><span>${esc(business.specialty)}</span><span>Fictional demonstration</span></div>
        </header>
        <section class="direction-grid" aria-label="Three design directions">${directions}</section>
        <section class="room-offer">
          <div><h3>Start useful for $199</h3><p>Launch the branded front door, publish up to 12 services, choose one starter booking path, display the business’s own approved payment QR, and get one year of hosting.</p></div>
          <div><h3>Grow when it pays</h3><p>Keep hosting at the normal $9.99 monthly renewal beginning in month 13. Add the $149 Appointment Scheduling setup or other growth tools only when the business is ready to use them.</p></div>
        </section>
        <p class="room-package-link">${button(`${publicBase}/package/`, 'See the complete Booked & Branded package')}</p>
      </main>`
  });
}

function packagePage() {
  const core = creative.offer.core.map(item => `<li>${esc(item)}</li>`).join('');
  const launch = creative.offer.launch_path.map((item, index) => `<li><b>${index + 1}</b><span>${esc(item)}</span></li>`).join('');
  const bookingPaths = creative.offer.booking_paths.map((item, index) => `<article><b>0${index + 1}</b><h3>${esc(item.label)}</h3><p>${esc(item.description)}</p></article>`).join('');
  const upgrades = creative.offer.upgrade_packages.map((item, index) => `<article class="upgrade-step upgrade-${esc(item.id)}"><div><span>0${index + 1}</span><small>${esc(item.status)}</small></div><h3>${esc(item.name)}</h3><strong>${esc(item.price)}</strong><p>${esc(item.outcome)}</p></article>`).join('');
  const growthSignals = creative.offer.growth_signals.map(item => `<li>${esc(item)}</li>`).join('');
  const specialists = creative.specialists.map(item => `<article><span>${esc(item.label)}</span><p>${esc(item.job)}</p></article>`).join('');
  return template({
    title: `${creative.offer.name} — Founding pilot package`,
    description: 'A truthful demonstration of the proposed Booked & Branded website and phone Booking Desk starter for independent appointment professionals.',
    className: 'package-page',
    body: `${ribbon()}
      <main>
        <section class="package-hero">
          <div class="shell package-hero-grid">
            <div>
              <a class="back-link" href="${publicBase}/">← Return to the four-business proof</a>
              <p class="kicker">${esc(creative.offer.status_display)}</p>
              <h1>${esc(creative.offer.headline)}</h1>
              <p class="package-lede">${esc(creative.offer.promise)}</p>
              <div class="package-actions">${button(`${publicBase}/#proofs`, 'See the four proof stories')}${button(`${publicBase}/template-lab/`, 'Open the Template Lab', true)}${button('#package-upgrades', 'See the upgrade path', true)}</div>
            </div>
            <aside class="package-price-card"><small>Proposed starter</small><strong>${esc(creative.offer.price_hypothesis)}</strong><p>One year of hosting included. Keep the site running for the normal $9.99 monthly hosting renewal beginning in month 13. Booking and growth upgrades stay optional.</p></aside>
          </div>
        </section>

        <section class="package-principle"><div class="shell"><p>Start with the smallest system that can help win the next appointment.</p><span>Keep what works. Add power when the extra capability can earn its place.</span></div></section>

        <section class="package-section"><div class="shell package-split"><div><p class="kicker">Included from day one</p><h2>A better front door and a useful next step.</h2><p>The public website gives the business its own visual language, explains services before the client has to ask, and connects each person to a booking path the owner can actually manage.</p></div><ul class="package-checklist">${core}</ul></div></section>

        <section class="package-section package-dark"><div class="shell"><div class="section-head"><div><p class="kicker">Built around the next appointment</p><h2>Look established. Answer less. Book more easily.</h2></div><p>The starter puts service details, policies, booking, the business’s own payment QR, and fresh testimonials in one branded path clients can use from a phone.</p></div><div class="package-desk-grid"><article><b>01</b><h3>Explain</h3><p>Clients see services, pricing, preparation, and policies before sending another DM.</p></article><article><b>02</b><h3>Book</h3><p>Use the current provider, Google, Cal.com, or request-to-book—whichever fits today.</p></article><article><b>03</b><h3>Get paid directly</h3><p>Display the owner’s approved Cash App or existing payment QR. The money goes directly to the business.</p></article><article><b>04</b><h3>Grow</h3><p>Turn completed visits into fresh testimonials and use real activity to choose the next upgrade.</p></article></div><p class="provider-note"><strong>Your QR. Your account. Your money.</strong> FAMtastic does not process, receive, settle, or reconcile the payment. Payment-processing and optional messaging costs are paid directly by the business to its chosen providers.</p></div></section>

        <section class="package-section booking-paths"><div class="shell"><div class="section-head"><div><p class="kicker">One starter, four practical paths</p><h2>Use the booking tool that fits the owner now.</h2></div><p>A personal Google appointment page, Cal.com, the current provider, or FAMtastic request-to-book can sit behind the branded experience. The owner keeps control of provider accounts and chooses the path during setup.</p></div><div class="booking-path-grid">${bookingPaths}</div><p class="provider-note">Provider availability and paid features can change. FAMtastic confirms the selected account, link or embed, privacy settings, and mobile behavior during setup.</p></div></section>

        <section class="package-section"><div class="shell package-split"><div><p class="kicker">A low-risk way to grow</p><h2>No forced overnight switch.</h2><ol class="launch-path">${launch}</ol></div><div class="package-phone"><span class="package-phone-glow"></span><div class="mini-phone"><small>Booked &amp; Branded</small><h3>Today’s chair</h3><p><b>3</b> requests waiting</p><p><b>1</b> reply due</p><span>Confirm · Suggest time · Services</span></div></div></div></section>

        <section class="package-section package-upgrades" id="package-upgrades"><div class="shell"><div class="section-head"><div><p class="kicker">A pipeline, not a one-and-done sale</p><h2>Start cheap. Upgrade from evidence.</h2></div><p>${esc(creative.offer.value_message)}</p></div><div class="upgrade-ladder">${upgrades}</div><div class="growth-signal"><div><p class="kicker">When is it time to add more?</p><h3>Let the business tell us.</h3></div><ul>${growthSignals}</ul></div></div></section>

        <section class="package-section shay-package"><div class="shell package-split"><div><span class="shay-orb package-orb">S</span><p class="kicker">The business face</p><h2>Meet Shay, your AI Business Concierge.</h2><p>Shay explains the proof choices, gathers decisions, keeps setup understandable, and knows when to bring in Fritz or the FAMtastic team. Human authority stays with the team for pricing, scope, approvals, payment, and launch.</p></div><div class="specialist-grid">${specialists}</div></div></section>

        <section class="package-final"><div class="shell"><p class="kicker">Proof before promise</p><h2>Four fictional businesses. Twelve working directions. Four reusable visual families.</h2>${button(`${publicBase}/template-lab/`, 'Open the Template Lab')}</div></section>
      </main>`
  });
}

function templateLabPage() {
  const foundation = [
    ['Your web address', 'A custom domain or an owner-controlled existing domain connected to the new site.'],
    ['One branded forwarding address', 'A customer-approved address such as bookings@yourdomain.com delivers into the inbox the owner already checks. A hosted mailbox and sending as that address remain an upgrade.'],
    ['Contact that works', 'A protected contact form with verified delivery, plus click-to-call, click-to-text, and approved social links.'],
    ['Find the business', 'Location or service area, accurate hours, parking or arrival notes, and a map only when the owner wants one.'],
    ['Services before DMs', 'Services, starting prices, duration, preparation, policies, and what the client should choose next.'],
    ['Proof of the work', 'A responsive, owner-approved gallery and consent-based testimonials—not copied reviews or invented results.'],
    ['Booking that fits today', 'Keep Booksy or another current provider, connect Google or Cal.com, or use FAMtastic request-to-book. No forced overnight switch.'],
    ['Owner-controlled QR', 'Display the business’s approved Cash App or existing payment QR. FAMtastic does not process or receive the payment.'],
    ['Launch quality', 'One year of hosting, SSL, responsive behavior, keyboard access, reduced-motion support, performance checks, and launch QA.']
  ];
  const foundationCards = foundation.map(([title, description], index) => `<article><b>${String(index + 1).padStart(2, '0')}</b><h3>${esc(title)}</h3><p>${esc(description)}</p></article>`).join('');
  const families = creative.template_lab.families.map((family, index) => {
    const business = data.businesses.find(item => item.slug === family.business_slug);
    if (!business) throw new Error(`Missing business for template family ${family.id}.`);
    const materials = family.material_language.map(item => `<span>${esc(item)}</span>`).join('');
    const motifs = family.motif_language.map(item => `<li>${esc(item)}</li>`).join('');
    return `<article class="lab-family lab-family-${esc(family.id)}" style="--family-ink:${esc(business.palette.ink)};--family-paper:${esc(business.palette.paper)};--family-accent:${esc(business.palette.accent)};--family-accent2:${esc(business.palette.accent2)}">
      <div class="lab-family-art">
        <img src="${publicBase}/template-lab/assets/${esc(family.material_asset)}" alt="Generated material study for the fictional ${esc(family.name)} template family" width="1672" height="941">
        <span class="lab-family-number">0${index + 1}</span>
        <div class="lab-material-tags">${materials}</div>
      </div>
      <div class="lab-family-copy">
        <p class="kicker">Reusable family · ${esc(business.operator)}</p>
        <div class="lab-type-lockup"><small>${esc(family.name)}</small><h2>${esc(family.signal)}</h2><span>${esc(business.location)}</span></div>
        <p class="lab-thesis">${esc(family.type_composition)} ${esc(family.module_shape)}</p>
        <div class="lab-family-system">
          <div><small>Motif grammar</small><ul>${motifs}</ul></div>
          <div><small>Adaptation rule</small><p>${esc(family.adaptation_rule)}</p></div>
        </div>
              <div class="lab-family-actions">${business.slug === 'velvet-coil-atelier' ? button(`${publicBase}/wow-lab/velvet-coil-architecture/`, 'Open the Ultra quality study') : ''}${button(`${publicBase}/rooms/${business.slug}/`, 'Open the existing 3-proof room', business.slug === 'velvet-coil-atelier')}${button(`${publicBase}/proofs/${business.slug}/c/#owner-desk`, 'See the phone Booking Desk', true)}</div>
      </div>
    </article>`;
  }).join('');
  const upgrades = creative.template_lab.growth_layer.map((item, index) => `<li><span>${String(index + 1).padStart(2, '0')}</span><p>${esc(item)}</p></li>`).join('');
  return template({
    title: 'Booked & Branded Template Lab — FAMtastic Designs',
    description: 'Four reusable, research-led visual systems and the complete business foundation behind the fictional Booked & Branded pilot.',
    className: 'template-lab-page',
    body: `${ribbon()}
      <main>
        <section class="lab-hero">
          <div class="shell lab-hero-grid">
            <div>
              <a class="back-link" href="${publicBase}/">← Return to the live proof pilot</a>
              <p class="kicker">FAMtastic Template Lab · research first</p>
              <h1>A template should feel <em>hand built.</em><br>The system underneath should feel repeatable.</h1>
              <p>These are not four one-page recolors. Each family has its own material world, typography composition, shape grammar, content rhythm, booking behavior, and rules for adapting to the next real business.</p>
              <div class="lab-hero-actions">${button('#families', 'See the four families')}${button('#foundation', 'See what every client gets', true)}</div>
            </div>
            <aside class="lab-manifesto">
              <span>THE RULE</span>
              <strong>Own the front door.<br>Connect what works.<br>Add what earns.</strong>
              <p>Booksy or another platform can remain the booking engine on day one. FAMtastic gives the business an owned brand, a complete web foundation, and a visible path toward more control.</p>
            </aside>
          </div>
        </section>

        <section class="lab-system-strip"><div class="shell"><div><b>Research</b><span>real specialty, market, client questions, neighborhood, and owner goals</span></div><div><b>Compose</b><span>type, shape, message, texture, imagery, and native business motifs</span></div><div><b>Build</b><span>real HTML, real forms, real links, real accessibility, and phone behavior</span></div><div><b>Grow</b><span>measure what helps, then offer the next useful capability</span></div></div></section>

        <section class="lab-families" id="families"><div class="shell"><header class="lab-section-head"><p class="kicker">Four visual engines</p><h2>Not color palettes.<br><em>Business-native worlds.</em></h2><p>The generated artwork supplies atmosphere and material depth. Native HTML and CSS still own every readable word, button, price, form, map, and operating state.</p></header>${families}</div></section>

        <section class="lab-foundation" id="foundation"><div class="shell"><header class="lab-section-head"><p class="kicker">The $199 foundation · proposed Booked & Branded scope</p><h2>Booking is the value add.<br><em>The website basics do not disappear.</em></h2><p>The starter must already feel like a serious business website. The booking path, payment QR, and phone workflow make it more useful; they do not replace identity, contact, location, services, content, or trust.</p></header><div class="lab-foundation-grid">${foundationCards}</div></div></section>

        <section class="lab-platform-bridge"><div class="shell lab-bridge-grid"><div><p class="kicker">Meet owners where they are</p><h2>Do not demand a breakup before the first date.</h2><p>The branded site can send clients into the owner’s current Booksy or other provider account while the owner keeps the calendar and client workflow they know. The website immediately improves the story around that booking link.</p><ol><li><b>Now</b><span>Branded home + current booking link + domain + forwarding address + contact + map + services + gallery + QR.</span></li><li><b>Next</b><span>Watch real questions, clicks, and appointment behavior. Improve copy, services, follow-up, and proof.</span></li><li><b>Later</b><span>Move into deeper scheduling, reminders, CRM, analytics, local SEO, or automation only when it creates value.</span></li></ol></div><div class="lab-owner-phone" aria-label="Static phone owner dashboard concept"><div class="lab-phone-top"><span>9:41</span><span>FAMtastic Desk</span><span>•••</span></div><small>Good morning, Jordan</small><h3>Your business, from your phone.</h3><div class="lab-phone-metric"><b>4</b><span>booking taps this week</span></div><div class="lab-phone-row"><span>New contact</span><strong>2</strong></div><div class="lab-phone-row"><span>Review to approve</span><strong>1</strong></div><div class="lab-phone-row"><span>Top service</span><strong>Signature Cut</strong></div><div class="lab-phone-actions"><span>Update hours</span><span>Edit services</span><span>View requests</span><span>Share QR</span></div><p>Demonstration only · No real account or activity</p></div></div></section>

        <section class="lab-growth"><div class="shell lab-growth-grid"><div><p class="kicker">The customer-growth engine</p><h2>Give first.<br>Prove value.<br><em>Earn the upgrade.</em></h2><p>The starter is intentionally affordable and useful. Each upgrade answers a real signal from the business instead of creating anxiety on day one.</p></div><ol>${upgrades}</ol></div></section>

        <section class="lab-shay"><div class="shell lab-shay-grid"><div><span class="shay-orb package-orb">S</span><p class="kicker">The FAMtastic business face</p><h2>Shay explains the value without overselling the machinery.</h2><p>Shay is FAMtastic Designs’ AI Business Concierge. She helps the owner compare proofs, understand what is included, collect setup choices, and see the next useful step. Fritz and the FAMtastic team retain authority for price, scope, approval, payment, and launch.</p></div><aside><small>Future motion layer</small><strong>HyperFrames can animate the approved motif—not invent the business.</strong><p>A short texture loop, reveal, or social explainer can become a later campaign asset. Reduced-motion-safe static design remains the default, and motion never changes pricing or publishes itself.</p></aside></div></section>

        <section class="lab-final"><div class="shell"><p class="kicker">No work thrown away</p><h2>The four current proofs become the first training examples for a reusable niche system.</h2><div>${button(`${publicBase}/`, 'Return to the four proof stories')}${button(`${publicBase}/package/`, 'Review the complete package', true)}</div></div></section>
      </main>`
  });
}

function wowLabPage() {
  const business = data.businesses.find(item => item.slug === 'velvet-coil-atelier');
  if (!business) throw new Error('Velvet Coil Atelier is missing from the pilot data.');
  return template({
    title: 'Velvet Coil Atelier — Every Coil Is Architecture',
    description: 'An additive Ultra FAMtastic quality study for the fictional Velvet Coil Atelier.',
    className: 'wow-page',
    body: `${ribbon()}
      <main>
        <nav class="wow-nav" aria-label="Velvet Coil primary navigation">
          <a class="wow-wordmark" href="#top"><span>VC</span><b>Velvet Coil<br>Atelier</b></a>
          <div><a href="#atlas">Texture atlas</a><a href="#blueprint">Consultation</a><a href="#atelier-console">Atelier console</a><a class="wow-nav-cta" href="#reserve">Reserve the ritual</a></div>
        </nav>

        <section class="wow-hero" id="top">
          <img src="${publicBase}/wow-lab/assets/velvet-coil-architecture-hero.webp" alt="Fictional editorial artwork of a natural-hair stylist and client surrounded by sculptural coil forms" width="1672" height="941">
          <div class="wow-hero-noise" aria-hidden="true"></div>
          <div class="wow-hero-copy">
            <p>Fort Pierce · Texture-first hair care</p>
            <h1><span>Every coil</span><em>is architecture.</em></h1>
            <div class="wow-hero-foot"><p>Healthy shape is designed—not forced. A private atelier for curls, coils, silk presses, and care plans that respect the structure already there.</p><a href="#atlas">Enter the texture atlas <b>↘</b></a></div>
          </div>
          <aside class="wow-hero-index"><span>ATELIER / 01</span><span>COIL / CARE / FORM</span><span>27.45° N · FORT PIERCE</span></aside>
        </section>

        <section class="wow-thesis">
          <div class="wow-orbit" aria-hidden="true"><i></i><i></i><i></i><b>VC</b></div>
          <p class="wow-section-number">01 / PHILOSOPHY</p>
          <h2>Hair is not a problem<br>to solve. <em>It is a form<br>to understand.</em></h2>
          <p class="wow-thesis-copy">This concept turns the service list into a living material archive. Clients begin with their texture, goals, and maintenance rhythm—then choose the ritual that fits.</p>
        </section>

        <section class="wow-atlas" id="atlas">
          <header><p class="wow-section-number">02 / THE TEXTURE ATLAS</p><h2>Choose your<br><em>structure.</em></h2><p>Three starting rituals. Each one can open into a consultation, preparation notes, duration, and an owner-controlled booking path.</p></header>
          <div class="wow-atlas-grid">
            <article class="wow-service wow-service-main"><span>01</span><div class="wow-contour" aria-hidden="true"></div><small>120 MIN · $120</small><h3>Signature<br>Curl Session</h3><p>Shape, hydration, definition, and a care map made for the week after the chair.</p><a href="#reserve">Request this ritual ↗</a></article>
            <article class="wow-service wow-service-light"><span>02</span><small>105 MIN · $95</small><h3>Silk Press<br>Ritual</h3><p>Movement and polish with the texture conversation kept intact.</p><a href="#reserve">Request this ritual ↗</a></article>
            <article class="wow-service wow-service-copper"><span>03</span><small>75 MIN · $75</small><h3>Coil Care<br>Reset</h3><p>A focused return to moisture, definition, and a simpler home rhythm.</p><a href="#reserve">Request this ritual ↗</a></article>
            <aside><b>Not sure where to begin?</b><p>Start with the Blueprint. The right appointment should follow the texture—not a generic menu.</p><a href="#blueprint">Build your consultation blueprint →</a></aside>
          </div>
        </section>

        <section class="wow-blueprint" id="blueprint">
          <div class="wow-blueprint-head"><p class="wow-section-number">03 / THE CONSULTATION BLUEPRINT</p><h2>Before the chair,<br>we draw the <em>plan.</em></h2></div>
          <div class="wow-blueprint-grid">
            <ol>
              <li><b>01</b><div><span>Texture now</span><p>Tell us what your hair is doing today—not what a category says it should do.</p></div></li>
              <li><b>02</b><div><span>Shape next</span><p>Share the result, feeling, or reference you want to move toward.</p></div></li>
              <li><b>03</b><div><span>Rhythm after</span><p>Choose the upkeep that fits your real time, budget, and routine.</p></div></li>
            </ol>
            <form class="wow-plan" aria-label="Fictional consultation blueprint">
              <div class="wow-plan-top"><span>VELVET COIL / NEW GUEST</span><span>FORM 03—A</span></div>
              <label>What is your texture asking for?<textarea readonly>More definition with a shape I can maintain between visits.</textarea></label>
              <div><label>Last chemical service<input value="None in the last year" readonly></label><label>Ideal visit rhythm<input value="Every 8–10 weeks" readonly></label></div>
              <label>Upload reference photos<span class="wow-upload">＋ Add up to 3 images</span></label>
              <span class="wow-plan-submit" aria-disabled="true">Create my care blueprint <b>↗</b></span>
              <small>Demonstration only · No details or files are submitted</small>
            </form>
          </div>
        </section>

        <section class="wow-care-lab">
          <div class="wow-care-word" aria-hidden="true">CARE</div>
          <div class="wow-care-copy"><p class="wow-section-number">04 / THE CARE LAB</p><h2>Luxury is knowing<br>what happens <em>next.</em></h2><p>Preparation, arrival notes, policies, and the care plan live beside the booking—not in scattered DMs.</p></div>
          <div class="wow-care-notes">
            <article><span>A / PREP</span><b>Arrive detangled unless your ritual says otherwise.</b><p>Owner-approved preparation notes appear with the chosen service.</p></article>
            <article><span>B / ARRIVAL</span><b>Private studio. Confirmed requests receive exact arrival details.</b><p>Location, map, parking, and access instructions can be managed without exposing a private address too early.</p></article>
            <article><span>C / CONTINUITY</span><b>Leave with a care map, not a product lecture.</b><p>The site can carry a simple owner-written routine and invite the next appointment.</p></article>
          </div>
        </section>

        <section class="wow-console" id="atelier-console">
          <div class="wow-console-copy"><p class="wow-section-number">05 / ATELIER CONSOLE</p><h2>The artistry is visible.<br><em>The system stays quiet.</em></h2><p>From her phone, the owner can review new requests, confirm consultations, update services and hours, approve contact or gallery changes, and share her own payment QR. FAMtastic does not process, receive, settle, or reconcile the payment.</p><div class="wow-foundation-list"><span>Custom domain</span><span>Branded forwarding email</span><span>Contact form</span><span>Service area or map</span><span>Current booking link</span><span>Owner’s payment QR</span></div></div>
          <div class="wow-console-device" aria-label="Static fictional phone owner console">
            <div class="wow-device-top"><span>9:41</span><b>VC / DESK</b><span>•••</span></div>
            <p>THURSDAY / ATELIER 04</p><h3>The chair is<br>in rhythm.</h3>
            <div class="wow-device-signal"><b>3</b><span>requests need your eye</span></div>
            <div class="wow-device-row"><i>AR</i><span><b>A. Reed</b><small>Curl Session · 11:00</small></span><em>Review</em></div>
            <div class="wow-device-row"><i>TN</i><span><b>T. Neal</b><small>Silk Press · 1:30</small></span><em>Ready</em></div>
            <div class="wow-device-actions"><span>Update hours</span><span>Edit rituals</span><span>Share QR</span></div>
            <small>Fictional console · No real account or activity</small>
          </div>
        </section>

        <section class="wow-reserve" id="reserve">
          <p class="wow-section-number">06 / RESERVE THE RITUAL</p>
          <h2>Care is<br><em>the luxury.</em></h2>
          <div class="wow-reserve-grid">
            <div><p>First visit? Begin with a short texture consultation. Returning guest? Continue to the calendar the atelier already uses.</p><span class="wow-reserve-button" aria-disabled="true">Request a consultation <b>↗</b></span><span class="wow-reserve-link" aria-disabled="true">Continue to current calendar →</span></div>
            <div class="wow-chair-card"><span class="wow-faux-qr" aria-hidden="true"></span><p><b>OWNER’S CHAIR CARD</b><br>Approved Cash App or existing payment QR can live here. Payment goes directly to the business.</p></div>
          </div>
          <footer><div><b>Velvet Coil Atelier</b><span>Fort Pierce, Florida · Wed–Sun · By confirmed request</span></div><div><span>velvetcoil.example</span><span>bookings@velvetcoil.example → owner’s existing inbox</span><span>Contact · Service area · Arrival notes</span></div><a href="${publicBase}/template-lab/">Return to the Template Lab ↗</a></footer>
        </section>
      </main>`
  });
}

function requestCards(business) {
  return business.requests.map((request, index) => {
    const actions = index === 0
      ? '\n      <div class="request-actions"><span>Confirm</span><span>Suggest time</span></div>'
      : '';
    return `
    <div class="request-card">
      <div class="request-top">
        <span class="request-avatar">${esc(request.initials)}</span>
        <span><strong>${esc(request.name)}</strong><small>${esc(request.service)} · ${esc(request.time)}</small></span>
        <span class="status-chip">${esc(request.status)}</span>
      </div>${actions}
    </div>`;
  }).join('');
}

function serviceCards(business) {
  return business.services.map(service => `
    <article class="service-card">
      <span>${esc(service.time)}</span>
      <h3>${esc(service.name)}</h3>
      <div class="service-price"><b>${esc(service.price)}</b><span>Request →</span></div>
    </article>`).join('');
}

function proofPage(business, direction) {
  const other = business.directions.filter(item => item.id !== direction.id);
  const { archetype, businessSystem } = systemFor(business, direction);
  const ownerDeskLabel = direction.id === 'c' ? 'How the flow works' : 'See the Booking Desk';
  return template({
    title: `${business.name} — ${direction.name}`,
    description: `${direction.label} Booked & Branded demonstration for ${business.name}.`,
    className: `proof-page dir-${direction.id}`,
    body: `${ribbon()}
      <div style="${vars(business, direction)}">
        <nav class="proof-nav" aria-label="Primary">
          <a class="brand-lockup" href="${publicBase}/rooms/${business.slug}/"><span class="brand-mark">${esc(business.mark)}</span><span>${esc(business.name)}</span></a>
          <div class="proof-nav-links"><a href="#services">Services</a><a href="#owner-desk">Owner desk</a>${button('#request', archetype.message.cta)}</div>
        </nav>
        <main>
          <section class="proof-hero">
            <div class="proof-hero-copy">
              <span class="overline">${esc(direction.name)} · ${esc(business.location)}</span>
              <h1 data-echo="${esc(direction.headline)}">${esc(direction.headline)}</h1>
              <p>${esc(direction.subhead)}</p>
              <div class="proof-actions">${button('#request', archetype.message.cta)}${button('#owner-desk', ownerDeskLabel, true)}</div>
            </div>
            <div class="proof-hero-media">
              <img src="${directionImage(business, direction)}" alt="Fictional ${esc(business.operator)} in the ${esc(direction.name)} art direction for ${esc(business.name)}" width="1376" height="768">
              <div class="proof-hero-tag"><strong>${esc(business.hours)}</strong><br>${esc(business.policy)}</div>
            </div>
          </section>

          <section class="creative-dna-strip" aria-label="Direction creative system">
            <div><small>Type Director</small><strong>${esc(archetype.type.display_family)} × ${esc(archetype.type.body_family)}</strong><span>${esc(archetype.type.composition)}</span></div>
            <div><small>Shape Director</small><strong>${esc(archetype.name)}</strong><span>${esc(archetype.shape.grammar)}</span></div>
            <div><small>Message Director</small><strong>${esc(archetype.message.tone)}</strong><span>${esc(archetype.message.argument)}</span></div>
            <div><small>Native motifs</small><strong>${esc(businessSystem.motifs.join(' · '))}</strong><span>Original symbolic language, never a copied platform identity.</span></div>
          </section>

          <section class="proof-section" id="services">
            <div class="shell">
              <div class="section-head"><div><p class="kicker">Choose with confidence</p><h2>Services that explain themselves.</h2></div><p>Each service gives the client the price, time, preparation, and next step before they ask. The owner controls what is visible from the phone.</p></div>
              <div class="service-grid">${serviceCards(business)}</div>
            </div>
          </section>

          <section class="proof-section experience-band" id="owner-desk">
            <div class="shell experience-grid">
              <div class="experience-copy">
                <p class="kicker">The operating difference</p>
                <h2>The site and the workday share one truth.</h2>
                <p>Start with the useful core: show services clearly, choose a booking path that fits the owner today, see new requests when request-to-book is selected, display the business’s own payment QR, and invite a fresh testimonial after completion. Calendar depth, reminders, multi-staff scheduling, and other automation remain optional upgrades for the moment they can save time or unlock more appointments.</p>
                <div class="flow-list">
                  <div class="flow-item"><b>1</b><span><strong>Client requests</strong><br>Service, preferred time, and essential preparation context.</span></div>
                  <div class="flow-item"><b>2</b><span><strong>Owner decides</strong><br>Confirm, propose another time, or decline without losing the request.</span></div>
                  <div class="flow-item"><b>3</b><span><strong>Business gets paid directly</strong><br>Show the operator’s own Cash App or existing payment QR. The payment stays between the client, business, and chosen provider.</span></div>
                </div>
              </div>
              <div class="phone-wrap" aria-label="Static phone Booking Desk demonstration">
                <div class="phone">
                  <div class="phone-screen">
                    <div class="phone-status"><span>9:41</span><span>Booked &amp; Branded</span><span>•••</span></div>
                    <div class="desk-head"><small>${esc(business.name)}</small><h3>Today’s chair</h3></div>
                    <div class="desk-tabs"><span>Requests</span><span>Schedule</span><span>Services</span><span>Reviews</span></div>
                    <div class="request-list">${requestCards(business)}</div>
                    <div class="desk-summary"><div><b>3</b><small>open requests</small></div><div><b>1</b><small>reply due</small></div></div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="proof-section">
            <div class="shell">
              <div class="section-head"><div><p class="kicker">Your QR. Your account. Your money.</p><h2>Let clients scan the payment method you already use.</h2></div><p>The business supplies and approves its own Cash App or existing payment QR. This demonstration QR cannot be scanned or paid.</p></div>
              <div class="qr-row"><div class="demo-qr" aria-label="Decorative non-scannable demo QR"></div><div><h3>FAMtastic displays it. The business gets paid directly.</h3><p>FAMtastic does not process, receive, settle, or reconcile the payment. Payment-processing and optional messaging costs are paid directly by the business to its chosen providers.</p></div></div>
            </div>
          </section>

          <section class="proof-section">
            <div class="shell">
              <div class="section-head"><div><p class="kicker">Build an owned reputation</p><h2>Let the good work keep working.</h2></div><p>Keep existing public review links visible while inviting completed clients to leave fresh, permission-based testimonials here. The owner can moderate privacy and abuse without filtering out honest negative feedback.</p></div>
              <div class="review-grid"><article class="review-card"><span class="stars">★★★★★</span><p>“Sample placement: clear service details and an easy confirmation made the whole visit feel organized.”</p><small>Fictional client · Demonstration copy</small></article><article class="review-card"><span class="stars">★★★★★</span><p>“Sample placement: the result felt personal—and the booking experience finally matched it.”</p><small>Fictional client · Demonstration copy</small></article></div>
            </div>
          </section>

          <section class="proof-section" id="request">
            <div class="shell">
              <div class="section-head"><div><p class="kicker">Choose the booking path</p><h2>Make the next appointment easy.</h2></div><p>This proof demonstrates request-to-book. A live setup can instead link or embed an owner-controlled Google appointment page, Cal.com page, or current provider after the selected path is reviewed on desktop and phone.</p></div>
              <form class="booking-form" aria-label="Non-submitting demonstration booking form">
                <label>Service<select><option>${esc(business.services[0].name)}</option><option>${esc(business.services[1].name)}</option><option>${esc(business.services[2].name)}</option></select></label>
                <label>Preferred day<input type="text" value="Saturday" readonly></label>
                <label>Preferred time<input type="text" value="11:30 AM" readonly></label>
                <label>Contact method<select><option>Text me</option><option>Email me</option></select></label>
                <label class="wide">Anything the owner should know?<input type="text" value="First visit — looking for a consultation." readonly></label>
                <span class="wide button" aria-disabled="true">Demonstration only — no request submitted</span>
              </form>
            </div>
          </section>
        </main>
        <footer class="proof-footer"><div class="shell proof-footer-grid"><div><strong>${esc(business.name)}</strong><br><span>${esc(business.location)} · ${esc(business.hours)}</span></div><div>${button(`${publicBase}/rooms/${business.slug}/`, 'Compare all 3 directions', true)}</div></div></footer>
      </div>`
  });
}

async function write(relativePath, contents) {
  const destination = join(publicRoot, relativePath);
  await mkdir(dirname(destination), { recursive: true });
  await writeFile(destination, contents);
}

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const absolute = join(directory, entry.name);
    if (entry.isDirectory()) files.push(...await walk(absolute));
    else files.push(absolute);
  }
  return files;
}

function sha256(buffer) {
  return createHash('sha256').update(buffer).digest('hex');
}

for (const generated of ['emails', 'rooms', 'proofs', 'package']) {
  await rm(join(publicRoot, generated), { recursive: true, force: true });
}

await write('index.html', pilotIndex());
await write('package/index.html', packagePage());
await write('template-lab/index.html', templateLabPage());
await write('wow-lab/velvet-coil-architecture/index.html', wowLabPage());

for (const business of data.businesses) {
  await write(`emails/${business.slug}/index.html`, emailPage(business));
  await write(`rooms/${business.slug}/index.html`, roomPage(business));
  for (const direction of business.directions) {
    await write(`proofs/${business.slug}/${direction.id}/index.html`, proofPage(business, direction));
  }
}

const promptManifest = existsSync(generatedPromptManifestPath)
  ? JSON.parse(await readFile(generatedPromptManifestPath, 'utf8'))
  : {
      schema: 'famtastic.booked-branded-reference-prompts.v1',
      request_id: creative.revision_id,
      provider: 'google-gemini-api',
      api: 'interactions',
      model: creative.image_rules.model,
      status: 'planned_not_executed',
      prompts: data.businesses.flatMap(business => business.directions.map(direction => ({
        business_slug: business.slug,
        direction_id: direction.id,
        direction_name: direction.name,
        reference_path: `frontend/public/showcase/booked-and-branded-pilot/assets/${business.image}`,
        image_direction: creative.archetypes[direction.id].image_direction,
        scene: creative.businesses[business.slug].scenes[direction.id]
      })))
    };
await write('image-prompts.json', JSON.stringify(promptManifest, null, 2) + '\n');

const artifactPaths = (await walk(publicRoot))
  .filter(path => !path.endsWith('build-dna.json'))
  .sort();
const artifacts = [];
for (const absolute of artifactPaths) {
  const info = await stat(absolute);
  const contents = await readFile(absolute);
  artifacts.push({
    path: relative(repositoryRoot, absolute),
    bytes: info.size,
    sha256: sha256(contents),
    role: /\.(?:avif|jpe?g|png|webp)$/i.test(absolute) ? 'generated-image-final' : absolute.endsWith('.html') ? 'customer-facing-static-proof' : 'proof-support'
  });
}

const generatedReceipt = existsSync(generationReceiptPath)
  ? JSON.parse(await readFile(generationReceiptPath, 'utf8'))
  : null;
const materialReceipt = existsSync(materialReceiptPath)
  ? JSON.parse(await readFile(materialReceiptPath, 'utf8'))
  : null;
const wowReceipt = existsSync(wowReceiptPath)
  ? JSON.parse(await readFile(wowReceiptPath, 'utf8'))
  : null;
const revision = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: repositoryRoot, encoding: 'utf8' }).trim();
const createdAt = new Date().toISOString();
const localStage = (stage_id, capability, outputs = []) => ({
  stage_id,
  capability,
  attempt: 1,
  execution: {
    provider: { id: 'famtastic-local-builder' },
    model: { status: 'not_applicable' },
    timing: { status: 'not_metered' },
    cost: { status: 'not_applicable', amount_usd: 0 }
  },
  result: { status: 'completed', outputs }
});
const buildDna = {
  schema: 'famtastic.build-dna.v1',
  build_id: creative.revision_id,
  classification: 'fictional-product-demonstration',
  created_at: createdAt,
  repository: {
    name: 'FAMtastic',
    revision,
    worktree: 'booked-branded-four-proof-pilot',
    revision_status: 'source_revision_before_artifact_commit'
  },
  recipe: {
    routine: 'website_proof.generate.v1',
    version: 'booked-branded.v5',
    build_class: 'medium',
    offer_status: creative.offer.status,
    public_base: canonicalBase,
    direction_system: Object.values(creative.archetypes).map(item => item.name)
  },
  run: {
    source_lane: 'famtastic-owned-product-demonstration',
    business_count: data.businesses.length,
    directions_per_business: 3,
    email_sent: false,
    payment_enabled: false,
    payment_processing_enabled: false,
    customer_data_used: false,
    commerce_sku_activated: false,
    booking_provider_connected: false,
    booking_upgrade_activated: false
  },
  stages: [
    localStage('offer-and-shay', 'offer-positioning-and-concierge-boundary', ['creative-system.json', 'package/index.html']),
    {
      stage_id: 'booking-option-research', capability: 'official-booking-option-validation', attempt: 1,
      execution: { provider: { id: 'official-provider-documentation' }, model: { status: 'not_applicable' }, timing: { status: 'reported', completed_at: createdAt }, cost: { status: 'not_applicable', amount_usd: 0 } },
      result: { status: 'completed', verified_on: '2026-08-27', outputs: creative.offer.booking_option_research.map(item => item.source) }
    },
    localStage('shape-direction', 'shape-composition-system', Object.keys(creative.archetypes)),
    localStage('type-direction', 'typographic-composition-system', Object.keys(creative.archetypes)),
    localStage('message-direction', 'proof-message-system', Object.keys(creative.archetypes)),
    {
      stage_id: 'template-material-studies', capability: 'reference-led-material-system-generation', attempt: 1,
      execution: {
        provider: { id: materialReceipt?.provider || 'openai-imagegen-builtin' },
        model: { status: materialReceipt?.model_status || 'provider_did_not_report' },
        timing: { status: materialReceipt ? 'reported' : 'not_started', completed_at: materialReceipt?.completed_at },
        cost: { status: materialReceipt?.cost_status || 'provider_did_not_report' }
      },
      result: { status: materialReceipt ? 'completed' : 'planned', selected_image_count: materialReceipt?.artifacts?.length || 0, outputs: materialReceipt?.artifacts?.map(item => item.path) || [] }
    },
    {
      stage_id: 'ultra-benchmark-hero', capability: 'high-concept-reference-led-hero-generation', attempt: 1,
      execution: {
        provider: { id: wowReceipt?.provider || 'openai-imagegen-builtin' },
        model: { status: wowReceipt?.model_status || 'provider_did_not_report' },
        timing: { status: wowReceipt ? 'reported' : 'not_started', completed_at: wowReceipt?.completed_at },
        cost: { status: wowReceipt?.cost_status || 'provider_did_not_report' }
      },
      result: { status: wowReceipt ? 'completed' : 'planned', selected_image_count: wowReceipt?.selected_image_count || 0, artifact_count: wowReceipt?.artifacts?.length || 0, outputs: wowReceipt?.artifacts?.map(item => item.path) || [] }
    },
    {
      stage_id: 'gemini-reference-images',
      capability: 'reference-led-image-generation',
      attempt: 1,
      execution: {
        provider: { id: 'google-gemini-api', api: 'interactions' },
        model: { id: creative.image_rules.model, status: generatedReceipt ? 'reported' : 'planned' },
        timing: generatedReceipt ? { status: 'reported', started_at: generatedReceipt.started_at, completed_at: generatedReceipt.completed_at, duration_ms: Date.parse(generatedReceipt.completed_at) - Date.parse(generatedReceipt.started_at) } : { status: 'not_started' },
        cost: generatedReceipt ? { status: generatedReceipt.cost_status, amount_usd: generatedReceipt.estimated_cost_usd, ceiling_usd: generatedReceipt.cost_ceiling_usd } : { status: 'planned_estimate', amount_usd: creative.image_rules.estimated_total_usd, ceiling_usd: 1 }
      },
      result: { status: generatedReceipt ? 'completed' : 'planned', provider_generation_count: generatedReceipt?.provider_generation_count || 0, selected_image_count: generatedReceipt?.image_count || 0, outputs: generatedReceipt?.artifacts.map(item => item.filename) || [] }
    },
    localStage('static-construction', 'static-proof-construction', ['index.html', 'package/index.html', 'template-lab/index.html', 'wow-lab/velvet-coil-architecture/index.html', '4 emails', '4 rooms', '12 baseline proof pages']),
    {
      stage_id: 'browser-qa', capability: 'responsive-browser-qa', attempt: 1,
      execution: { provider: { id: 'playwright-local' }, model: { status: 'not_applicable' }, timing: { status: 'pending' }, cost: { status: 'not_applicable', amount_usd: 0 } },
      result: { status: 'pending' }
    },
    {
      stage_id: 'visual-review', capability: 'primary-visual-review', attempt: 1,
      execution: { provider: { id: 'codex-visual-review' }, model: { status: 'not_applicable' }, timing: { status: 'pending' }, cost: { status: 'not_applicable', amount_usd: 0 } },
      result: { status: 'pending', independent_review: 'reserved_for_owner' }
    }
  ],
  artifacts,
  retrieval: {
    filesystem: { status: 'available', root: 'frontend/public/showcase/booked-and-branded-pilot' },
    database: { status: 'not_registered', reason: 'Static fictional showcase; no prospect, customer, proof, or Commerce record.' },
    site_studio: { status: 'contract_ready_not_imported', source: 'frontend/public/showcase/booked-and-branded-pilot/creative-system.json#offer.site_studio_contract', reason: 'Vendor-neutral booking modes and upgrade tiers are encoded for a later Site Studio build translation; no Studio import or provider connection occurred.' }
  },
  integrity: { artifact_hash_algorithm: 'sha256', credential_values_retained: false }
};

await write('build-dna.json', JSON.stringify(buildDna, null, 2) + '\n');
console.log(`Built ${data.businesses.length} email previews, ${data.businesses.length} concept rooms, and ${data.businesses.length * 3} proof directions.`);
console.log(`Evidence: ${join(publicRoot, 'build-dna.json')}`);
