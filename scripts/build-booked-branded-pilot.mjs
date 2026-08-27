#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { mkdir, readFile, readdir, rm, stat, writeFile } from 'node:fs/promises';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(repositoryRoot, 'frontend/public/showcase/booked-and-branded-pilot');
const dataPath = join(publicRoot, 'pilot-data.json');
const data = JSON.parse(await readFile(dataPath, 'utf8'));
const publicBase = '/showcase/booked-and-branded-pilot';
const canonicalBase = 'https://famtasticdesigns.com' + publicBase;

const imagePrompts = {
  schema: 'famtastic.booked-branded-image-prompts.v1',
  provider: 'openai-built-in-image-generation',
  model: 'provider_did_not_report',
  cost: { status: 'provider_did_not_report', authorized_cap_usd: 1 },
  prompts: [
    {
      business_slug: 'coastline-crown-barbers',
      output_ref: 'exec-83ed3784-de53-4ac5-80b0-410d2672540e.png',
      prompt: 'Premium 16:9 editorial documentary photograph of a skilled Black male barber giving a precise taper and beard lineup to a Black male client in a polished Port St. Lucie barbershop. Warm coastal Florida daylight, dark walnut, brushed brass, clean mirrors, authentic tools; subjects on the right with negative space on the left. Fictional people; no text, logos, watermarks, distorted hands, extra fingers, or duplicated tools.'
    },
    {
      business_slug: 'velvet-coil-atelier',
      output_ref: 'exec-91c5e205-5c65-4d61-8070-aa1d89c6c6be.png',
      prompt: 'Premium 16:9 beauty editorial photograph of a skilled Black female stylist finishing defined healthy curls on a Black female client in an intimate Treasure Coast studio. Textured plaster, terracotta, linen, amber unlabeled bottles, soft window light; subjects left with negative space on the right. Fictional people; no text, logos, watermarks, distorted hair, extra fingers, or duplicated tools.'
    },
    {
      business_slug: 'palmera-fade-society',
      output_ref: 'exec-a4e9fe7b-7eaf-4cbb-811b-07b44b5579e8.png',
      prompt: 'Premium 16:9 street-editorial photograph of a skilled Latino male barber finishing a sharp fade on a Latino male client in a contemporary West Palm Beach barbershop. Tropical daylight, cream terrazzo, sea-glass tile, chrome, coral accent; subjects left with negative space on the right. Fictional people; no flags, clichés, text, logos, watermarks, distorted hands, extra fingers, or duplicated tools.'
    },
    {
      business_slug: 'saltline-color-house',
      output_ref: 'exec-d1f6042b-ab45-4968-9ec6-26ff009b7a75.png',
      prompt: 'Premium 16:9 fashion-beauty photograph of a skilled white female stylist creating dimensional sunlit color and a textured cut for a white female client in a design-forward Miami studio. Sculptural white space, pale oak, travertine, cobalt glass, diffused coastal light; subjects right with negative space on the left. Fictional people; no text, logos, watermarks, labels, distorted hands, extra fingers, or duplicated tools.'
    }
  ]
};

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

