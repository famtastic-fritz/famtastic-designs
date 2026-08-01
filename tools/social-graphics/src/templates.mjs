/**
 * Social graphic templates.
 *
 * Each post is rendered as a self-contained HTML document sized to exact
 * platform pixels, then screenshotted by Chromium. Everything is vector or
 * type — there is no photography here, which is deliberate: a price-led offer
 * reads better as a typographic statement than as stock imagery, and it stays
 * legible at thumbnail size in a feed.
 *
 * Brand tokens mirror frontend/src/index.css so graphics and site agree.
 */

const BRAND = {
  bg: '#0a0a0a',
  surface: '#111111',
  lime: '#7cfc00',
  text: '#ffffff',
  muted: 'rgba(255,255,255,0.62)',
  faint: 'rgba(255,255,255,0.28)',
  border: 'rgba(255,255,255,0.10)',
};

/** Canvas sizes, keyed by the name used in posts.json. */
export const SIZES = {
  square: { w: 1080, h: 1080, label: 'Facebook / Instagram feed' },
  portrait: { w: 1080, h: 1350, label: 'Instagram feed (taller = more reach)' },
  story: { w: 1080, h: 1920, label: 'Facebook / Instagram story' },
  link: { w: 1200, h: 630, label: 'Link share / Open Graph' },
};

/**
 * Deterministic constellation echoing the site's hero ParticleField.
 * Seeded so a given post always renders identically — re-running the tool
 * must not silently produce a different image.
 */
