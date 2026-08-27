function dataAttributes(section) {
  return `data-section-id="${section.instance_id}" data-component-id="${section.component_id}" data-component-variant="${section.variant}"`;
}

export function renderBookedBrandedOnePage({
  recipe,
  business,
  direction,
  archetype,
  businessSystem,
  publicBase,
  imageSrc,
  imageAlt,
  esc,
  button,
  vars,
}) {
  const context = {
    recipe,
    business,
    direction,
    archetype,
    businessSystem,
    publicBase,
    imageSrc,
    imageAlt,
    esc,
    button,
  };
  const renderers = componentRenderers();
  const renderRegion = (region) => recipe.regions[region].map((section) => {
    const renderer = renderers[section.component_id];
    if (!renderer) throw new Error(`Missing renderer for ${section.component_id}.`);
    return renderer(context, section);
  }).join('\n');

  return `<div data-page-template-id="${esc(recipe.id)}" data-page-template-version="${esc(recipe.version)}" style="${vars}">
        ${renderRegion('header')}
        <main data-page-region="main">
          ${renderRegion('main')}
        </main>
        ${renderRegion('footer')}
      </div>`;
}

function componentRenderers() {
  return {
    'navigation.site-nav.v1': renderNavigation,
    'hero.split-brand.v1': renderHero,
    'proof.creative-dna.v1': renderCreativeDna,
    'services.card-grid.v1': renderServices,
    'operations.booking-desk.v1': renderOwnerDesk,
    'payments.owner-qr.v1': renderPaymentQr,
    'trust.review-grid.v1': renderReviews,
    'booking.request-form.v1': renderBookingForm,
    'navigation.site-footer.v1': renderFooter,
  };
}

function renderNavigation({ business, archetype, publicBase, esc, button }, section) {
  return `<nav class="proof-nav" aria-label="Primary" ${dataAttributes(section)}>
          <a class="brand-lockup" data-field-id="business.brand-lockup" href="${publicBase}/rooms/${business.slug}/"><span class="brand-mark">${esc(business.mark)}</span><span>${esc(business.name)}</span></a>
          <div class="proof-nav-links" data-slot-id="navigation-items"><a href="#services">Services</a><a href="#owner-desk">Owner desk</a>${button('#request', archetype.message.cta)}</div>
        </nav>`;
}

function renderHero({ business, direction, archetype, imageSrc, imageAlt, esc, button }, section) {
  const ownerDeskLabel = direction.id === 'c' ? 'How the flow works' : 'See the Booking Desk';
  return `<section class="proof-hero" id="${esc(section.anchor_id || 'top')}" ${dataAttributes(section)}>
            <div class="proof-hero-copy" data-slot-id="hero-copy">
              <span class="overline" data-field-id="hero.overline">${esc(direction.name)} · ${esc(business.location)}</span>
              <h1 data-field-id="hero.headline" data-echo="${esc(direction.headline)}">${esc(direction.headline)}</h1>
              <p data-field-id="hero.subhead">${esc(direction.subhead)}</p>
              <div class="proof-actions" data-slot-id="hero-actions">${button('#request', archetype.message.cta)}${button('#owner-desk', ownerDeskLabel, true)}</div>
            </div>
            <div class="proof-hero-media" data-slot-id="hero-media">
              <img data-field-id="hero.media.src" src="${esc(imageSrc)}" alt="${esc(imageAlt)}" width="1376" height="768">
              <div class="proof-hero-tag"><strong data-field-id="business.hours">${esc(business.hours)}</strong><br><span data-field-id="business.policy">${esc(business.policy)}</span></div>
            </div>
          </section>`;
}

