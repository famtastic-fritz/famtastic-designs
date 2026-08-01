/**
 * Deterministic placeholder imagery.
 *
 * Proof sites currently ship with no images at all, so a prospect opening
 * "three designs for your business" sees text and coloured boxes. Real photos
 * of their business don't exist yet at proof time, and stock photography is a
 * licensing problem — so this generates abstract, brand-coloured compositions
 * instead. Seeded from the business name, so the same business always gets the
 * same artwork and the three directions differ from each other.
 *
 * Output is a standalone SVG string: no raster step, no network, embeddable
 * directly in generated HTML or written to a file.
 */

/** FNV-1a — small, fast, and stable across runs and platforms. */
function hashString(input) {
  let h = 2166136261;
  for (let i = 0; i < input.length; i += 1) {
    h ^= input.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

function seededRandom(seed) {
  let s = seed || 1;
  return () => {
    s = (s * 1664525 + 1013904223) % 4294967296;
    return s / 4294967296;
  };
}

/** Palettes matched to the three proof directions. */
export const PALETTES = {
  a: { bg: '#0a0a0a', accent: '#7cfc00', second: '#38bdf8' },
  b: { bg: '#0f172a', accent: '#38bdf8', second: '#a78bfa' },
  c: { bg: '#1a1410', accent: '#f59e0b', second: '#fb7185' },
};

/**
 * @param {object} opts
 * @param {string} opts.seedText   Business name — drives the composition.
 * @param {string} [opts.variant]  'a' | 'b' | 'c', or pass an explicit palette.
 * @param {object} [opts.palette]  {bg, accent, second} overriding variant.
 * @param {number} [opts.width]
 * @param {number} [opts.height]
 * @returns {string} SVG markup
 */
export function placeholderSvg({
  seedText = 'business',
  variant = 'a',
  palette,
  width = 1600,
  height = 900,
} = {}) {
  const p = palette ?? PALETTES[variant] ?? PALETTES.a;
  // Mixing the variant into the seed keeps a/b/c visually distinct for one
  // business, rather than three recolours of an identical composition.
  const rand = seededRandom(hashString(`${seedText}::${variant}`));

  const cx = width * (0.58 + rand() * 0.22);
  const cy = height * (0.32 + rand() * 0.28);
  const ringCount = 5 + Math.floor(rand() * 4);
  const step = Math.min(width, height) * (0.075 + rand() * 0.035);
  const rotation = Math.round(rand() * 60 - 30);

  const rings = Array.from({ length: ringCount }, (_, i) => {
    const r = step * (i + 1.5);
    const dash = i % 2 === 0 ? 'none' : `${Math.round(r * 0.14)} ${Math.round(r * 0.09)}`;
    const opacity = (0.42 - i * 0.045).toFixed(3);
    return `<circle cx="${cx.toFixed(1)}" cy="${cy.toFixed(1)}" r="${r.toFixed(1)}" fill="none" stroke="${p.accent}" stroke-opacity="${opacity}" stroke-width="${(1.6 + i * 0.25).toFixed(1)}"${dash === 'none' ? '' : ` stroke-dasharray="${dash}"`}/>`;
  }).join('');

  // A sparse isometric lattice reads as "engineered" without becoming busy.
  const cols = 7 + Math.floor(rand() * 4);
  const bars = Array.from({ length: cols }, (_, i) => {
    const x = (width / cols) * i + width * 0.02;
    const h = height * (0.08 + rand() * 0.34);
    const y = height - h - height * 0.06;
    const fill = i % 3 === 0 ? p.second : p.accent;
    return `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${(width / cols * 0.16).toFixed(1)}" height="${h.toFixed(1)}" fill="${fill}" fill-opacity="${(0.10 + rand() * 0.16).toFixed(3)}" rx="2"/>`;
  }).join('');

  const dotRows = 6;
  const dots = Array.from({ length: dotRows * 14 }, (_, i) => {
    const col = i % 14;
    const row = Math.floor(i / 14);
    const x = width * 0.04 + col * (width * 0.026);
    const y = height * 0.08 + row * (height * 0.038);
    return `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="2.2" fill="${p.accent}" fill-opacity="0.18"/>`;
  }).join('');

  // Gradient ids must be unique per SVG. Two placeholders on one page (three
  // proof directions side by side, say) share a document id space, so a fixed
  // id means the first definition paints all of them — every variant came out
  // the first one's colour.
  const uid = `fam${hashString(`${seedText}::${variant}::${width}x${height}`).toString(36)}`;

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img" aria-label="Abstract brand illustration">
  <defs>
    <radialGradient id="${uid}-bloom" cx="${(cx / width).toFixed(3)}" cy="${(cy / height).toFixed(3)}" r="0.75">
      <stop offset="0%" stop-color="${p.accent}" stop-opacity="0.26"/>
      <stop offset="55%" stop-color="${p.accent}" stop-opacity="0.05"/>
      <stop offset="100%" stop-color="${p.accent}" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="${width}" height="${height}" fill="${p.bg}"/>
  <rect width="${width}" height="${height}" fill="url(#${uid}-bloom)"/>
  ${dots}
  <g transform="rotate(${rotation} ${cx.toFixed(1)} ${cy.toFixed(1)})">${rings}</g>
  ${bars}
</svg>`;
}

/** Base64 data URI, for embedding straight into an <img src> or CSS. */
export function placeholderDataUri(opts) {
  return `data:image/svg+xml;base64,${Buffer.from(placeholderSvg(opts)).toString('base64')}`;
}