function constellation(w, h, seed) {
  let s = seed;
  const rand = () => {
    s = (s * 1664525 + 1013904223) % 4294967296;
    return s / 4294967296;
  };

  const count = Math.round((w * h) / 42000);
  const nodes = Array.from({ length: count }, () => ({
    x: rand() * w,
    y: rand() * h,
    r: 1.4 + rand() * 2.2,
  }));

  const links = [];
  for (let i = 0; i < nodes.length; i += 1) {
    for (let j = i + 1; j < nodes.length; j += 1) {
      const dx = nodes[i].x - nodes[j].x;
      const dy = nodes[i].y - nodes[j].y;
      if (Math.hypot(dx, dy) < w * 0.17) links.push([nodes[i], nodes[j]]);
    }
  }

  return `<svg class="field" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" aria-hidden="true">
    ${links
      .map(([a, b]) => `<line x1="${a.x.toFixed(1)}" y1="${a.y.toFixed(1)}" x2="${b.x.toFixed(1)}" y2="${b.y.toFixed(1)}"/>`)
      .join('')}
    ${nodes.map((n) => `<circle cx="${n.x.toFixed(1)}" cy="${n.y.toFixed(1)}" r="${n.r.toFixed(1)}"/>`).join('')}
  </svg>`;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

/**
 * Headlines accept a single `*emphasis*` span, rendered in lime. Keeps the
 * copy file readable without a markdown dependency.
 */
function headline(text) {
  return escapeHtml(text).replace(/\*([^*]+)\*/g, '<em>$1</em>');
}

export function renderPost(post, sizeName, fonts) {
  const size = SIZES[sizeName];
  if (!size) throw new Error(`Unknown size "${sizeName}".`);
  const { w, h } = size;

  // One scale factor drives every dimension, so a layout tuned at 1080 square
  // holds together at 1200x630 and 1080x1920 without per-size hand-tweaking.
  const scale = w / 1080;
  const tall = h / w >= 1.6;
  const wide = w / h >= 1.6;

  const pad = Math.round((wide ? 72 : 88) * scale);

  // Sizes are budgeted against the available content height rather than picked
  // by eye: a post carrying price + headline + support has three heavy blocks
  // competing for the same column, and at display scale the overflow silently
  // pushes the footer off-canvas instead of wrapping.
  // 1200x630 is only 470px of content height after padding — not enough for
  // price + headline + support together. Link previews render small anyway and
  // the description comes from the page's meta tags, so the support line is
  // dropped rather than shrunk into illegibility.
  const showSupport = Boolean(post.support) && !wide;
  const dense = Boolean(post.price) && showSupport;
  const priceSize = Math.round((wide ? 148 : tall ? 300 : dense ? 236 : 320) * scale);
  const headSize = Math.round((wide ? 44 : tall ? 76 : dense ? 62 : 76) * scale);
  const supportSize = Math.round((wide ? 24 : tall ? 34 : 29) * scale);
  const urlSize = Math.round((wide ? 27 : tall ? 40 : 34) * scale);
  const eyebrowSize = Math.round((wide ? 18 : 23) * scale);

  return `<!doctype html>
<html><head><meta charset="utf-8"><style>
  @font-face { font-family: 'Display'; src: url(data:font/ttf;base64,${fonts.displayBold}) format('truetype'); font-weight: 700; }
  @font-face { font-family: 'Body'; src: url(data:font/ttf;base64,${fonts.bodyRegular}) format('truetype'); font-weight: 400; }
  @font-face { font-family: 'Body'; src: url(data:font/ttf;base64,${fonts.bodyBold}) format('truetype'); font-weight: 700; }

  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { width: ${w}px; height: ${h}px; }
  body {
    background: ${BRAND.bg};
    color: ${BRAND.text};
    font-family: 'Body', sans-serif;
    overflow: hidden;
    position: relative;
  }

  /* Lime bloom anchored off-canvas so the falloff stays smooth, never banded. */
  .glow {
    position: absolute; inset: 0;
    background:
      radial-gradient(${Math.round(w * 1.1)}px ${Math.round(h * 0.8)}px at ${wide ? '88%' : '78%'} -12%,
        rgba(124,252,0,0.20), rgba(124,252,0,0.05) 42%, transparent 68%),
      radial-gradient(${Math.round(w * 0.9)}px ${Math.round(h * 0.7)}px at -10% 108%,
        rgba(56,189,248,0.10), transparent 62%);
  }
  .field { position: absolute; inset: 0; }
  .field line { stroke: rgba(124,252,0,0.13); stroke-width: ${Math.max(1, 1.1 * scale)}; }
  .field circle { fill: rgba(124,252,0,0.42); }
  /* Vignette keeps type off the busiest part of the constellation. */
  .veil {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% 55%, rgba(10,10,10,0.82) 0%, rgba(10,10,10,0.55) 45%, transparent 78%);
  }

  /* Three fixed rows — eyebrow, content, footer. The middle row is the only
     flexible one, so the footer can never be pushed past the canvas edge. */
  .frame {
    position: absolute; inset: ${pad}px;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    gap: ${Math.round(34 * scale)}px;
  }
  .middle {
    min-height: 0;
    display: flex; flex-direction: column; justify-content: center;
    gap: ${Math.round((dense ? 22 : 32) * scale)}px;
  }

  .eyebrow {
    /* justify-self, not align-self: the frame is a grid, so a flex-start
       alignment on the wrong axis lets the pill stretch the full width. */
    display: inline-flex; justify-self: start; align-items: center;
    gap: ${Math.round(12 * scale)}px;
    padding: ${Math.round(13 * scale)}px ${Math.round(26 * scale)}px;
    border: 1px solid rgba(124,252,0,0.42);
    border-radius: 999px;
    font-size: ${eyebrowSize}px; font-weight: 700;
    letter-spacing: ${Math.round(2.4 * scale)}px; text-transform: uppercase;
    color: ${BRAND.lime};
    background: rgba(124,252,0,0.07);
  }
  .eyebrow::before {
    content: ''; width: ${Math.round(9 * scale)}px; height: ${Math.round(9 * scale)}px;
    border-radius: 50%; background: ${BRAND.lime};
  }

  .price {
    font-family: 'Display', sans-serif; font-weight: 700;
    font-size: ${priceSize}px; line-height: 0.9;
    letter-spacing: ${Math.round(priceSize * -0.036)}px;
    color: ${BRAND.lime};
    /* Optical inset: the glyph's own sidebearing already reads as margin. */
    margin-left: ${Math.round(priceSize * -0.022)}px;
  }

  h1 {
    font-family: 'Display', sans-serif; font-weight: 700;
    font-size: ${headSize}px; line-height: 1.06;
    letter-spacing: ${Math.round(headSize * -0.028)}px;
    max-width: ${wide ? '80%' : '94%'};
  }
  h1 em { font-style: normal; color: ${BRAND.lime}; }

  .support {
    font-size: ${supportSize}px; line-height: 1.5;
    color: ${BRAND.muted};
    max-width: ${wide ? '68%' : '84%'};
  }

  .foot {
    display: flex; align-items: flex-end; justify-content: space-between;
    gap: ${Math.round(24 * scale)}px;
    border-top: 1px solid ${BRAND.border};
    padding-top: ${Math.round(30 * scale)}px;
  }
  .url {
    font-family: 'Display', sans-serif; font-weight: 700;
    font-size: ${urlSize}px; letter-spacing: ${Math.round(-0.6 * scale)}px;
    color: ${BRAND.text};
  }
  .url span { color: ${BRAND.lime}; }
  .mark {
    text-align: right;
    font-size: ${Math.round(eyebrowSize * 0.86)}px;
    letter-spacing: ${Math.round(2.6 * scale)}px; text-transform: uppercase;
    color: ${BRAND.faint};
    line-height: 1.5;
  }
</style></head>
<body>
  <div class="glow"></div>
  ${constellation(w, h, post.seed ?? 20260801)}
  <div class="veil"></div>
  <div class="frame">
    <div class="eyebrow">${escapeHtml(post.eyebrow)}</div>
    <div class="middle">
      ${post.price ? `<div class="price">${escapeHtml(post.price)}</div>` : ''}
      <h1>${headline(post.headline)}</h1>
      ${showSupport ? `<p class="support">${escapeHtml(post.support)}</p>` : ''}
    </div>
    <div class="foot">
      <div class="url">famtasticdesigns.com<span>/199</span></div>
      <div class="mark">FAMtastic<br>Designs</div>
    </div>
  </div>
</body></html>`;
}

export { BRAND };