function renderCreativeDna({ archetype, businessSystem, esc }, section) {
  return `<section class="creative-dna-strip" aria-label="Direction creative system" ${dataAttributes(section)}>
            <div><small>Type Director</small><strong data-field-id="type.system">${esc(archetype.type.display_family)} × ${esc(archetype.type.body_family)}</strong><span>${esc(archetype.type.composition)}</span></div>
            <div><small>Shape Director</small><strong data-field-id="shape.system">${esc(archetype.name)}</strong><span>${esc(archetype.shape.grammar)}</span></div>
            <div><small>Message Director</small><strong data-field-id="message.system">${esc(archetype.message.tone)}</strong><span>${esc(archetype.message.argument)}</span></div>
            <div><small>Native motifs</small><strong data-field-id="business.motifs">${esc(businessSystem.motifs.join(' · '))}</strong><span>Original symbolic language, never a copied platform identity.</span></div>
          </section>`;
}

function renderServices({ business, esc }, section) {
  const cards = business.services.map((service, index) => `
    <article class="service-card" data-repeater-id="services" data-item-id="service-${index + 1}">
      <span data-field-id="service.duration">${esc(service.time)}</span>
      <h3 data-field-id="service.name">${esc(service.name)}</h3>
      <div class="service-price"><b data-field-id="service.price">${esc(service.price)}</b><span data-field-id="service.action">Request →</span></div>
    </article>`).join('');
  return `<section class="proof-section" id="${esc(section.anchor_id || 'services')}" ${dataAttributes(section)}>
            <div class="shell">
              <div class="section-head" data-slot-id="section-heading"><div><p class="kicker">Choose with confidence</p><h2>Services that explain themselves.</h2></div><p>Each service gives the client the price, time, preparation, and next step before they ask. The owner controls what is visible from the phone.</p></div>
              <div class="service-grid" data-slot-id="service-cards">${cards}</div>
            </div>
          </section>`;
}

function renderOwnerDesk({ business, esc }, section) {
  const requests = business.requests.map((request, index) => {
    const actions = index === 0
      ? '\n      <div class="request-actions"><span>Confirm</span><span>Suggest time</span></div>'
      : '';
    return `
    <div class="request-card" data-repeater-id="booking.requests" data-item-id="request-${index + 1}">
      <div class="request-top">
        <span class="request-avatar">${esc(request.initials)}</span>
        <span><strong data-field-id="request.name">${esc(request.name)}</strong><small><span data-field-id="request.service">${esc(request.service)}</span> · <span data-field-id="request.time">${esc(request.time)}</span></small></span>
        <span class="status-chip" data-field-id="request.status">${esc(request.status)}</span>
      </div>${actions}
    </div>`;
  }).join('');
  return `<section class="proof-section experience-band" id="${esc(section.anchor_id || 'owner-desk')}" ${dataAttributes(section)}>
            <div class="shell experience-grid">
              <div class="experience-copy" data-slot-id="value-copy">
                <p class="kicker">The operating difference</p>
                <h2>The site and the workday share one truth.</h2>
                <p>Start with the useful core: show services clearly, choose a booking path that fits the owner today, see new requests when request-to-book is selected, display the business’s own payment QR, and invite a fresh testimonial after completion. Calendar depth, reminders, multi-staff scheduling, and other automation remain optional upgrades for the moment they can save time or unlock more appointments.</p>
                <div class="flow-list" data-repeater-id="flow.steps">
                  <div class="flow-item"><b>1</b><span><strong>Client requests</strong><br>Service, preferred time, and essential preparation context.</span></div>
                  <div class="flow-item"><b>2</b><span><strong>Owner decides</strong><br>Confirm, propose another time, or decline without losing the request.</span></div>
                  <div class="flow-item"><b>3</b><span><strong>Business gets paid directly</strong><br>Show the operator’s own Cash App or existing payment QR. The payment stays between the client, business, and chosen provider.</span></div>
                </div>
              </div>
              <div class="phone-wrap" data-slot-id="phone-preview" aria-label="Static phone Booking Desk demonstration">
                <div class="phone">
                  <div class="phone-screen">
                    <div class="phone-status"><span>9:41</span><span>Booked &amp; Branded</span><span>•••</span></div>
                    <div class="desk-head"><small>${esc(business.name)}</small><h3>Today’s chair</h3></div>
                    <div class="desk-tabs" data-repeater-id="desk.tabs"><span>Requests</span><span>Schedule</span><span>Services</span><span>Reviews</span></div>
                    <div class="request-list">${requests}</div>
                    <div class="desk-summary" data-repeater-id="desk.metrics"><div><b>3</b><small>open requests</small></div><div><b>1</b><small>reply due</small></div></div>
                  </div>
                </div>
              </div>
            </div>
          </section>`;
}