function vars(business) {
  const { ink, paper, accent, accent2 } = business.palette;
  return `--ink:${esc(ink)};--paper:${esc(paper)};--accent:${esc(accent)};--accent2:${esc(accent2)}`;
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
            <strong>$199 founding-pilot thesis</strong>
            <p>Custom site + phone Booking Desk + request-to-book + business-owned payment and QR handoff + fresh moderated testimonial showcase. Real-time multi-staff scheduling remains a later, separately proven add-on.</p>
          </aside>
        </div>
      </header>
      <main class="shell pilot-grid">${cards}</main>`
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
            <span class="sender-mark">FD</span>
            <span><strong>FAMtastic Designs</strong><small>to Demo recipient · No message sent</small></span>
            <small>Today · 2:30 PM</small>
          </div>
        </div>
        <article class="mail-body">
          <div class="mail-brand">FAMTASTIC DESIGNS</div>
          <h2>I built the alternative before asking you to imagine it.</h2>
          <p>Hi there,</p>
          <p>I found ${esc(business.name)} through a public booking-platform profile. You already made it possible for clients to book—the opportunity is giving them a place that looks and feels like <em>your</em> business before they choose a service.</p>
          <p>Your profile’s focus on ${esc(business.specialty)} became the starting point. I used that—not a generic salon template—to create three complete visual directions for ${esc(business.name)}.</p>
          <p>The proposed <strong>$199 Booked &amp; Branded founding pilot</strong> includes:</p>
          <ul>
            <li>your own mobile-ready website;</li>
            <li>a phone-friendly Booking Desk;</li>
            <li>service, availability, and policy management;</li>
            <li>booking and business-owned payment/QR options; and</li>
            <li>a small, consent-based testimonial showcase.</li>
          </ul>
          <p>You can keep your current booking platform while testing it. Nothing needs to switch until the new flow works for you.</p>
          <a class="mail-cta" href="${roomUrl}">See ${esc(possessive(business.name))} three directions →</a>
          <p>— Fritz @ FAMtastic Designs</p>
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
  const directions = business.directions.map(direction => `
    <article class="direction-card">
      <div class="direction-preview">
        <img src="${publicBase}/assets/${esc(business.image)}" alt="Fictional ${esc(business.name)} direction ${esc(direction.id.toUpperCase())} preview" width="1672" height="941">
        <span class="direction-letter">${esc(direction.id)}</span>
      </div>
      <div class="direction-card-body">
        <small>${esc(direction.label)}</small>
        <h2>${esc(direction.name)}</h2>
        <p>${esc(direction.subhead)}</p>
        ${button(`${publicBase}/proofs/${business.slug}/${direction.id}/`, 'Open working direction')}
      </div>
    </article>`).join('');
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
          <p>Same business truth, three materially different compositions. Every direction includes the proposed public site, request-to-book flow, phone Booking Desk, payment/QR boundary, and controlled testimonial treatment.</p>
          <div class="room-meta"><span>${esc(business.location)}</span><span>${esc(business.specialty)}</span><span>Fictional demonstration</span></div>
        </header>
        <section class="direction-grid" aria-label="Three design directions">${directions}</section>
        <section class="room-offer">
          <div><h3>The $199 boundary</h3><p>One operator, one location, up to 12 services, request-to-book, phone controls, one business-owned payment destination, booking/payment QR, and fresh consent-based testimonials.</p></div>
          <div><h3>The honest expansion</h3><p>Keep the current platform during testing. Add instant calendar connections, multi-staff operations, SMS, full POS, memberships, or deeper payments only after those capabilities are separately scoped and proven.</p></div>
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
  return template({
    title: `${business.name} — ${direction.name}`,
    description: `${direction.label} Booked & Branded demonstration for ${business.name}.`,
    className: `proof-page dir-${direction.id}`,
    body: `${ribbon()}
      <div style="${vars(business)}">
        <nav class="proof-nav" aria-label="Primary">
          <a class="brand-lockup" href="${publicBase}/rooms/${business.slug}/"><span class="brand-mark">${esc(business.mark)}</span><span>${esc(business.name)}</span></a>
          <div class="proof-nav-links"><a href="#services">Services</a><a href="#owner-desk">Owner desk</a>${button('#request', 'Request a time')}</div>
        </nav>
        <main>
          <section class="proof-hero">
            <div class="proof-hero-copy">
              <span class="overline">${esc(direction.name)} · ${esc(business.location)}</span>
              <h1>${esc(direction.headline)}</h1>
              <p>${esc(direction.subhead)}</p>
              <div class="proof-actions">${button('#request', 'Request a time')}${button('#owner-desk', 'See the Booking Desk', true)}</div>
            </div>
            <div class="proof-hero-media">
              <img src="${publicBase}/assets/${esc(business.image)}" alt="Fictional ${esc(business.operator)} working in the ${esc(business.name)} demonstration" width="1672" height="941">
              <div class="proof-hero-tag"><strong>${esc(business.hours)}</strong><br>${esc(business.policy)}</div>
            </div>
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
                <p>The starter does not pretend to replace a full multi-staff platform. It gives a solo operator the useful core: see new requests, confirm or suggest another time, manage services and blackout dates, send a business-owned deposit link, and request a fresh testimonial after completion.</p>
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
              <div class="section-head"><div><p class="kicker">Fresh consent-based proof</p><h2>Reviews that belong to this chapter.</h2></div><p>No Booksy review scraping. Completed clients may submit a fresh testimonial with permission; the owner can moderate privacy and abuse without review-gating negative feedback.</p></div>
              <div class="review-grid"><article class="review-card"><span class="stars">★★★★★</span><p>“Sample placement: clear service details and an easy confirmation made the whole visit feel organized.”</p><small>Fictional client · Demonstration copy</small></article><article class="review-card"><span class="stars">★★★★★</span><p>“Sample placement: the result felt personal—and the booking experience finally matched it.”</p><small>Fictional client · Demonstration copy</small></article></div>
            </div>
          </section>

          <section class="proof-section" id="request">
            <div class="shell">
              <div class="section-head"><div><p class="kicker">Request-to-book starter</p><h2>Ask for the chair. Get a real confirmation.</h2></div><p>This bounded pilot avoids silent double-booking: the client requests a preferred time and the owner confirms it from the Booking Desk.</p></div>
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

for (const generated of ['emails', 'rooms', 'proofs']) {
  await rm(join(publicRoot, generated), { recursive: true, force: true });
}

await write('index.html', pilotIndex());
await write('image-prompts.json', JSON.stringify(imagePrompts, null, 2) + '\n');

for (const business of data.businesses) {
  await write(`emails/${business.slug}/index.html`, emailPage(business));
  await write(`rooms/${business.slug}/index.html`, roomPage(business));
  for (const direction of business.directions) {
    await write(`proofs/${business.slug}/${direction.id}/index.html`, proofPage(business, direction));
  }
}

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
    role: absolute.endsWith('.webp') ? 'generated-image-final' : absolute.endsWith('.html') ? 'customer-facing-static-proof' : 'proof-support'
  });
}

