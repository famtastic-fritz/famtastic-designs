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
const generatedImageRoot = join(publicRoot, 'assets/directions');
const generationReceiptPath = join(generatedImageRoot, 'generation-receipt.json');
const generatedPromptManifestPath = join(generatedImageRoot, 'prompt-manifest.json');

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
  <link rel="stylesheet" href="${publicBase}/styles.css">
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
      <img class="pilot-card-image" src="${publicBase}/assets/${esc(business.image)}" alt="Fictional editorial hero created for ${esc(business.name)}" width="1672" height="941"${index === 0 ? '' : ' loading="lazy"'}>
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
            <li>a business-owned payment/QR handoff; and</li>
            <li>fresh consent-based testimonials and Shay-guided setup.</li>
          </ul>
          <p><strong>One year of hosting is included.</strong> Normal hosting is $9.99 a month beginning in month 13, and only after separate authorization.</p>
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
          <p>Same business truth, three materially different compositions. Every direction shows the public site, a starter booking path, phone-friendly owner controls, business-owned payment/QR handoff, and a fresh-testimonial path.</p>
          <div class="room-meta"><span>${esc(business.location)}</span><span>${esc(business.specialty)}</span><span>Fictional demonstration</span></div>
        </header>
        <section class="direction-grid" aria-label="Three design directions">${directions}</section>
        <section class="room-offer">
          <div><h3>Start useful for $199</h3><p>Launch the branded front door, publish up to 12 services, choose one starter booking path, connect a business-owned payment destination, and get one year of hosting.</p></div>
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
              <div class="package-actions">${button(`${publicBase}/#proofs`, 'See the four proof stories')}${button('#package-upgrades', 'See the upgrade path', true)}</div>
            </div>
            <aside class="package-price-card"><small>Proposed starter</small><strong>${esc(creative.offer.price_hypothesis)}</strong><p>One year of hosting included. Keep the site running for the normal $9.99 monthly hosting renewal beginning in month 13. Booking and growth upgrades stay optional.</p></aside>
          </div>
        </section>

        <section class="package-principle"><div class="shell"><p>Start with the smallest system that can help win the next appointment.</p><span>Keep what works. Add power when the extra capability can earn its place.</span></div></section>

        <section class="package-section"><div class="shell package-split"><div><p class="kicker">Included from day one</p><h2>A better front door and a useful next step.</h2><p>The public website gives the business its own visual language, explains services before the client has to ask, and connects each person to a booking path the owner can actually manage.</p></div><ul class="package-checklist">${core}</ul></div></section>

        <section class="package-section package-dark"><div class="shell"><div class="section-head"><div><p class="kicker">Built around the next appointment</p><h2>Look established. Answer less. Book more easily.</h2></div><p>The starter puts service details, policies, booking, payment direction, and fresh testimonials in one branded path clients can use from a phone.</p></div><div class="package-desk-grid"><article><b>01</b><h3>Explain</h3><p>Clients see services, pricing, preparation, and policies before sending another DM.</p></article><article><b>02</b><h3>Book</h3><p>Use the current provider, Google, Cal.com, or request-to-book—whichever fits today.</p></article><article><b>03</b><h3>Collect</h3><p>Send clients to the owner’s reviewed Square, Stripe, or Cash App Business destination.</p></article><article><b>04</b><h3>Grow</h3><p>Turn completed visits into fresh testimonials and use real activity to choose the next upgrade.</p></article></div></div></section>

        <section class="package-section booking-paths"><div class="shell"><div class="section-head"><div><p class="kicker">One starter, four practical paths</p><h2>Use the booking tool that fits the owner now.</h2></div><p>A personal Google appointment page, Cal.com, the current provider, or FAMtastic request-to-book can sit behind the branded experience. The owner keeps control of provider accounts and chooses the path during setup.</p></div><div class="booking-path-grid">${bookingPaths}</div><p class="provider-note">Provider availability and paid features can change. FAMtastic confirms the selected account, link or embed, privacy settings, and mobile behavior during setup.</p></div></section>

        <section class="package-section"><div class="shell package-split"><div><p class="kicker">A low-risk way to grow</p><h2>No forced overnight switch.</h2><ol class="launch-path">${launch}</ol></div><div class="package-phone"><span class="package-phone-glow"></span><div class="mini-phone"><small>Booked &amp; Branded</small><h3>Today’s chair</h3><p><b>3</b> requests waiting</p><p><b>1</b> deposit due</p><span>Confirm · Suggest time · Services</span></div></div></div></section>

        <section class="package-section package-upgrades" id="package-upgrades"><div class="shell"><div class="section-head"><div><p class="kicker">A pipeline, not a one-and-done sale</p><h2>Start cheap. Upgrade from evidence.</h2></div><p>${esc(creative.offer.value_message)}</p></div><div class="upgrade-ladder">${upgrades}</div><div class="growth-signal"><div><p class="kicker">When is it time to add more?</p><h3>Let the business tell us.</h3></div><ul>${growthSignals}</ul></div></div></section>

        <section class="package-section shay-package"><div class="shell package-split"><div><span class="shay-orb package-orb">S</span><p class="kicker">The business face</p><h2>Meet Shay, your AI Business Concierge.</h2><p>Shay explains the proof choices, gathers decisions, keeps setup understandable, and knows when to bring in Fritz or the FAMtastic team. Human authority stays with the team for pricing, scope, approvals, payment, and launch.</p></div><div class="specialist-grid">${specialists}</div></div></section>

        <section class="package-final"><div class="shell"><p class="kicker">Proof before promise</p><h2>Four fictional businesses. Twelve working directions. One reusable product thesis.</h2>${button(`${publicBase}/#proofs`, 'Open the four-business showcase')}</div></section>
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
                <p>Start with the useful core: show services clearly, choose a booking path that fits the owner today, see new requests when request-to-book is selected, send a business-owned deposit link, and invite a fresh testimonial after completion. Calendar depth, reminders, multi-staff scheduling, and other automation remain optional upgrades for the moment they can save time or unlock more appointments.</p>
                <div class="flow-list">
                  <div class="flow-item"><b>1</b><span><strong>Client requests</strong><br>Service, preferred time, and essential preparation context.</span></div>
                  <div class="flow-item"><b>2</b><span><strong>Owner decides</strong><br>Confirm, propose another time, or decline without losing the request.</span></div>
                  <div class="flow-item"><b>3</b><span><strong>Business gets paid</strong><br>Send the operator’s Square, Stripe, or Cash App Business link—never FAMtastic’s merchant account.</span></div>
                </div>
              </div>
              <div class="phone-wrap" aria-label="Static phone Booking Desk demonstration">
                <div class="phone">
                  <div class="phone-screen">
                    <div class="phone-status"><span>9:41</span><span>Booked &amp; Branded</span><span>•••</span></div>
                    <div class="desk-head"><small>${esc(business.name)}</small><h3>Today’s chair</h3></div>
                    <div class="desk-tabs"><span>Requests</span><span>Schedule</span><span>Services</span><span>Reviews</span></div>
                    <div class="request-list">${requestCards(business)}</div>
                    <div class="desk-summary"><div><b>3</b><small>open requests</small></div><div><b>1</b><small>deposit due</small></div></div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="proof-section">
            <div class="shell">
              <div class="section-head"><div><p class="kicker">Business-owned payments</p><h2>Scan. Pay. Keep the relationship clear.</h2></div><p>The QR is generated only after the operator connects a reviewed business payment destination. This demonstration QR cannot be scanned or paid.</p></div>
              <div class="qr-row"><div class="demo-qr" aria-label="Decorative non-scannable demo QR"></div><div><h3>Deposit or checkout, on the owner’s terms.</h3><p>Connect Square, Stripe Payment Links, Cash App Business, or another reviewed provider. FAMtastic stores the destination—not cards, bank details, or merchant credentials.</p></div></div>
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
    version: 'booked-branded.v3',
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
    localStage('static-construction', 'static-proof-construction', ['index.html', 'package/index.html', '4 emails', '4 rooms', '12 proof pages']),
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
