/**
 * blogArt — generated SVG art for blog posts.
 *
 * WHY SVG STRINGS (and not React components):
 * Blog bodies arrive from Drupal as a single HTML string and are rendered with
 * `dangerouslySetInnerHTML`. Inline art therefore has to be *interleaved into
 * that string*; a React component cannot be spliced into the middle of an
 * innerHTML blob without shredding the body into fragments and rendering an
 * array, which would break `.v1-prose` sibling selectors and complicate the
 * pre-existing '55 Cents a Day' campaign path. So every art block is a pure
 * `(options) => string` function. The hero reuses the same module so there is
 * one source of truth for the art language.
 *
 * WHY NO RASTER IMAGES:
 * No image-generation provider is configured for this project. SVG is a better
 * fit anyway: it is crisp at every density, it costs ~2KB instead of ~120KB,
 * it inherits the site's CSS custom properties (so it is theme-aware for free),
 * and it can be *derived from the post's own content* rather than commissioned.
 *
 * DESIGN CONSTRAINTS HONORED HERE:
 * - Ground #070907, panel #101310/#141814, borders #252b25, radius 18-22px.
 * - Lime (#7cfc00) is the only accent and appears at most twice per block.
 * - NO box-shadow / drop-shadow glow anywhere in this module. The page's single
 *   permitted glow is already spent elsewhere; art must not compete for it.
 * - Every block is authored at a NARROW viewBox and capped with max-width in
 *   CSS, so type scales *up* on desktop instead of *down* on mobile. See
 *   `.fam-art` in index.css.
 * - Colors/type live in CSS classes (`.fam-art__*`), not presentation
 *   attributes, so the art restyles with the design system.
 *
 * ACCESSIBILITY:
 * - Informational art carries <title> + role="img" + aria-label.
 * - Purely ornamental art is aria-hidden and focusable="false".
 *
 * All element ids are namespaced per instance (`uid`) because duplicate SVG ids
 * on one document silently break <mask>/<pattern>/<clipPath> references.
 */

/* ------------------------------------------------------------------ */
/* Small utilities                                                     */
/* ------------------------------------------------------------------ */

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

/** Escape any post-derived text before it enters an HTML/SVG string. */
export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ESCAPES[char]);
}