const buildDna = {
  schema: 'famtastic.build-dna.v1',
  run: {
    run_id: 'booked-branded-four-proof-pilot-20260827',
    routine: 'website_proof.generate.v1',
    source_lane: 'famtastic-owned-product-demonstration',
    created_at: '2026-08-27T18:18:00Z',
    public_base: canonicalBase,
    business_count: data.businesses.length,
    directions_per_business: 3,
    email_sent: false,
    payment_enabled: false,
    customer_data_used: false
  },
  recipe: {
    offer: 'Booked & Branded — $199 founding pilot draft',
    audience_examples: ['Black barber in Port St. Lucie', 'Black hair stylist in Fort Pierce', 'Hispanic barber in West Palm Beach', 'white hair stylist in Miami'],
    direction_system: ['editorial restraint', 'high-energy brand world', 'operator-first system'],
    operational_surface: 'static phone Booking Desk demonstration',
    disclaimer: 'All businesses, people, services, requests, reviews, and profile facts are fictional demonstration content.'
  },
  image_generation: imagePrompts,
  construction: {
    agent: 'codex',
    provider: 'local-deterministic-static-builder',
    model: 'not_applicable',
    generator: 'scripts/build-booked-branded-pilot.mjs',
    output: 'frontend/public/showcase/booked-and-branded-pilot'
  },
  qa: {
    static_build: 'pending',
    browser_desktop: 'pending',
    browser_mobile_390: 'pending',
    link_validation: 'pending',
    independent_review: 'pending'
  },
  cost: {
    authorized_image_cap_usd: 1,
    actual_usd: null,
    status: 'provider_did_not_report',
    note: 'The built-in image generation tool returned no billing or usage receipt; no paid fallback provider was called.'
  },
  artifacts
};

await write('build-dna.json', JSON.stringify(buildDna, null, 2) + '\n');
console.log(`Built ${data.businesses.length} email previews, ${data.businesses.length} concept rooms, and ${data.businesses.length * 3} proof directions.`);
console.log(`Evidence: ${join(publicRoot, 'build-dna.json')}`);