function renderPaymentQr(_context, section) {
  return `<section class="proof-section" ${dataAttributes(section)}>
            <div class="shell">
              <div class="section-head" data-slot-id="section-heading"><div><p class="kicker">Your QR. Your account. Your money.</p><h2>Let clients scan the payment method you already use.</h2></div><p>The business supplies and approves its own Cash App or existing payment QR. This demonstration QR cannot be scanned or paid.</p></div>
              <div class="qr-row"><div class="demo-qr" data-slot-id="owner-approved-qr" aria-label="Decorative non-scannable demo QR"></div><div><h3>FAMtastic displays it. The business gets paid directly.</h3><p data-field-id="payment.disclosure">FAMtastic does not process, receive, settle, or reconcile the payment. Payment-processing and optional messaging costs are paid directly by the business to its chosen providers.</p></div></div>
            </div>
          </section>`;
}

function renderReviews(_context, section) {
  return `<section class="proof-section" ${dataAttributes(section)}>
            <div class="shell">
              <div class="section-head" data-slot-id="section-heading"><div><p class="kicker">Build an owned reputation</p><h2>Let the good work keep working.</h2></div><p>Keep existing public review links visible while inviting completed clients to leave fresh, permission-based testimonials here. The owner can moderate privacy and abuse without filtering out honest negative feedback.</p></div>
              <div class="review-grid" data-repeater-id="reviews"><article class="review-card" data-item-id="review-1"><span class="stars">★★★★★</span><p>“Sample placement: clear service details and an easy confirmation made the whole visit feel organized.”</p><small>Fictional client · Demonstration copy</small></article><article class="review-card" data-item-id="review-2"><span class="stars">★★★★★</span><p>“Sample placement: the result felt personal—and the booking experience finally matched it.”</p><small>Fictional client · Demonstration copy</small></article></div>
            </div>
          </section>`;
}

function renderBookingForm({ business, esc }, section) {
  const options = business.services.map(service => `<option>${esc(service.name)}</option>`).join('');
  return `<section class="proof-section" id="${esc(section.anchor_id || 'request')}" ${dataAttributes(section)}>
            <div class="shell">
              <div class="section-head" data-slot-id="section-heading"><div><p class="kicker">Choose the booking path</p><h2>Make the next appointment easy.</h2></div><p>This proof demonstrates request-to-book. A live setup can instead link or embed an owner-controlled Google appointment page, Cal.com page, or current provider after the selected path is reviewed on desktop and phone.</p></div>
              <form class="booking-form" data-slot-id="booking-form" aria-label="Non-submitting demonstration booking form">
                <label>Service<select data-field-id="form.service">${options}</select></label>
                <label>Preferred day<input data-field-id="form.day" type="text" value="Saturday" readonly></label>
                <label>Preferred time<input data-field-id="form.time" type="text" value="11:30 AM" readonly></label>
                <label>Contact method<select data-field-id="form.contact-method"><option>Text me</option><option>Email me</option></select></label>
                <label class="wide">Anything the owner should know?<input data-field-id="form.notes" type="text" value="First visit — looking for a consultation." readonly></label>
                <span class="wide button" data-field-id="form.action" aria-disabled="true">Demonstration only — no request submitted</span>
              </form>
            </div>
          </section>`;
}

function renderFooter({ business, publicBase, esc, button }, section) {
  return `<footer class="proof-footer" ${dataAttributes(section)}><div class="shell proof-footer-grid"><div data-field-id="footer.business-summary"><strong>${esc(business.name)}</strong><br><span>${esc(business.location)} · ${esc(business.hours)}</span></div><div>${button(`${publicBase}/rooms/${business.slug}/`, 'Compare all 3 directions', true)}</div></div></footer>`;
}