/** Stable 32-bit hash — seeds deterministic per-post variation. */
export function hashString(value) {
  let hash = 0;
  const text = String(value ?? '');
  for (let i = 0; i < text.length; i += 1) {
    hash = (hash << 5) - hash + text.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

/** Strip tags and collapse whitespace. */
function toText(html) {
  return String(html ?? '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/\s+/g, ' ')
    .trim();
}

/** Trim a label to `max` characters on a word boundary (SVG text cannot wrap). */
function clamp(text, max) {
  const clean = toText(text).replace(/[.:;]+$/, '');
  if (clean.length <= max) return clean;
  const cut = clean.slice(0, max);
  const space = cut.lastIndexOf(' ');
  return `${(space > max * 0.6 ? cut.slice(0, space) : cut).trim()}…`;
}

let uidCounter = 0;
function nextUid(seed) {
  uidCounter += 1;
  return `fa${(seed % 9973).toString(36)}${uidCounter}`;
}

/* ------------------------------------------------------------------ */
/* Shared art primitives                                               */
/* ------------------------------------------------------------------ */

/**
 * A faint dot-grid <pattern>. This is the module's base "texture" — it reads as
 * engineering graph paper and keeps large dark areas from looking like a void.
 * `phase` shifts the grid so two blocks on one page are not identical.
 */
function dotGrid(uid, { size = 24, radius = 1.1, phase = 0 } = {}) {
  return `<pattern id="${uid}-dots" width="${size}" height="${size}" patternUnits="userSpaceOnUse" patternTransform="translate(${phase} ${phase})"><circle cx="${size / 2}" cy="${size / 2}" r="${radius}" class="fam-art__dot"/></pattern>`;
}

/** A left-to-right fade mask, used to let texture dissolve instead of hard-stop. */
function fadeMask(uid, width, height, { edge = 0.18 } = {}) {
  return `<mask id="${uid}-fade"><rect width="${width}" height="${height}" fill="url(#${uid}-fadegrad)"/></mask><linearGradient id="${uid}-fadegrad" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#000"/><stop offset="${edge}" stop-color="#fff"/><stop offset="${1 - edge}" stop-color="#fff"/><stop offset="1" stop-color="#000"/></linearGradient>`;
}

/* ------------------------------------------------------------------ */
/* Art block: textured section divider (ornamental)                    */
/* ------------------------------------------------------------------ */

/**
 * A full-column geometric break. Deliberately borderless and caption-free so it
 * reads as punctuation between sections rather than as a figure.
 */
export function artDivider({ seed = 0 } = {}) {
  const uid = nextUid(seed);
  const w = 720;
  const h = 56;
  const lines = [];
  for (let x = -40; x < w + 40; x += 11) {
    lines.push(`<line x1="${x}" y1="${h - 8}" x2="${x + 22}" y2="8" class="fam-art__hatch"/>`);
  }
  return `<div class="fam-art-divider" aria-hidden="true"><svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true"><defs>${fadeMask(uid, w, h, { edge: 0.3 })}</defs><g mask="url(#${uid}-fade)">${lines.join('')}</g><rect x="${w / 2 - 62}" y="${h / 2 - 0.5}" width="44" height="1" class="fam-art__rule"/><rect x="${w / 2 + 18}" y="${h / 2 - 0.5}" width="44" height="1" class="fam-art__rule"/><rect x="${w / 2 - 6}" y="${h / 2 - 6}" width="12" height="12" rx="2" transform="rotate(45 ${w / 2} ${h / 2})" class="fam-art__accent-fill"/></svg></div>`;
}

/* ------------------------------------------------------------------ */
/* Art block: the $199 ÷ 365 ≈ 55¢ composition                         */
/* ------------------------------------------------------------------ */

/**
 * 365 squares — one per day of the included first year — with the arithmetic
 * stated above them. The texture is not decoration: each square *is* a day, so
 * the block explains the "55 cents a day" framing instead of just captioning it.
 */
export function artDayCost({ seed = 0 } = {}) {
  const uid = nextUid(seed);
  const w = 360;
  const padX = 22;
  const cols = 25;
  const rows = 15;
  const pitch = (w - padX * 2) / cols;
  const dot = 6.6;
  const gridTop = 92;
  const h = Math.round(gridTop + rows * pitch + 16);

  const squares = [];
  for (let i = 0; i < 365; i += 1) {
    const col = i % cols;
    const row = Math.floor(i / cols);
    const x = padX + col * pitch + (pitch - dot) / 2;
    const y = gridTop + row * pitch;
    squares.push(
      `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${dot}" height="${dot}" rx="1.4" class="${i === 0 ? 'fam-art__accent-fill' : 'fam-art__cell'}"/>`,
    );
  }

  return `<svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="${uid}-t" class="fam-art__svg"><title id="${uid}-t">A grid of 365 squares, one for each day of the first year. The one-time $199 price divided across those days is about 55 cents per day.</title><defs>${dotGrid(uid, { size: 20, radius: 0.9, phase: seed % 20 })}</defs><rect width="${w}" height="${h}" rx="18" class="fam-art__ground"/><rect width="${w}" height="${h}" rx="18" fill="url(#${uid}-dots)"/><text x="${padX}" y="34" class="fam-art__kicker">$199 ONE-TIME ÷ 365 DAYS</text><text x="${padX}" y="72" class="fam-art__figure">≈ <tspan class="fam-art__accent-text">55¢</tspan> a day</text><g>${squares.join('')}</g><rect x="0.5" y="0.5" width="${w - 1}" height="${h - 1}" rx="17.5" class="fam-art__frame"/></svg>`;
}

/* ------------------------------------------------------------------ */
/* Art block: rented land vs owned hub                                 */
/* ------------------------------------------------------------------ */

/**
 * Two contrasting shape groups. "Rented" is drawn provisional — dashed
 * boundary, a landlord bar the business does not control, blocks off their own
 * baseline. "Owned" is drawn settled — solid boundary, blocks aligned to a
 * lime ground line. The contrast is carried by *geometry*, not by color alone,
 * so it survives greyscale and low-vision viewing.
 */
export function artOwnedVsRented({ seed = 0 } = {}) {
  const uid = nextUid(seed);
  const w = 360;
  const h = 316;
  // Deliberately wide jitter: the whole point of the top group is that it does
  // NOT line up. A subtle wobble reads as a rendering bug rather than a claim.
  const jitter = (i) => ((hashString(`${seed}-${i}`) % 15) - 7);

  const rentedBlocks = [0, 1, 2]
    .map((i) => {
      const x = 40 + i * 92;
      const y = 92 + jitter(i);
      return `<rect x="${x}" y="${y}" width="66" height="34" rx="5" class="fam-art__cell" transform="rotate(${(jitter(i + 7) * 0.7).toFixed(1)} ${x + 33} ${y + 17})"/>`;
    })
    .join('');

  const ownedBlocks = [0, 1, 2]
    .map((i) => {
      const x = 40 + i * 92;
      return `<rect x="${x}" y="236" width="66" height="34" rx="5" class="fam-art__panel"/>${i === 1 ? `<rect x="${x}" y="236" width="66" height="4" rx="2" class="fam-art__accent-fill"/>` : ''}`;
    })
    .join('');

  return `<svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="${uid}-t" class="fam-art__svg"><title id="${uid}-t">Two stacked diagrams. The top one, labelled Rented, shows loose blocks sitting under a platform bar inside a dashed boundary the business does not control. The bottom one, labelled Owned, shows the same blocks aligned on a solid ground line inside a boundary the business owns.</title><defs>${dotGrid(uid, { size: 22, radius: 0.9, phase: seed % 22 })}</defs><rect width="${w}" height="${h}" rx="18" class="fam-art__ground"/><rect width="${w}" height="${h}" rx="18" fill="url(#${uid}-dots)"/>

<text x="22" y="30" class="fam-art__kicker">RENTED</text>
<rect x="22" y="42" width="316" height="102" rx="14" class="fam-art__dashed"/>
<rect x="40" y="58" width="280" height="14" rx="4" class="fam-art__cell"/>
<text x="46" y="69" class="fam-art__micro">PLATFORM RULES</text>
${rentedBlocks}

<text x="22" y="186" class="fam-art__kicker"><tspan class="fam-art__accent-text">OWNED</tspan></text>
<rect x="22" y="198" width="316" height="102" rx="14" class="fam-art__frame-strong"/>
${ownedBlocks}
<rect x="40" y="280" width="280" height="2" rx="1" class="fam-art__accent-fill"/>

<rect x="0.5" y="0.5" width="${w - 1}" height="${h - 1}" rx="17.5" class="fam-art__frame"/></svg>`;
}

/* ------------------------------------------------------------------ */
/* Art block: ordered flow / process spine                             */
/* ------------------------------------------------------------------ */

/**
 * A numbered vertical spine built from the post's OWN ordered list. Labels are
 * lifted from the <li> text, so this diagram cannot drift out of sync with the
 * prose it illustrates. The final node is accented because in every flow this
 * blog describes, the last step is the one the reader is deciding about.
 */
export function artFlowSteps({ seed = 0, steps = [] } = {}) {
  const uid = nextUid(seed);
  // 32 chars is the measured limit: labels start at x=74 in a 360-unit box and
  // .fam-art__label is 16 units, so ~270 units of run works out to 32 glyphs.
  const list = steps.slice(0, 5).map((step) => clamp(step, 32)).filter(Boolean);
  if (list.length < 3) return '';

  const w = 360;
  const top = 42;
  const gap = 54;
  const h = top + (list.length - 1) * gap + 46;
  const cx = 44;

  const nodes = list
    .map((label, i) => {
      const cy = top + i * gap;
      const last = i === list.length - 1;
      return `<circle cx="${cx}" cy="${cy}" r="15" class="${last ? 'fam-art__node-accent' : 'fam-art__node'}"/><text x="${cx}" y="${cy + 5}" text-anchor="middle" class="${last ? 'fam-art__num-accent' : 'fam-art__num'}">${i + 1}</text><text x="${cx + 30}" y="${cy + 5}" class="fam-art__label">${escapeHtml(label)}</text>`;
    })
    .join('');

  return `<svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="${uid}-t" class="fam-art__svg"><title id="${uid}-t">A numbered vertical flow: ${escapeHtml(list.join('; then '))}.</title><defs>${dotGrid(uid, { size: 22, radius: 0.9, phase: seed % 22 })}</defs><rect width="${w}" height="${h}" rx="18" class="fam-art__ground"/><rect width="${w}" height="${h}" rx="18" fill="url(#${uid}-dots)"/><line x1="${cx}" y1="${top}" x2="${cx}" y2="${top + (list.length - 1) * gap}" class="fam-art__spine"/>${nodes}<rect x="0.5" y="0.5" width="${w - 1}" height="${h - 1}" rx="17.5" class="fam-art__frame"/></svg>`;
}

/* ------------------------------------------------------------------ */
/* Art block: scope boundary (what is inside the price, what is not)   */
/* ------------------------------------------------------------------ */

/**
 * A literal boundary. Items the post lists as included sit inside a solid
 * enclosure; the named "separate packages" sit outside it as dashed chips.
 * "Defined scope is a feature" is an argument about a *boundary*, so the art
 * draws the boundary.
 *
 * WHY THIS ONE IS AN HTML/SVG HYBRID:
 * An earlier all-SVG version had to force every inclusion onto one unwrappable
 * <text> line, which truncated all five rows to "A complete small-business…".
 * Five ellipses is not a diagram, it is a broken list. So the SVG here supplies
 * only what SVG is good at — the boundary, the texture — and the inclusions are
 * real HTML text that wraps at any column width, in any language, at any list
 * length. Same reasoning as artPullQuote.
 */
export function artScopeBoundary({ seed = 0, included = [], excluded = [] } = {}) {
  const uid = nextUid(seed);
  const inside = included.slice(0, 6).map((item) => toText(item)).filter(Boolean);
  if (inside.length < 3) return '';
  const outside = excluded.slice(0, 3).map((item) => clamp(item, 14)).filter(Boolean);

  const rows = inside.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
  const chips = outside.length
    ? `<p class="fam-scope__kicker fam-scope__kicker--muted">Separate packages</p><div class="fam-scope__chips">${outside
        .map((item) => `<span>${escapeHtml(item)}</span>`)
        .join('')}</div>`
    : '';

  return `<div class="fam-scope"><svg class="fam-scope__texture" viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><defs>${dotGrid(uid, { size: 22, radius: 1.2, phase: seed % 22 })}</defs><rect width="400" height="300" fill="url(#${uid}-dots)"/></svg><p class="fam-scope__kicker"><span class="fam-scope__accent">Included</span> in the one-time price</p><ul class="fam-scope__list">${rows}</ul>${chips}</div>`;
}

/* ------------------------------------------------------------------ */
/* Art block: pull quote over texture                                  */
/* ------------------------------------------------------------------ */

/**
 * Real HTML text over an SVG texture — NOT SVG <text>. Keeping the quote as
 * live text means it reflows at any column width, stays selectable and
 * translatable, and is read once (not twice) by a screen reader. The texture
 * sits behind at low opacity with the type on an opaque plate, so contrast is
 * never negotiated with the pattern.
 */
export function artPullQuote({ seed = 0, quote = '' } = {}) {
  const uid = nextUid(seed);
  const text = toText(quote);
  if (!text) return '';
  return `<aside class="fam-pullquote"><svg class="fam-pullquote__texture" viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><defs>${dotGrid(uid, { size: 16, radius: 1.4, phase: seed % 16 })}${fadeMask(uid, 400, 200, { edge: 0.24 })}</defs><rect width="400" height="200" fill="url(#${uid}-dots)" mask="url(#${uid}-fade)"/></svg><blockquote class="fam-pullquote__text">${escapeHtml(text)}</blockquote></aside>`;
}

/* ------------------------------------------------------------------ */
/* Hero art — one motif per blog category                              */
/* ------------------------------------------------------------------ */

const HERO_MOTIFS = {
  /** Get Customers — separate demand converging into one owned destination. */
  'get customers': (uid, seed) => {
    const accent = seed % 5;
    const curves = [0, 1, 2, 3, 4]
      .map((i) => {
        const y = 30 + i * 105;
        return `<path d="M0 ${y} C 170 ${y} 210 240 400 240" class="${i === accent ? 'fam-art__accent-stroke' : 'fam-art__flow'}" fill="none"/>`;
      })
      .join('');
    return `${curves}<circle cx="400" cy="240" r="52" class="fam-art__ring"/><circle cx="400" cy="240" r="27" class="fam-art__accent-fill"/>`;
  },
  /** Get Paid — value accumulating to a defined top. */
  'get paid': (uid, seed) => {
    const heights = [96, 154, 212, 284, 360];
    const bars = heights
      .map((barH, i) => {
        const x = 34 + i * 86;
        const y = 440 - barH;
        const last = i === heights.length - 1;
        return `<rect x="${x}" y="${y}" width="58" height="${barH}" rx="8" class="fam-art__panel"/>${last ? `<rect x="${x}" y="${y}" width="58" height="9" rx="4.5" class="fam-art__accent-fill"/>` : ''}`;
      })
      .join('');
    return `${bars}<rect x="20" y="448" width="440" height="2" rx="1" class="fam-art__flow-fill"/><circle cx="${34 + 4 * 86 + 29}" cy="${440 - 360 - 26}" r="7" class="fam-art__accent-fill" opacity="${0.4 + (seed % 3) * 0.2}"/>`;
  },
  /** Get Found — concentric reach with one located point. */
  'get found': (uid, seed) => {
    const angle = (-40 + (seed % 5) * 20) * (Math.PI / 180);
    const px = 230 + Math.cos(angle) * 168;
    const py = 250 + Math.sin(angle) * 168;
    const rings = [86, 142, 198, 254]
      .map((r, i) => `<circle cx="230" cy="250" r="${r}" class="fam-art__ring" stroke-dasharray="${i % 2 ? '10 12' : ''}"/>`)
      .join('');
    return `${rings}<line x1="230" y1="250" x2="${px.toFixed(1)}" y2="${py.toFixed(1)}" class="fam-art__flow"/><circle cx="230" cy="250" r="8" class="fam-art__flow-fill"/><circle cx="${px.toFixed(1)}" cy="${py.toFixed(1)}" r="18" class="fam-art__accent-fill"/><circle cx="${px.toFixed(1)}" cy="${py.toFixed(1)}" r="34" class="fam-art__accent-stroke" fill="none"/>`;
  },
  /** Serve Customers — two parties, one genuinely shared overlap. */
  'serve customers': (uid, seed) => {
    const cy = 250;
    const r = 132;
    const ax = 176;
    const bx = 296;
    const half = Math.sqrt(r * r - ((bx - ax) / 2) ** 2);
    const mx = (ax + bx) / 2;
    return `<circle cx="${ax}" cy="${cy}" r="${r}" class="fam-art__ring-thick"/><circle cx="${bx}" cy="${cy}" r="${r}" class="fam-art__ring-thick"/><line x1="${mx}" y1="${(cy - half).toFixed(1)}" x2="${mx}" y2="${(cy + half).toFixed(1)}" class="fam-art__accent-stroke"/><circle cx="${mx}" cy="${(cy - half).toFixed(1)}" r="9" class="fam-art__accent-fill"/><circle cx="${mx}" cy="${(cy + half).toFixed(1)}" r="9" class="fam-art__accent-fill"/><circle cx="${mx}" cy="${cy}" r="${4 + (seed % 3)}" class="fam-art__flow-fill"/>`;
  },
  /** Grow and Automate — modular capacity, one cell newly switched on. */
  'grow and automate': (uid, seed) => {
    const cells = [];
    const lit = seed % 3;
    for (let row = 0; row < 3; row += 1) {
      for (let col = 0; col < 3; col += 1) {
        const onDiagonal = row === col;
        const isLit = onDiagonal && row === lit;
        cells.push(
          `<rect x="${30 + col * 140}" y="${110 + row * 140}" width="120" height="120" rx="18" class="${isLit ? 'fam-art__accent-fill' : onDiagonal ? 'fam-art__panel-lit' : 'fam-art__panel'}"/>`,
        );
      }
    }
    return `${cells.join('')}<line x1="90" y1="170" x2="370" y2="450" class="fam-art__flow"/>`;
  },
};

const HERO_DEFAULT = (uid, seed) => HERO_MOTIFS['get customers'](uid, seed);

/**
 * Build a category-derived SVG hero.
 *
 * The 1600x900 viewBox deliberately matches the raster heroes the other 80
 * posts use, so a generated hero and a photographic one occupy the identical
 * `.blog-visual` box and can be swapped either way with no layout shift.
 *
 * The composition carries no small type — at 390px a 1600-unit viewBox renders
 * at roughly 0.18 scale, so anything under ~90 units would be illegible. The
 * only text is the category set as a large display word; everything a reader
 * needs in words lives in the HTML <figcaption> beneath, at real text size.
 */
export function heroArtFor({ category = '', title = '', slug = '' } = {}) {
  const key = String(category || '').trim().toLowerCase();
  const motif = HERO_MOTIFS[key] || HERO_DEFAULT;
  const label = (category || 'Field Guide').toUpperCase();
  const seed = hashString(slug || title || category);
  const uid = nextUid(seed);
  const fontSize = Math.max(64, Math.min(132, Math.round(820 / (label.length * 0.58))));

  return {
    label,
    caption: `FAMtastic Designs field guide: ${category || 'Small-business websites'}`,
    ariaLabel: `Abstract FAMtastic Designs cover graphic for the ${category || 'field guide'} category.`,
    svg: `<svg viewBox="0 0 1600 900" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${escapeHtml(`Abstract FAMtastic Designs cover graphic for the ${category || 'field guide'} category.`)}" class="fam-hero-art__svg" preserveAspectRatio="xMidYMid slice"><defs>${dotGrid(uid, { size: 44, radius: 1.8, phase: seed % 44 })}<radialGradient id="${uid}-vig" cx="0.72" cy="0.28" r="0.85"><stop offset="0" stop-color="#7cfc00" stop-opacity="0.07"/><stop offset="1" stop-color="#7cfc00" stop-opacity="0"/></radialGradient></defs><rect width="1600" height="900" class="fam-art__ground"/><rect width="1600" height="900" fill="url(#${uid}-dots)"/><rect width="1600" height="900" fill="url(#${uid}-vig)"/><rect x="110" y="392" width="132" height="7" rx="3.5" class="fam-art__accent-fill"/><text x="110" y="${470 + fontSize * 0.36}" class="fam-hero-art__word" font-size="${fontSize}">${escapeHtml(label)}</text><g transform="translate(1000 180)">${motif(uid, seed)}</g></svg>`,
  };
}

/* ------------------------------------------------------------------ */
/* Content matchers — which diagram does this section have earned?     */
/* ------------------------------------------------------------------ */

/**
 * Each recipe declares the textual signals that mean "this section is actually
 * about the thing this diagram draws". A recipe needs >= `min` distinct signals
 * before it may be placed, which is what keeps art from being decorative
 * wallpaper dropped at an arbitrary paragraph number.
 */
const RECIPES = [
  {
    id: 'ownedVsRented',
    min: 2,
    signals: [
      /rented land/i,
      /linktr|link-in-bio|linktree/i,
      /@gmail|gmail\.com/i,
      /\bown(?:ed|s|ing)?\b[^.]{0,40}\b(?:domain|hub|site|website)\b/i,
      /platform(?:'s)?\s+(?:free tier|policies|layout|changes)/i,
      /belong to (?:your|the) business/i,
    ],
    build: (ctx) => artOwnedVsRented({ seed: ctx.seed }),
    caption: 'Rented placements follow someone else’s rules. An owned domain sits on ground the business controls.',
  },
  {
    id: 'dayCost',
    // NB: "one-time" is deliberately NOT a signal. It shows up in every scope
    // discussion on this blog, so including it made the cost diagram tie with
    // the scope diagram on sections that were really about scope.
    min: 2,
    signals: [/55\s*(?:¢|cents?)/i, /\$199/, /\b365\b/, /\ba day\b/i, /annualiz/i, /per day\b/i],
    build: (ctx) => artDayCost({ seed: ctx.seed }),
    caption: 'The one-time $199 spread across the included first year — an affordability comparison, not a subscription.',
  },
  {
    id: 'flowSteps',
    min: 2,
    signals: [
      /how (?:the|it|this) (?:flow|process|order) works/i,
      /proofs? (?:come|get|are) (?:first|built)/i,
      /pick a direction/i,
      /checkout follows/i,
      /before (?:you )?pay/i,
      /\bstep\b/i,
    ],
    build: (ctx) => artFlowSteps({ seed: ctx.seed, steps: ctx.listItems }),
    caption: 'The decision point sits before the payment step, not after it.',
  },
  {
    id: 'scopeBoundary',
    min: 2,
    signals: [
      /defined scope/i,
      /published scope/i,
      /\bincludes?:/i,
      /separate packages/i,
      /hidden upsells?/i,
      /exactly what ships/i,
    ],
    build: (ctx) =>
      artScopeBoundary({
        seed: ctx.seed,
        included: ctx.listItems,
        excluded: ctx.excluded,
      }),
    caption: 'A defined boundary, not a thin one — what sits outside it is a separate package, not a hidden upsell.',
  },
];

function scoreRecipe(recipe, text) {
  return recipe.signals.reduce((total, signal) => total + (signal.test(text) ? 1 : 0), 0);
}

/* ------------------------------------------------------------------ */
/* Pull-quote selection                                                */
/* ------------------------------------------------------------------ */

/**
 * A pull quote is only worth printing if it stands alone. The gate rejects
 * fragments, list scaffolding, anything with a price or a URL in it (those read
 * as an ad, not an argument), and anything short enough to be a caption. It
 * must also come from far enough back in the article that repeating it reads as
 * a callback rather than as a duplicated line.
 */
function pickQuote(sections, fromMaxIndex) {
  const candidates = [];
  sections.slice(0, Math.max(0, fromMaxIndex)).forEach((section, index) => {
    section.blocks.forEach((block) => {
      if (block.tagName !== 'P') return;
      if (block.querySelector('a')) return;
      toText(block.innerHTML)
        .split(/(?<=[.!?])\s+/)
        .forEach((sentence) => {
          const s = sentence.trim();
          if (s.length < 70 || s.length > 150) return;
          if (!/[.!?]$/.test(s)) return;
          if (/[$:;]|https?:|www\.|\d{2,}/.test(s)) return;
          if (/^(and|but|so|then|that|which|it |this )/i.test(s)) return;
          candidates.push({ sentence: s, index });
        });
    });
  });
  if (!candidates.length) return null;
  // Prefer the latest qualifying sentence: later lines carry the argument's
  // conclusion rather than its setup.
  return candidates[candidates.length - 1].sentence;
}

/* ------------------------------------------------------------------ */
/* Placement engine                                                    */
/* ------------------------------------------------------------------ */

const FLOW_TAGS = new Set(['P', 'UL', 'OL', 'BLOCKQUOTE', 'TABLE']);

function wrapFigure(svg, caption) {
  if (!svg) return '';
  return `<figure class="article-inline-visual article-inline-visual--art"><div class="fam-art">${svg}</div><figcaption><img src="/brand/famtastic-mark.svg" alt="" width="28" height="28">FAMtastic Designs — ${escapeHtml(caption)}</figcaption></figure>`;
}

/**
 * Insert generated art into a Drupal body HTML string.
 *
 * PLACEMENT RULE (the whole point of this function):
 *  1. Parse the body into top-level blocks and group them into <h2> sections.
 *  2. Refuse to place anything unless the post can carry it: >= 3 sections,
 *     >= 8 blocks and >= 250 words. Short posts render exactly as before.
 *  3. A candidate slot is the point AFTER a section's first flow block — the
 *     reader has the section's premise, then sees the illustration. A slot is
 *     rejected if fewer than 2 blocks follow it (which is what keeps art from
 *     landing on top of the closing call to action) or if it sits in the first
 *     15% of the document.
 *  4. Each candidate section is scored against the recipe signals. Art is only
 *     placed where the surrounding prose actually earned it.
 *  5. Slot A takes the best-scoring candidate in the first ~62% of the article.
 *     Slot B takes the best remaining candidate at least 22% further down, and
 *     falls back to a pull quote, then to an ornamental divider, then to
 *     nothing.
 *  6. Hard cap of two inline blocks per post, at most one of them heavyweight
 *     per half of the article.
 *
 * Returns the original string untouched on any failure, and in any environment
 * without DOMParser (the Node-side SEO shell generator), so crawler HTML stays
 * pure prose.
 */
export function injectBodyArt(bodyHtml, post = {}) {
  const html = String(bodyHtml || '');
  if (!html || typeof DOMParser === 'undefined') return html;

  let doc;
  try {
    doc = new DOMParser().parseFromString(`<!doctype html><body>${html}</body>`, 'text/html');
  } catch {
    return html;
  }
  const blocks = Array.from(doc.body.children);
  if (blocks.length < 8) return html;

  const words = toText(html).split(/\s+/).filter(Boolean).length;
  if (words < 250) return html;

  // ---- group into <h2> sections -------------------------------------------
  const sections = [];
  blocks.forEach((block, index) => {
    if (block.tagName === 'H2') {
      sections.push({ heading: toText(block.innerHTML), headingIndex: index, blocks: [] });
    } else if (sections.length) {
      sections[sections.length - 1].blocks.push(block);
    }
  });
  if (sections.length < 3) return html;

  const seed = hashString(post.slug || post.title || '');

  // ---- build candidate slots ----------------------------------------------
  const candidates = [];
  sections.forEach((section, sectionIndex) => {
    const firstFlow = section.blocks.find((block) => FLOW_TAGS.has(block.tagName));
    if (!firstFlow) return;

    // Never split a lead-in from the list it introduces. "Web Basics is a
    // one-time $199 and includes:" followed by a <ul> is one unit; dropping a
    // figure between the colon and the list severs the sentence. If the block
    // after the anchor is a list, the figure goes after the LIST instead —
    // which also puts a list-derived diagram directly beneath its own source.
    let anchor = firstFlow;
    while (anchor.nextElementSibling && ['UL', 'OL'].includes(anchor.nextElementSibling.tagName)) {
      anchor = anchor.nextElementSibling;
    }

    const anchorIndex = blocks.indexOf(anchor);
    const remaining = blocks.length - 1 - anchorIndex;
    if (remaining < 2) return; // would sit against the closing CTA
    const depth = (anchorIndex + 1) / blocks.length;
    if (depth < 0.15) return;

    const text = `${section.heading} ${section.blocks.map((b) => toText(b.innerHTML)).join(' ')}`;
    const listItems = Array.from(
      section.blocks.find((b) => b.tagName === 'OL' || b.tagName === 'UL')?.children ?? [],
    ).map((li) => toText(li.querySelector('strong')?.innerHTML || li.innerHTML));

    const scored = RECIPES.map((recipe) => ({ recipe, score: scoreRecipe(recipe, text) }))
      .filter((entry) => entry.score >= entry.recipe.min)
      .sort((a, b) => b.score - a.score);

    candidates.push({ sectionIndex, anchor, depth, text, listItems, scored });
  });
  if (!candidates.length) return html;

  // "Separate packages" chips for the scope diagram are gathered document-wide:
  // the exclusions are usually named in a different section than the inclusions.
  // Matches are canonicalised to short nouns so the chips fit their box.
  const EXCLUSION_LABELS = [
    [/\bbooking\b/i, 'Booking'],
    [/\becommerce\b|\be-commerce\b/i, 'Ecommerce'],
    [/\bcustomer portals?\b|\bportals?\b/i, 'Portals'],
  ];
  const documentText = toText(html);
  const excluded = EXCLUSION_LABELS.filter(([re]) => re.test(documentText)).map(([, label]) => label);

  const used = new Set();
  const placements = [];

  /**
   * Try every (candidate, recipe) pair that passes `predicate`, best score
   * first, and return the first one that actually PRODUCES markup.
   *
   * The "actually produces markup" part matters: a recipe can match a section
   * on wording and still decline to draw — artFlowSteps needs >= 3 list items,
   * artScopeBoundary needs >= 3 inclusions. Treating a matched-but-empty recipe
   * as a filled slot silently swallowed the slot and suppressed the fallback,
   * so the loop keeps going instead of stopping at the first match.
   */
  const fill = (predicate, seedOffset) => {
    const ranked = [];
    candidates.forEach((candidate) => {
      if (!predicate(candidate)) return;
      candidate.scored.forEach((entry) => {
        if (used.has(entry.recipe.id)) return;
        ranked.push({ candidate, entry });
      });
    });
    ranked.sort((a, b) => b.entry.score - a.entry.score || a.candidate.depth - b.candidate.depth);
    for (const { candidate, entry } of ranked) {
      const svg = entry.recipe.build({ seed: seed + seedOffset, listItems: candidate.listItems, excluded });
      if (!svg) continue;
      used.add(entry.recipe.id);
      placements.push({ anchor: candidate.anchor, markup: wrapFigure(svg, entry.recipe.caption) });
      return candidate;
    }
    return null;
  };

  // ---- slot A: the strongest match in the first ~62% ----------------------
  const slotA = fill((c) => c.depth <= 0.62, 0) || fill(() => true, 0);
  if (!slotA) return html;

  // ---- slot B: a second, well-separated moment ----------------------------
  const minDepthB = slotA.depth + 0.22;
  if (!fill((c) => c.depth >= minDepthB, 7)) {
    // No further diagram earned its place. Fall back to the lighter blocks:
    // a pull quote if the article contains a sentence that stands alone, then
    // an ornamental divider, then nothing at all.
    const later = candidates.filter((c) => c.depth >= minDepthB);
    if (later.length) {
      const target = later[later.length - 1];
      const quote = pickQuote(sections, target.sectionIndex - 1);
      if (quote) {
        placements.push({ anchor: target.anchor, markup: artPullQuote({ seed: seed + 7, quote }) });
      } else if (blocks.length >= 12) {
        const heading = blocks[sections[target.sectionIndex].headingIndex];
        placements.push({ anchor: heading, markup: artDivider({ seed: seed + 7 }), before: true });
      }
    }
  }

  if (!placements.length) return html;
  placements.forEach(({ anchor, markup, before }) => {
    if (!markup) return;
    anchor.insertAdjacentHTML(before ? 'beforebegin' : 'afterend', markup);
  });
  return doc.body.innerHTML;
}
