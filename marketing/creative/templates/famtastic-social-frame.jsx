/*
 * FAMtastic social frame generator
 * -------------------------------------------------------------------------
 * Renders one ad concept across every platform aspect ratio, on brand, at
 * zero marginal cost, using the paid Photoshop desktop subscription.
 *
 * Run it through the local Photoshop MCP bridge:
 *
 *   mcp__photoshop-bridge__ps_run_script with:
 *     $.evalFile("<repo>/marketing/creative/templates/famtastic-social-frame.jsx");
 *     return renderFamtasticFrames(CONFIG);
 *
 * where CONFIG is:
 *   {
 *     slug:    "ad-link-in-bio",            // output filename stem
 *     outDir:  "/abs/path/to/output/",      // must end with a slash
 *     eyebrow: "FAMTASTIC DESIGNS",
 *     head1:   "A LINK IN BIO",             // white
 *     head2:   "IS NOT A WEBSITE.",         // lime
 *     body:    ["line one", "line two", "line three"],
 *     footer:  "famtasticdesigns.com/blog",
 *     formats: ["story-9x16","feed-4x5","square-1x1","wide-16x9"]  // optional
 *   }
 *
 * PRECONDITION: Adobe Photoshop must be RUNNING. The bridge drives the live
 * application; it cannot launch it.
 *
 * HARD CONSTRAINTS (learned the hard way, 2026-09-04):
 *   - ASCII ONLY. A non-ASCII character either throws
 *     "ExtendScript: Required value is missing" or renders as mojibake
 *     (a cent sign came back as two garbage glyphs). Spell out "cents".
 *   - Export with exportDocument/SaveForWeb, NEVER doc.saveAs with
 *     PNGSaveOptions. Same artwork: 47 KB vs 6.2 MB.
 *   - Fonts are PostScript names, not family names.
 *   - The empty zones in these layouts are deliberate. They are plate slots:
 *     cheap generated imagery composites UNDER this type. Never ask an image
 *     model to bake in text.
 *
 * Design DNA v1: ground #070907, accent #7cfc00, restraint over decoration.
 */

// --- Design DNA palette -----------------------------------------------------
function famColor(r, g, b) {
  var c = new SolidColor();
  c.rgb.red = r; c.rgb.green = g; c.rgb.blue = b;
  return c;
}
/*
 * PALETTES - the campaign variable.
 *
 * Owner directive 2026-09-04: "I don't want to get stuck in this damn black and
 * green. Campaigns are supposed to be thoughtful, not cookie cutter."
 *
 * That is a correction to how this template was first written, which hardcoded
 * #070907 + #7cfc00 and stamped it on everything. Black-and-lime is the
 * FAMtastic *site* identity. It is not an instruction to make every campaign
 * look like the site.
 *
 * WHAT STAYS CONSTANT (this is what makes it ours):
 *   - the typographic system: condensed bold display, humanist sans body,
 *     tight tracking on display, wide tracking on the eyebrow
 *   - the layout grid: left-aligned, generous margin, eyebrow + rule, two-line
 *     headline, short body, signature block
 *   - restraint: one glow, real negative space, art dissolving under type
 *   - the signature block
 *
 * WHAT VARIES BY CAMPAIGN:
 *   - the palette, chosen from the campaign's own subject matter
 *   - the art theme and texture
 *
 * Adding a palette is not decoration. Argue it from the subject, the way
 * ghost-town is argued from a sun-bleached empty storefront rather than from a
 * colour someone liked.
 */
var PALETTES = {
  // The house signature. Use when FAMtastic is talking about FAMtastic.
  "famtastic": {
    ground: [7, 9, 7], accent: [124, 252, 0], head: [255, 255, 255],
    body: [150, 163, 150], hair: [38, 48, 38],
    note: "Site identity. #070907 / #7cfc00."
  },
  // Sun-bleached, dusty, abandoned main street. For the ghost-town argument:
  // a business that exists but cannot be found.
  "ghost-town": {
    ground: [23, 18, 13], accent: [217, 164, 65], head: [242, 233, 216],
    body: [168, 152, 128], hair: [58, 47, 34],
    note: "Amber dust on dark earth. Absence, heat, weathering."
  },
  // Warm, low, intimate. Salon and personal-service work.
  "salon": {
    ground: [26, 16, 19], accent: [232, 180, 184], head: [250, 242, 240],
    body: [193, 170, 170], hair: [61, 40, 45],
    note: "Rose on plum. Skin-adjacent warmth, never clinical."
  },
  // Industrial, high-visibility. Trades, automotive, contractors.
  "trades": {
    ground: [13, 17, 23], accent: [255, 122, 26], head: [240, 246, 252],
    body: [139, 152, 165], hair: [32, 43, 56],
    note: "Safety orange on blue-black. Work, not lifestyle."
  },
  /*
   * MEASURED FROM THE PREMIUM ANCHOR, not chosen.
   *
   * Sampled from marketing/creative/heygen/renders/take-a-platform-dependency.mp4
   * across five frames and 113,000 pixels; see
   * marketing/creative/heygen/reference-tokens.json.
   *
   * Two things the measurement changed. The take is a LIGHT frame (mean
   * luminance 162 of 255), so grading a still to a near-black ground would make
   * it read as a different piece cut in beside the video. And the brand accent
   * does not appear as specified: #7cfc00 renders as #7fb449 under this take's
   * lighting - darker, olive rather than chartreuse - and covers only 1.31% of
   * frame area. Use this palette when a still must sit next to THIS take.
   */
  "anchor-take-a": {
    ground: [244, 242, 238], accent: [127, 180, 73], head: [51, 39, 46],
    body: [106, 96, 104], hair: [221, 216, 210],
    note: "Measured from take-a. Light ground, olive accent as rendered. Match, do not spec."
  },
  // Daylight. Documents, proposals, LinkedIn, anything that must read as
  // sober rather than as an ad. Proves the system is not a dark-mode trick.
  "paper": {
    ground: [244, 241, 234], accent: [31, 111, 74], head: [20, 18, 15],
    body: [90, 86, 78], hair: [214, 208, 196],
    note: "Ink on warm paper. Light ground; art uses MULTIPLY, not SCREEN."
  }
};

var ACTIVE = PALETTES["famtastic"];

// A light ground needs the art to darken rather than glow, or it disappears.
function famIsLightGround() {
  return (ACTIVE.ground[0] + ACTIVE.ground[1] + ACTIVE.ground[2]) / 3 > 128;
}

// Brand faces Inter / Space Grotesk are NOT installed on this machine.
// These are the documented stand-ins. See ADOBE_CREATIVE_PRODUCTION.md.
var FONT_DISPLAY = "HelveticaNeue-CondensedBold";
var FONT_UI      = "AvenirNext-Regular";
var FONT_UI_BOLD = "AvenirNext-DemiBold";

// name, W, H, margin, eyebrowSize, headSize, headLead, bodySize, footerSize, headTop
var FAM_FORMATS = {
  "story-9x16": [1080, 1920, 90, 30, 118, 138, 40, 38, 640],  // TikTok, Shorts, Stories, Reels
  "feed-4x5":   [1080, 1350, 84, 28, 104, 122, 38, 34, 470],  // Facebook + Instagram feed
  "square-1x1": [1080, 1080, 80, 27,  96, 114, 36, 32, 400],  // X, Facebook, Instagram
  "wide-16x9":  [1280,  720, 76, 24,  84, 100, 32, 28, 300],  // YouTube thumbnail, X inline
  "blog-hero":  [1200,  630, 72, 22,  74,  88, 28, 24, 250]   // OG card; crops to the 220px masthead
};

/*
 * ART REGIONS - where the layered graphic lives in each format.
 * [x, y, w, h] plus [glowCx, glowCy] for the atmosphere bloom.
 *
 * These regions deliberately BLEED UNDER the type. The art is composited with
 * SCREEN blend at low opacity, so anything approaching the #070907 ground goes
 * invisible and only the bright structure reads. That is what makes it dissolve
 * into the dark instead of sitting next to it as a separate block.
 *
 * Owner directive 2026-09-04: "the darkness is a perfect design opportunity to
 * have layered art with graphics and themes that fade into the background -
 * overlay, not stacked."
 */
var FAM_ART_REGIONS = {
  "story-9x16": [40, 980,  1040, 720, 760, 1300],
  "feed-4x5":   [40, 760,  1040, 420, 780,  940],
  "square-1x1": [520, 120,  560, 820, 860,  560],
  "wide-16x9":  [620,  60,  660, 600, 980,  330],
  "blog-hero":  [640,  40,  520, 550, 900,  315]
};


/*
 * Fit type to a column width.
 *
 * A concept object owns the right side of the frame, which means the headline no
 * longer has the full canvas. Without this, a long headline slides straight
 * under the artwork - which it did, on the first ghost-town hero.
 *
 * Average glyph width as a fraction of point size, measured against the faces
 * actually used here: condensed display is narrow, humanist body is not.
 */
// 0.42 underestimated caps-heavy display strings (YOURS, wide O/U/R/S), which
// let a word cross an extrusion edge and break the 3D read. 0.46 is measured
// safe for all-caps condensed bold and barely changes normal headlines.
var FAM_CHAR_W = { display: 0.46, body: 0.50, bodyBold: 0.52 };

function famFitSize(text, maxW, baseSize, kind) {
  var f = FAM_CHAR_W[kind] || FAM_CHAR_W.body;
  if (!text || !text.length) return baseSize;
  var fits = Math.floor(maxW / (text.length * f));
  return Math.max(12, Math.min(baseSize, fits));
}

function famText(doc, content, x, y, size, color, font, tracking, name) {
  var l = doc.artLayers.add();
  l.kind = LayerKind.TEXT;
  var t = l.textItem;
  t.contents = content;
  t.size = new UnitValue(size, 'pt');
  t.position = [new UnitValue(x, 'px'), new UnitValue(y, 'px')];  // baseline, not top
  t.color = famColor(color[0], color[1], color[2]);
  if (font) t.font = font;
  if (tracking) t.tracking = tracking;
  l.name = name;
  return l;
}

function famBar(doc, left, top, w, h, color, name) {
  var l = doc.artLayers.add();
  l.name = name;
  doc.selection.select([[left, top], [left + w, top], [left + w, top + h], [left, top + h]]);
  doc.selection.fill(famColor(color[0], color[1], color[2]), ColorBlendMode.NORMAL, 100);
  doc.selection.deselect();
  return l;
}

/* ==========================================================================
 * LAYERED ART SYSTEM
 *
 * Every generator draws bright structure on its own layer, blurs it, then sets
 * SCREEN blend + low opacity. Against a #070907 ground, SCREEN makes dark pixels
 * vanish entirely and bright pixels bloom - so the graphic fades into the
 * background by construction rather than by hand-masking. Type is drawn AFTER,
 * so it always sits on top at full strength.
 * ========================================================================== */

// A filled polygon on the CURRENT layer.
function famPoly(doc, pts, color, opacity) {
  doc.selection.select(pts);
  doc.selection.fill(famColor(color[0], color[1], color[2]), ColorBlendMode.NORMAL,
                     (opacity === undefined ? 100 : opacity));
  doc.selection.deselect();
}

// ExtendScript has no ellipse selection, so approximate with a polygon.
function famCircle(doc, cx, cy, r, color, opacity, sides) {
  var n = sides || 28, pts = [];
  for (var i = 0; i < n; i++) {
    var a = (Math.PI * 2 * i) / n;
    pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]);
  }
  famPoly(doc, pts, color, opacity);
}

// A line of given thickness between two points, as a quad.
function famLine(doc, x1, y1, x2, y2, thick, color, opacity) {
  var dx = x2 - x1, dy = y2 - y1;
  var len = Math.sqrt(dx * dx + dy * dy);
  if (len < 0.5) return;
  var nx = (-dy / len) * (thick / 2), ny = (dx / len) * (thick / 2);
  famPoly(doc, [[x1 + nx, y1 + ny], [x2 + nx, y2 + ny],
                [x2 - nx, y2 - ny], [x1 - nx, y1 - ny]], color, opacity);
}

function famFinishArtLayer(layer, blurPx, opacity) {
  if (blurPx > 0) layer.applyGaussianBlur(blurPx);
  // SCREEN dissolves bright art into a dark ground. On a light ground that
  // would erase it, so invert the logic rather than banning light palettes.
  layer.blendMode = famIsLightGround() ? BlendMode.MULTIPLY : BlendMode.SCREEN;
  layer.opacity = opacity;
}

// Soft lime bloom. Sells depth on its own and anchors the other themes.
function famAtmosphere(doc, cx, cy, radius, opacity) {
  var l = doc.artLayers.add(); l.name = "art-atmosphere";
  famCircle(doc, cx, cy, radius, ACTIVE.accent, 100, 40);
  famFinishArtLayer(l, Math.round(radius * 0.55), opacity === undefined ? 14 : opacity);
  return l;
}

// Connected node network - reads as a system being engineered. The house theme
// for "Agentic AI Business Solutions Engineering Studio".
function famThemeNodes(doc, region, count, seed) {
  var x = region[0], y = region[1], w = region[2], h = region[3];
  var l = doc.artLayers.add(); l.name = "art-nodes";
  var n = count || 16, s = seed || 7, pts = [];
  for (var i = 0; i < n; i++) {
    s = (s * 1103515245 + 12345) % 2147483648;
    var px = x + (s / 2147483648) * w;
    s = (s * 1103515245 + 12345) % 2147483648;
    var py = y + (s / 2147483648) * h;
    pts.push([px, py]);
  }
  // Edges first so nodes sit on top of them.
  for (var a = 0; a < pts.length; a++) {
    for (var b = a + 1; b < pts.length; b++) {
      var d = Math.sqrt(Math.pow(pts[a][0] - pts[b][0], 2) + Math.pow(pts[a][1] - pts[b][1], 2));
      if (d < Math.min(w, h) * 0.42) {
        famLine(doc, pts[a][0], pts[a][1], pts[b][0], pts[b][1], 2, ACTIVE.accent, 55);
      }
    }
  }
  for (var c = 0; c < pts.length; c++) {
    famCircle(doc, pts[c][0], pts[c][1], 7 + (c % 4) * 4, ACTIVE.accent, 100);
  }
  famFinishArtLayer(l, 2, 26);
  return l;
}

// Receding grid - infrastructure, the thing a real website sits on.
function famThemeGrid(doc, region, step) {
  var x = region[0], y = region[1], w = region[2], h = region[3];
  var l = doc.artLayers.add(); l.name = "art-grid";
  var gap = step || 78;
  for (var gx = x; gx <= x + w; gx += gap) famLine(doc, gx, y, gx, y + h, 1.5, ACTIVE.accent, 40);
  var row = 0;
  for (var gy = y; gy <= y + h; gy += gap) {
    famLine(doc, x, gy, x + w, gy, 1.5, ACTIVE.accent, Math.max(8, 60 - row * 6));
    row++;
  }
  famFinishArtLayer(l, 1, 20);
  return l;
}

// Concentric arcs - orbit/reach. Good behind a single short headline.
function famThemeOrbit(doc, region, rings) {
  var cx = region[0] + region[2] / 2, cy = region[1] + region[3] / 2;
  var l = doc.artLayers.add(); l.name = "art-orbit";
  var n = rings || 5, maxR = Math.min(region[2], region[3]) * 0.62;
  for (var i = 1; i <= n; i++) {
    var r = (maxR / n) * i, n2 = 64;
    for (var k = 0; k < n2; k++) {
      var a1 = (Math.PI * 2 * k) / n2, a2 = (Math.PI * 2 * (k + 1)) / n2;
      famLine(doc, cx + Math.cos(a1) * r, cy + Math.sin(a1) * r,
                   cx + Math.cos(a2) * r, cy + Math.sin(a2) * r, 2, ACTIVE.accent, 45);
    }
  }
  famFinishArtLayer(l, 2, 22);
  return l;
}

/* ==========================================================================
 * CONCEPT OBJECTS - art that argues the post's actual point
 *
 * Owner directive 2026-09-04, on the blog hero for "Why Gmail and Linktree Cost
 * Your Business Revenue":
 *
 *   "Why isn't the title considered for the blog image creation? What graphic
 *    would be nice? Maybe a business card, a URL, something. Not enough thought
 *    is going here."
 *
 * Correct, and it is the same failure as hardcoding one palette. The themes
 * above (nodes, grid, orbit) are ATMOSPHERE - they set a mood and belong behind
 * type. They do not say anything. A post arguing that a free email address costs
 * you credibility should show a business card, because the card IS the argument.
 *
 * So concept objects are drawn CRISP at full opacity, not dissolved. They are
 * the subject of the image, not its background. Pick one by reading the post's
 * claim, not its category.
 * ========================================================================== */

function famMix(a, b, t) {
  return [Math.round(a[0] + (b[0] - a[0]) * t),
          Math.round(a[1] + (b[1] - a[1]) * t),
          Math.round(a[2] + (b[2] - a[2]) * t)];
}

function famRect(doc, x, y, w, h, color, opacity) {
  famPoly(doc, [[x, y], [x + w, y], [x + w, y + h], [x, y + h]], color, opacity);
}

// Hollow rectangle, drawn as four bars.
function famRectOutline(doc, x, y, w, h, t, color, opacity) {
  famRect(doc, x, y, w, t, color, opacity);
  famRect(doc, x, y + h - t, w, t, color, opacity);
  famRect(doc, x, y, t, h, color, opacity);
  famRect(doc, x + w - t, y, t, h, color, opacity);
}

/*
 * Two business cards. The whole credibility argument in one object: the same
 * business, printed twice, once with an address anyone could have made and once
 * with an address at a domain it owns.
 *
 * cfg.cardBad  - the generic address (rendered muted, hairline border)
 * cfg.cardGood - the owned-domain address (rendered in accent, accent border)
 * cfg.cardName - the business name printed on both
 */
function famConceptBusinessCards(doc, region, cfg) {
  var x = region[0], y = region[1], w = region[2], h = region[3];
  var l = doc.artLayers.add(); l.name = "concept-business-cards";

  var cw = Math.min(Math.round(w * 0.80), 520);
  var ch = Math.round(cw * 0.58);
  var gap = Math.round(ch * 0.34);
  var totalH = ch * 2 + gap;
  var cx = x + Math.round((w - cw) / 2);
  var cy = y + Math.round((h - totalH) / 2);

  var faceBad  = famMix(ACTIVE.ground, ACTIVE.hair, 0.55);
  var faceGood = famMix(ACTIVE.ground, ACTIVE.hair, 0.85);
  var nameSize = Math.max(13, Math.round(ch * 0.115));
  // Fit the address to the card. An email address is a fixed string we cannot
  // shorten, so the type has to yield, not the object.
  var inner = cw - Math.round(cw * 0.14);
  var longest = Math.max((cfg.cardBad || "elite_autocare24@gmail.com").length,
                         (cfg.cardGood || "quotes@eliteautocare.com").length);
  var addrSize = Math.max(13, Math.min(Math.round(ch * 0.135),
                                       Math.floor(inner / (longest * 0.56))));

  // Card 1 - the generic address. Deliberately inert.
  famRect(doc, cx, cy, cw, ch, faceBad, 100);
  famRectOutline(doc, cx, cy, cw, ch, 2, ACTIVE.hair, 100);
  famRect(doc, cx + Math.round(cw * 0.07), cy + Math.round(ch * 0.24),
          Math.round(cw * 0.20), 3, ACTIVE.body, 55);

  // Card 2 - the owned domain. The one the eye is meant to land on.
  var c2y = cy + ch + gap;
  famRect(doc, cx, c2y, cw, ch, faceGood, 100);
  famRectOutline(doc, cx, c2y, cw, ch, 3, ACTIVE.accent, 100);
  famRect(doc, cx + Math.round(cw * 0.07), c2y + Math.round(ch * 0.24),
          Math.round(cw * 0.20), 3, ACTIVE.accent, 100);

  famFinishArtLayer(l, 0, 100);   // crisp: this is the subject, not atmosphere
  l.blendMode = BlendMode.NORMAL;

  var padX = cx + Math.round(cw * 0.07);
  famText(doc, cfg.cardName || "ELITE AUTO CARE", padX, cy + Math.round(ch * 0.44),
          nameSize, ACTIVE.body, FONT_UI_BOLD, 160, "card1-name");
  famText(doc, cfg.cardBad || "elite_autocare24@gmail.com", padX, cy + Math.round(ch * 0.75),
          addrSize, ACTIVE.body, FONT_UI, 0, "card1-addr");

  famText(doc, cfg.cardName || "ELITE AUTO CARE", padX, c2y + Math.round(ch * 0.44),
          nameSize, ACTIVE.accent, FONT_UI_BOLD, 160, "card2-name");
  famText(doc, cfg.cardGood || "quotes@eliteautocare.com", padX, c2y + Math.round(ch * 0.75),
          addrSize, ACTIVE.head, FONT_UI_BOLD, 0, "card2-addr");
  return l;
}

/*
 * A browser address bar. For posts about being found, being searched for, or
 * owning the address customers type.
 */
function famConceptAddressBar(doc, region, cfg) {
  var x = region[0], y = region[1], w = region[2], h = region[3];
  var l = doc.artLayers.add(); l.name = "concept-address-bar";

  var bw = Math.min(Math.round(w * 0.88), 560);
  var bh = Math.max(52, Math.round(bw * 0.135));
  var bx = x + Math.round((w - bw) / 2);
  var by = y + Math.round((h - bh) / 2);

  famRect(doc, bx, by, bw, bh, famMix(ACTIVE.ground, ACTIVE.hair, 0.75), 100);
  famRectOutline(doc, bx, by, bw, bh, 2, ACTIVE.accent, 100);
  // Padlock stand-in: a small solid block, readable at any size.
  famRect(doc, bx + Math.round(bh * 0.34), by + Math.round(bh * 0.34),
          Math.round(bh * 0.28), Math.round(bh * 0.32), ACTIVE.accent, 100);

  famFinishArtLayer(l, 0, 100);
  l.blendMode = BlendMode.NORMAL;

  famText(doc, cfg.url || "eliteautocare.com",
          bx + Math.round(bh * 1.05), by + Math.round(bh * 0.68),
          Math.max(18, Math.round(bh * 0.42)), ACTIVE.head, FONT_UI_BOLD, 0, "addr-url");
  return l;
}

// Place a generated plate (Gemini / gpt-image-2 / HeyGen frame) and dissolve it
// into the ground the same way. Plates must carry NO baked-in text.
function famPlate(doc, path, opacity, blurPx) {
  var f = new File(path);
  if (!f.exists) return null;
  var before = doc.artLayers.length;
  var idl = charIDToTypeID("Plc ");
  var d = new ActionDescriptor();
  d.putPath(charIDToTypeID("null"), f);
  d.putBoolean(charIDToTypeID("Lnkd"), false);
  executeAction(idl, d, DialogModes.NO);
  if (doc.artLayers.length <= before) return null;
  var l = doc.activeLayer;
  l.name = "art-plate";
  famFinishArtLayer(l, blurPx === undefined ? 0 : blurPx, opacity === undefined ? 38 : opacity);
  return l;
}

/*
 * Full-bleed plate: the generated image IS the background, not atmosphere behind
 * it. Placed at full strength, scaled to cover, then a scrim so type keeps its
 * contrast. This is the mode the Gemini plates are actually composed for - they
 * are generated with a reserved empty band precisely so type can land on it.
 */
function famPlateFull(doc, path, scrim) {
  var f = new File(path);
  if (!f.exists) return null;
  var before = doc.artLayers.length;
  var d = new ActionDescriptor();
  d.putPath(charIDToTypeID("null"), f);
  d.putBoolean(charIDToTypeID("Lnkd"), false);
  executeAction(charIDToTypeID("Plc "), d, DialogModes.NO);
  if (doc.artLayers.length <= before) return null;
  var l = doc.activeLayer;
  l.name = "plate-full";

  // Cover the canvas: scale up by the larger axis ratio.
  var b = l.bounds;
  var w = b[2].value - b[0].value, h = b[3].value - b[1].value;
  var k = Math.max(doc.width.value / w, doc.height.value / h) * 100;
  l.resize(k, k, AnchorPosition.MIDDLECENTER);

  // Scrim toward the palette ground so type holds contrast over any plate.
  var sc = doc.artLayers.add(); sc.name = "plate-scrim";
  doc.selection.selectAll();
  doc.selection.fill(famColor(ACTIVE.ground[0], ACTIVE.ground[1], ACTIVE.ground[2]),
                     ColorBlendMode.NORMAL, 100);
  doc.selection.deselect();
  sc.opacity = scrim === undefined ? 42 : scrim;
  return l;
}

/* Set when a full-bleed plate is composited, so the signature knows it is
 * sitting on photography rather than on flat ground. */
var FAM_PLATE_ACTIVE = false;

function famApplyArt(doc, key, cfg) {
  var region = FAM_ART_REGIONS[key];
  if (!region) return [];
  var applied = [];
  var box = [region[0], region[1], region[2], region[3]];

  // A full-bleed plate replaces atmosphere and theme entirely.
  if (cfg.plateFull) {
    if (famPlateFull(doc, cfg.plateFull, cfg.scrim)) { FAM_PLATE_ACTIVE = true; return ["plate-full"]; }
    return ["plate-full MISSING: " + cfg.plateFull];
  }

  if (cfg.atmosphere !== false) {
    // Keep the bloom as depth, not as a green wash over the ground. Design DNA
    // allows ONE glow; this is it, so it stays restrained.
    famAtmosphere(doc, region[4], region[5], Math.round(Math.min(box[2], box[3]) * 0.70),
                  cfg.atmosphereOpacity === undefined ? 10 : cfg.atmosphereOpacity);
    applied.push("atmosphere");
  }
  if (cfg.plate) {
    if (famPlate(doc, cfg.plate, cfg.plateOpacity, cfg.plateBlur)) applied.push("plate");
    else applied.push("plate MISSING: " + cfg.plate);
  }
  // A concept object REPLACES the abstract theme. Atmosphere may stay behind it.
  if (cfg.concept) {
    if (cfg.concept === "business-cards") { famConceptBusinessCards(doc, box, cfg); return applied.concat(["concept:business-cards"]); }
    if (cfg.concept === "address-bar")    { famConceptAddressBar(doc, box, cfg);    return applied.concat(["concept:address-bar"]); }
    applied.push("concept UNKNOWN: " + cfg.concept);
  }

  var theme = cfg.theme || "nodes";
  if (theme === "nodes")      { famThemeNodes(doc, box, cfg.nodeCount, cfg.seed); applied.push("nodes"); }
  else if (theme === "grid")  { famThemeGrid(doc, box, cfg.gridStep);             applied.push("grid"); }
  else if (theme === "orbit") { famThemeOrbit(doc, box, cfg.rings);               applied.push("orbit"); }
  return applied;
}

// Compressed export. doc.saveAs with PNGSaveOptions is ~130x larger.
function famExportPNG(doc, path) {
  var opts = new ExportOptionsSaveForWeb();
  opts.format = SaveDocumentType.PNG;
  opts.PNG8 = false;
  opts.transparency = false;
  opts.interlaced = false;
  doc.exportDocument(new File(path), ExportType.SAVEFORWEB, opts);
}

function renderFamtasticFrames(cfg) {
  // Campaign palette. Defaults to the house signature, but every campaign is
  // expected to argue for its own. See PALETTES.
  var palName = cfg.palette || "famtastic";
  ACTIVE = PALETTES[palName] || PALETTES["famtastic"];

  var formats = cfg.formats || ["story-9x16", "feed-4x5", "square-1x1", "wide-16x9"];
  var body = cfg.body || [];
  var written = [];

  for (var i = 0; i < formats.length; i++) {
    var key = formats[i];
    var F = FAM_FORMATS[key];
    if (!F) { written.push(key + " SKIPPED: unknown format"); continue; }

    var W = F[0], H = F[1], M = F[2], ES = F[3], HS = F[4], HL = F[5],
        BS = F[6], FS = F[7], HT = F[8];

    var doc = app.documents.add(W, H, 72, cfg.slug + "-" + key);
    doc.artLayers[0].isBackgroundLayer = false;
    doc.selection.selectAll();
    doc.selection.fill(famColor(ACTIVE.ground[0], ACTIVE.ground[1], ACTIVE.ground[2]),
                       ColorBlendMode.NORMAL, 100);
    doc.selection.deselect();

    // ART FIRST, TYPE ON TOP. Never the other way around.
    FAM_PLATE_ACTIVE = false;   // per-frame; must not leak into the next format
    var art = famApplyArt(doc, key, cfg);

    /*
     * Type origin. Plates are generated with a RESERVED EMPTY BAND
     * (prompt-library.json records it per variant as negative_space), and until
     * now the template ignored that and always set type hard left - so type
     * landed on the busy half of a plate that had deliberately cleared the other
     * side. Pass typeSide:'right' for a right-reserved plate.
     */
    var TX = M;
    if (cfg.typeSide === 'right') TX = Math.round(W * 0.52);
    else if (typeof cfg.typeOffsetX === 'number') TX = M + cfg.typeOffsetX;
    var TW = W - TX - M;

    famText(doc, cfg.eyebrow, TX, Math.round(H * 0.135), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");
    famBar(doc, TX, Math.round(H * 0.155), Math.round(W * 0.155),
           Math.max(4, Math.round(H * 0.004)), ACTIVE.accent, "rule-top");

    // When a concept object owns the right side, type gets the left column only.
    var colW = TW;
    if (cfg.concept && FAM_ART_REGIONS[key]) {
      colW = Math.max(Math.round(W * 0.30), FAM_ART_REGIONS[key][0] - M - 40);
    }
    var hs = Math.min(famFitSize(cfg.head1, colW, HS, "display"),
                      famFitSize(cfg.head2, colW, HS, "display"));
    famText(doc, cfg.head1, TX, HT,      hs, ACTIVE.head,   FONT_DISPLAY, -20, "head-1");
    famText(doc, cfg.head2, TX, HT + HL, hs, ACTIVE.accent, FONT_DISPLAY, -20, "head-2");

    var bodyTop = HT + HL + Math.round(HL * 0.72);
    var bs = BS;
    for (var bf = 0; bf < body.length; bf++) {
      bs = Math.min(bs, famFitSize(body[bf], colW, BS, "body"));
    }
    for (var b = 0; b < body.length; b++) {
      famText(doc, body[b], TX, bodyTop + Math.round(bs * 1.45 * b), bs,
              ACTIVE.body, FONT_UI, 0, "body-" + (b + 1));
    }

    // A concept object owns the right side, so the rule stops at the text column
    // rather than sliding behind the artwork.
    var ruleW = W - (M * 2);
    if (cfg.concept && FAM_ART_REGIONS[key]) {
      ruleW = Math.max(Math.round(W * 0.28), FAM_ART_REGIONS[key][0] - M - 40);
    }
    // Same protection the archetype layouts get: over photography the signature
    // carries its own ground, or it renders as grey type on wood grain.
    if (FAM_PLATE_ACTIVE) {
      var sBandTop = H - Math.round(H * 0.195);
      var sBand = doc.artLayers.add(); sBand.name = "signature-scrim";
      famRect(doc, 0, sBandTop, W, H - sBandTop, ACTIVE.ground, 100);
      sBand.opacity = 94;
    }
    famBar(doc, M, H - Math.round(H * 0.135), ruleW, 2, ACTIVE.hair, "rule-bot");

    /*
     * Studio signature. Carried as IDENTITY, never as a claim.
     * VOICE.md:122 bans "agentic" as vocabulary used to impress; this is the
     * studio's own name, which is the one legitimate use. It belongs in the
     * signature block and must never migrate into a headline or a benefit line.
     * Canonical wording matches the live site H1 and title tag.
     */
    var studio = cfg.studio || "AGENTIC AI BUSINESS SOLUTIONS ENGINEERING STUDIO";
    if (studio !== "none") {
      famText(doc, studio, M, H - Math.round(H * 0.088),
              Math.max(14, Math.round(FS * 0.58)), ACTIVE.body, FONT_UI_BOLD, 120, "studio");
    }
    famText(doc, cfg.footer, M, H - Math.round(H * 0.038), FS, ACTIVE.accent, FONT_UI_BOLD, 40, "footer");

    var path = cfg.outDir + cfg.slug + "-" + key + ".png";
    famExportPNG(doc, path);
    written.push(key + " " + W + "x" + H + " art[" + art.join(",") + "] -> " + path);
    doc.close(SaveOptions.DONOTSAVECHANGES);
  }
  return { slug: cfg.slug, palette: palName, written: written };
}

/* ==========================================================================
 * LAYOUT ARCHETYPES
 *
 * Owner correction 2026-09-04: "there isn't enough variety in your designs.
 * You need to see how Cox does it or something."
 *
 * Fair. Every asset above used ONE composition - eyebrow, rule, two-line
 * headline, body, signature - and varied only the palette. Changing colour is
 * not variety; it is the same poster in a different shirt.
 *
 * Studied cox.com directly. Across a single page they alternate at least seven
 * compositions: split panel with photo bleeding to one edge, offer card with a
 * badge chip and a CTA pill, thin utility strip, three-up card grid, icon nav
 * row, inline form band, and price-as-hero. The variety comes from a small kit
 * of reusable OBJECTS - chips, pills, tinted panels, cards - recombined, not
 * from bespoke art each time.
 *
 * So: a component kit, then archetypes built from it.
 * ========================================================================== */

/* ---- Components --------------------------------------------------------- */

// Depth on a 2D surface. A dark ground cannot take a drop shadow (black on
// near-black is nothing), so it gets a lit edge instead; a light ground gets a
// real cast shadow. Same intent, opposite physics.
function famElevate(doc, x, y, w, h, strength) {
  var s = strength || 1;
  var l = doc.artLayers.add(); l.name = "elevation";
  if (famIsLightGround()) {
    famRect(doc, x + Math.round(6 * s), y + Math.round(8 * s), w, h, [0, 0, 0], 100);
    l.applyGaussianBlur(Math.round(14 * s));
    l.blendMode = BlendMode.MULTIPLY;
    l.opacity = 22;
  } else {
    famRect(doc, x - Math.round(5 * s), y - Math.round(5 * s), w, h, ACTIVE.accent, 100);
    l.applyGaussianBlur(Math.round(18 * s));
    l.blendMode = BlendMode.SCREEN;
    l.opacity = 16;
  }
  return l;
}

// Tinted panel. The single most useful thing borrowed from Cox: a block of
// held colour that a section sits inside, instead of everything floating on one
// flat ground.
function famPanel(doc, x, y, w, h, mixT, cutCorner) {
  var l = doc.artLayers.add(); l.name = "panel";
  // Lift toward the headline colour for a neutral panel, then a whisper of
  // accent. Tinting straight to the accent muddies warm palettes.
  var lift = famMix(ACTIVE.ground, ACTIVE.head, mixT === undefined ? 0.09 : mixT);
  var face = famMix(lift, ACTIVE.accent, 0.06);
  if (cutCorner) {
    // Angled corner - a non-rectangular edge, so the frame stops reading as a
    // stack of boxes.
    var c = Math.round(Math.min(w, h) * 0.16);
    famPoly(doc, [[x, y], [x + w, y], [x + w, y + h - c], [x + w - c, y + h],
                  [x, y + h]], face, 100);
  } else {
    famRect(doc, x, y, w, h, face, 100);
  }
  return l;
}

// Badge chip: small caps label in a solid block. Cox's "SPECIAL OFFER".
function famChip(doc, x, y, text, size) {
  var padX = Math.round(size * 0.9), padY = Math.round(size * 0.62);
  var w = Math.round(text.length * size * 0.62) + padX * 2;
  var h = size + padY * 2;
  var l = doc.artLayers.add(); l.name = "chip";
  famRect(doc, x, y, w, h, ACTIVE.accent, 100);
  famText(doc, text, x + padX, y + h - padY - Math.round(size * 0.15), size,
          ACTIVE.ground, FONT_UI_BOLD, 140, "chip-text");
  return { w: w, h: h };
}

// CTA pill. An actual button object, not a bare URL in the footer.
function famPill(doc, x, y, text, size) {
  var padX = Math.round(size * 1.5), padY = Math.round(size * 0.72);
  var w = Math.round(text.length * size * 0.60) + padX * 2;
  var h = size + padY * 2;
  var r = Math.round(h / 2);
  var l = doc.artLayers.add(); l.name = "pill";
  famRect(doc, x + r, y, w - r * 2, h, ACTIVE.accent, 100);
  famCircle(doc, x + r, y + r, r, ACTIVE.accent, 100);
  famCircle(doc, x + w - r, y + r, r, ACTIVE.accent, 100);
  famText(doc, text, x + padX, y + h - padY - Math.round(size * 0.16), size,
          ACTIVE.ground, FONT_UI_BOLD, 20, "pill-text");
  return { w: w, h: h };
}

// Extruded display type. Depth without a bevel filter: a copy behind, offset,
// in accent. Reads as dimensional on both light and dark grounds.
function famText3D(doc, text, x, y, size, depth, name) {
  var d = depth || Math.round(size * 0.06);
  famText(doc, text, x + d, y + d, size, ACTIVE.accent, FONT_DISPLAY, -20, name + "-back");
  famText(doc, text, x, y, size, ACTIVE.head, FONT_DISPLAY, -20, name);
}

// Shared signature block, so every archetype closes the same way.
function famSignature(doc, W, H, M, FS, cfg, ruleW) {
  /*
   * Over a plate the signature was rendering dark grey on mid-brown wood and
   * was effectively unreadable. A photograph does not owe the type any
   * particular value, so the signature carries its own ground when it is
   * standing on one.
   */
  if (FAM_PLATE_ACTIVE) {
    var bandTop = H - Math.round(H * 0.195);
    var band = doc.artLayers.add(); band.name = "signature-scrim";
    famRect(doc, 0, bandTop, W, H - bandTop, ACTIVE.ground, 100);
    band.opacity = 94;  // 82 still let wood grain read through the studio line
  }
  famBar(doc, M, H - Math.round(H * 0.135), ruleW || (W - M * 2), 2, ACTIVE.hair, "rule-bot");
  var studio = cfg.studio || "AGENTIC AI BUSINESS SOLUTIONS ENGINEERING STUDIO";
  if (studio !== "none") {
    famText(doc, studio, M, H - Math.round(H * 0.088),
            Math.max(14, Math.round(FS * 0.58)), ACTIVE.body, FONT_UI_BOLD, 120, "studio");
  }
  famText(doc, cfg.footer, M, H - Math.round(H * 0.038), FS, ACTIVE.accent,
          FONT_UI_BOLD, 40, "footer");
}

function famNewDoc(W, H, name) {
  var doc = app.documents.add(W, H, 72, name);
  doc.artLayers[0].isBackgroundLayer = false;
  doc.selection.selectAll();
  doc.selection.fill(famColor(ACTIVE.ground[0], ACTIVE.ground[1], ACTIVE.ground[2]),
                     ColorBlendMode.NORMAL, 100);
  doc.selection.deselect();
  return doc;
}

/* ---- Archetypes --------------------------------------------------------- */

/*
 * OFFER CARD - price is the hero.
 * Badge chip, headline, an enormous number, one line of terms, a CTA pill.
 * For the moment a campaign is actually asking for the sale.
 */
function famLayoutOfferCard(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], BS = F[6], FS = F[7];
  var px = M, pw = W - M * 2;
  var py = Math.round(H * 0.20), ph = Math.round(H * 0.50);

  famText(doc, cfg.eyebrow, M, Math.round(H * 0.115), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");
  famElevate(doc, px, py, pw, ph, 1);
  famPanel(doc, px, py, pw, ph, 0.12, true);

  var inx = px + Math.round(pw * 0.07);
  var chip = famChip(doc, inx, py + Math.round(ph * 0.09),
                     cfg.chip || "SPECIAL OFFER", Math.max(16, Math.round(BS * 0.62)));

  var headSize = famFitSize(cfg.head1, pw - Math.round(pw * 0.14), Math.round(F[4] * 0.62), "display");
  famText(doc, cfg.head1, inx, py + Math.round(ph * 0.34), headSize,
          ACTIVE.head, FONT_DISPLAY, -20, "oc-head");

  var priceSize = famFitSize(cfg.price || "55 cents", pw - Math.round(pw * 0.14),
                             Math.round(F[4] * 1.35), "display");
  famText3D(doc, cfg.price || "55 cents", inx, py + Math.round(ph * 0.58), priceSize,
            Math.round(priceSize * 0.035), "oc-price");

  famText(doc, cfg.terms || "a day, first year all in", inx, py + Math.round(ph * 0.70),
          Math.max(16, Math.round(BS * 0.86)), ACTIVE.body, FONT_UI, 0, "oc-terms");
  famPill(doc, inx, py + Math.round(ph * 0.78), cfg.cta || "See what is included",
          Math.max(16, Math.round(BS * 0.74)));

  famSignature(doc, W, H, M, FS, cfg);
}

/*
 * SPLIT - a full-bleed band of held colour against the ground.
 * The band carries a single statement; the ground carries the explanation.
 * Cox uses this constantly and it is the fastest way out of "text on a field".
 */
function famLayoutSplit(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], HS = F[4], HL = F[5], BS = F[6], FS = F[7];
  var bandH = Math.round(H * 0.42), bandY = Math.round(H * 0.10);

  famPanel(doc, 0, bandY, W, bandH, 0.16, false);
  // Diagonal accent cut along the band's lower edge - a non-standard shape, so
  // the composition is not four stacked rectangles.
  var cut = Math.round(bandH * 0.18);
  var cl = doc.artLayers.add(); cl.name = "band-cut";
  famPoly(doc, [[0, bandY + bandH], [W, bandY + bandH - cut],
                [W, bandY + bandH], [0, bandY + bandH]], ACTIVE.accent, 100);
  cl.opacity = 70;

  famText(doc, cfg.eyebrow, M, bandY + Math.round(bandH * 0.20), ES,
          ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");

  var hs = Math.min(famFitSize(cfg.head1, W - M * 2, HS, "display"),
                    famFitSize(cfg.head2, W - M * 2, HS, "display"));
  famText(doc, cfg.head1, M, bandY + Math.round(bandH * 0.55), hs, ACTIVE.head, FONT_DISPLAY, -20, "sp-h1");
  famText(doc, cfg.head2, M, bandY + Math.round(bandH * 0.55) + Math.round(hs * 1.12), hs,
          ACTIVE.accent, FONT_DISPLAY, -20, "sp-h2");

  var body = cfg.body || [], bodyTop = bandY + bandH + Math.round(H * 0.09);
  var bs = BS;
  for (var i = 0; i < body.length; i++) bs = Math.min(bs, famFitSize(body[i], W - M * 2, BS, "body"));
  for (var b = 0; b < body.length; b++) {
    famText(doc, body[b], M, bodyTop + Math.round(bs * 1.45 * b), bs, ACTIVE.body, FONT_UI, 0, "sp-b" + b);
  }
  if (cfg.cta) famPill(doc, M, bodyTop + Math.round(bs * 1.45 * body.length) + Math.round(H * 0.02),
                       cfg.cta, Math.max(16, Math.round(BS * 0.74)));
  famSignature(doc, W, H, M, FS, cfg);
}

/*
 * STAT - one number, very large, and the sentence that gives it meaning.
 * Use only with a figure that is verifiable from the repo. No invented stats.
 */
function famLayoutStat(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], BS = F[6], FS = F[7];
  famText(doc, cfg.eyebrow, M, Math.round(H * 0.135), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");
  famBar(doc, M, Math.round(H * 0.155), Math.round(W * 0.155), 6, ACTIVE.accent, "rule-top");

  var big = cfg.stat || "199";
  var statSize = famFitSize(big, W - M * 2, Math.round(H * 0.26), "display");
  famText3D(doc, big, M, Math.round(H * 0.50), statSize, Math.round(statSize * 0.035), "stat");

  var cap = cfg.body || [];
  var bs = Math.round(F[6] * 1.05);
  for (var i = 0; i < cap.length; i++) bs = Math.min(bs, famFitSize(cap[i], W - M * 2, bs, "body"));
  for (var c = 0; c < cap.length; c++) {
    famText(doc, cap[c], M, Math.round(H * 0.60) + Math.round(bs * 1.45 * c), bs,
            ACTIVE.body, FONT_UI, 0, "stat-cap" + c);
  }
  if (cfg.cta) famPill(doc, M, Math.round(H * 0.60) + Math.round(bs * 1.45 * cap.length) + Math.round(H * 0.03),
                       cfg.cta, Math.max(16, Math.round(BS * 0.74)));
  famSignature(doc, W, H, M, FS, cfg);
}

/*
 * CHECKLIST - what is actually included. Answers the real objection instead of
 * asserting value. Each row is a marker plus a fact.
 */
function famLayoutChecklist(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], HS = F[4], BS = F[6], FS = F[7];
  famText(doc, cfg.eyebrow, M, Math.round(H * 0.115), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");

  var hs = famFitSize(cfg.head1, W - M * 2, Math.round(HS * 0.78), "display");
  famText(doc, cfg.head1, M, Math.round(H * 0.215), hs, ACTIVE.head, FONT_DISPLAY, -20, "cl-head");

  var items = cfg.items || [];
  var top = Math.round(H * 0.30), rowH = Math.round((H * 0.46) / Math.max(items.length, 1));
  var isz = Math.max(18, Math.min(Math.round(F[6] * 1.05), Math.round(rowH * 0.42)));

  var rl = doc.artLayers.add(); rl.name = "checklist-rows";
  for (var i = 0; i < items.length; i++) {
    var ry = top + rowH * i;
    famRect(doc, M, ry + Math.round(rowH * 0.30), Math.round(isz * 0.55), Math.round(isz * 0.55),
            ACTIVE.accent, 100);
    if (i > 0) famRect(doc, M, ry, W - M * 2, 1, ACTIVE.hair, 100);
  }
  for (var j = 0; j < items.length; j++) {
    var ty = top + rowH * j;
    famText(doc, items[j], M + Math.round(isz * 1.35), ty + Math.round(rowH * 0.30) + Math.round(isz * 0.52),
            famFitSize(items[j], W - M * 2 - Math.round(isz * 1.35), isz, "body"),
            ACTIVE.head, FONT_UI, 0, "cl-item" + j);
  }
  if (cfg.cta) famPill(doc, M, top + rowH * items.length + Math.round(H * 0.02),
                       cfg.cta, Math.max(16, Math.round(BS * 0.74)));
  famSignature(doc, W, H, M, FS, cfg);
}

var FAM_LAYOUTS = {
  "offer-card": famLayoutOfferCard,
  "split":      famLayoutSplit,
  "stat":       famLayoutStat,
  "checklist":  famLayoutChecklist
};

/*
 * Entry point. layout:"standard" (or omitted) keeps the original composition;
 * anything in FAM_LAYOUTS renders that archetype instead.
 */
function famRender(cfg) {
  if (!cfg.layout || cfg.layout === "standard") return renderFamtasticFrames(cfg);
  var fn = FAM_LAYOUTS[cfg.layout];
  if (!fn) return { error: "unknown layout: " + cfg.layout,
                    known: ["standard", "offer-card", "split", "stat", "checklist"] };

  ACTIVE = PALETTES[cfg.palette || "famtastic"] || PALETTES["famtastic"];
  var formats = cfg.formats || ["story-9x16"];
  var written = [];
  for (var i = 0; i < formats.length; i++) {
    var key = formats[i], F = FAM_FORMATS[key];
    if (!F) { written.push(key + " SKIPPED: unknown format"); continue; }
    var doc = famNewDoc(F[0], F[1], cfg.slug + "-" + key);
    FAM_PLATE_ACTIVE = false;
    fn(doc, F, cfg);
    var path = cfg.outDir + cfg.slug + "-" + key + ".png";
    famExportPNG(doc, path);
    written.push(key + " " + F[0] + "x" + F[1] + " [" + cfg.layout + "] -> " + path);
    doc.close(SaveOptions.DONOTSAVECHANGES);
  }
  return { slug: cfg.slug, layout: cfg.layout, palette: cfg.palette || "famtastic", written: written };
}

/* ==========================================================================
 * DIMENSION - real 3D on a 2D surface
 *
 * Owner direction 2026-09-04: "start thinking in 3D effect on 2D surfaces, and
 * non-standard shapes and colors."
 *
 * The first pass at this was a drop shadow, which is not depth - it is a hint of
 * depth. Real dimension needs visible side faces. These build isometric solids
 * out of polygons, so they are deterministic and need no rotation, no filters,
 * and no 3D engine. Shade the faces and the eye does the rest.
 *
 * Light is treated as coming from the upper left throughout, so the right face
 * is darker than the top and the bottom face is darkest. Break that consistency
 * and the whole frame stops reading as solid.
 * ========================================================================== */

function famShade(color, t) {
  // t < 0 darkens toward black, t > 0 lifts toward the headline colour.
  return t < 0 ? famMix(color, [0, 0, 0], -t) : famMix(color, ACTIVE.head, t);
}

/*
 * An extruded rectangular block. depth is the isometric offset in pixels;
 * positive pushes the solid down and to the right.
 */
function famExtrudeRect(doc, x, y, w, h, depth, faceColor, name) {
  var d = depth === undefined ? 26 : depth;
  var face = faceColor || famMix(ACTIVE.ground, ACTIVE.head, 0.12);
  var l = doc.artLayers.add(); l.name = name || "solid";

  // Right face, then bottom face, then the top face over both.
  famPoly(doc, [[x + w, y], [x + w + d, y + d],
                [x + w + d, y + h + d], [x + w, y + h]], famShade(face, -0.34), 100);
  famPoly(doc, [[x, y + h], [x + w, y + h],
                [x + w + d, y + h + d], [x + d, y + h + d]], famShade(face, -0.55), 100);
  famPoly(doc, [[x, y], [x + w, y], [x + w, y + h], [x, y + h]], face, 100);
  return l;
}

/*
 * A slab of accent colour standing on edge - a bar chart column, a plinth, a
 * block of held colour with actual thickness.
 */
function famExtrudeBar(doc, x, y, w, h, depth, color, name) {
  var d = depth === undefined ? 18 : depth;
  var c = color || ACTIVE.accent;
  var l = doc.artLayers.add(); l.name = name || "bar";
  famPoly(doc, [[x + w, y], [x + w + d, y + d],
                [x + w + d, y + h + d], [x + w, y + h]], famShade(c, -0.40), 100);
  famPoly(doc, [[x, y], [x + d, y - 0], [x + w + d, y + d], [x + w, y]],
          famShade(c, 0.22), 100);
  famPoly(doc, [[x, y], [x + w, y], [x + w, y + h], [x, y + h]], c, 100);
  return l;
}

/*
 * Perspective grid receding to a vanishing point. A floor for solids to stand
 * on, so extruded objects do not float.
 */
function famPerspectiveGrid(doc, region, vx, vy, lines) {
  var x = region[0], y = region[1], w = region[2], h = region[3];
  var n = lines || 12;
  var l = doc.artLayers.add(); l.name = "art-perspective";
  for (var i = 0; i <= n; i++) {
    var px = x + (w / n) * i;
    famLine(doc, px, y + h, vx, vy, 2, ACTIVE.accent, 42);
  }
  // Horizontals compress toward the vanishing point.
  for (var r = 1; r <= 7; r++) {
    var t = Math.pow(r / 8, 2.1);
    var ry = y + h - (y + h - vy) * t;
    famLine(doc, x, ry, x + w, ry, 1.5, ACTIVE.accent, Math.round(40 * (1 - t) + 8));
  }
  famFinishArtLayer(l, 1, 24);
  return l;
}

/*
 * Composite a texture over the frame. Textures live in
 * marketing/creative/textures/ and carry no colour of their own - they exist to
 * give a flat surface tooth. SOFT_LIGHT keeps the palette intact where OVERLAY
 * would blow out the accent.
 */
function famTexture(doc, path, opacity, mode) {
  var f = new File(path);
  if (!f.exists) return null;
  var before = doc.artLayers.length;
  var d = new ActionDescriptor();
  d.putPath(charIDToTypeID("null"), f);
  d.putBoolean(charIDToTypeID("Lnkd"), false);
  executeAction(charIDToTypeID("Plc "), d, DialogModes.NO);
  if (doc.artLayers.length <= before) return null;
  var l = doc.activeLayer;
  l.name = "texture";
  l.blendMode = (mode === "overlay") ? BlendMode.OVERLAY : BlendMode.SOFTLIGHT;
  l.opacity = opacity === undefined ? 26 : opacity;
  return l;
}

/*
 * COMPARISON - two solids, unequal. The argument is carried by the geometry:
 * the thing you rent is thin, the thing you own has mass.
 *
 * Not a chart. A chart implies measurement and would need a sourced number;
 * this is a shape contrast, which claims nothing it cannot support.
 */
function famLayoutComparison(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], HS = F[4], BS = F[6], FS = F[7];

  famText(doc, cfg.eyebrow, M, Math.round(H * 0.115), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");
  var hs = famFitSize(cfg.head1, W - M * 2, Math.round(HS * 0.80), "display");
  famText(doc, cfg.head1, M, Math.round(H * 0.205), hs, ACTIVE.head, FONT_DISPLAY, -20, "cmp-head");

  var baseY = Math.round(H * 0.66);
  var colW = Math.round((W - M * 2) * 0.34);
  var gap = Math.round((W - M * 2) * 0.18);
  var depth = Math.round(colW * 0.16);

  var leftH = Math.round(H * 0.055);
  var rightH = Math.round(H * 0.235);

  famPerspectiveGrid(doc, [M, Math.round(H * 0.42), W - M * 2, Math.round(H * 0.26)],
                     Math.round(W / 2), Math.round(H * 0.40), 10);

  famExtrudeBar(doc, M, baseY - leftH, colW, leftH, depth,
                famMix(ACTIVE.ground, ACTIVE.head, 0.26), "solid-rented");
  famExtrudeBar(doc, M + colW + gap, baseY - rightH, colW, rightH, depth,
                ACTIVE.accent, "solid-owned");

  var lbl = Math.max(16, Math.round(BS * 0.80));
  famText(doc, cfg.labelA || "RENTED", M, baseY + depth + Math.round(H * 0.045), lbl,
          ACTIVE.body, FONT_UI_BOLD, 160, "cmp-lbl-a");
  famText(doc, cfg.labelB || "OWNED", M + colW + gap, baseY + depth + Math.round(H * 0.045), lbl,
          ACTIVE.accent, FONT_UI_BOLD, 160, "cmp-lbl-b");

  var body = cfg.body || [];
  var bs = BS;
  for (var i = 0; i < body.length; i++) bs = Math.min(bs, famFitSize(body[i], W - M * 2, BS, "body"));
  for (var b = 0; b < body.length; b++) {
    famText(doc, body[b], M, baseY + depth + Math.round(H * 0.095) + Math.round(bs * 1.45 * b),
            bs, ACTIVE.body, FONT_UI, 0, "cmp-b" + b);
  }
  famSignature(doc, W, H, M, FS, cfg);
}

/*
 * MONUMENT - one extruded slab carrying a single word or number, standing on a
 * perspective floor. For the moment a campaign needs presence rather than
 * explanation.
 */
function famLayoutMonument(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], BS = F[6], FS = F[7];
  famText(doc, cfg.eyebrow, M, Math.round(H * 0.115), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");

  famPerspectiveGrid(doc, [0, Math.round(H * 0.46), W, Math.round(H * 0.30)],
                     Math.round(W / 2), Math.round(H * 0.44), 14);

  // Optional headline fills the upper third; without it the slab carries alone.
  if (cfg.head1) {
    var mhs = famFitSize(cfg.head1, W - M * 2, Math.round(F[4] * 0.80), "display");
    famText(doc, cfg.head1, M, Math.round(H * 0.235), mhs, ACTIVE.head, FONT_DISPLAY, -20, "mon-head");
  }

  var bw = Math.round(W * 0.70), bh = Math.round(H * 0.20);
  var bx = M, by = Math.round(H * 0.44);
  var mdepth = Math.round(bw * 0.055);
  famExtrudeRect(doc, bx, by, bw, bh, mdepth,
                 famMix(ACTIVE.ground, ACTIVE.head, 0.14), "monument");

  // Fit inside the TOP FACE, not the silhouette - the extrusion depth is not
  // usable width, and the word ran off the right edge when it was counted.
  var word = cfg.word || "YOURS";
  var ws = famFitSize(word, bw - Math.round(bw * 0.12) - mdepth, Math.round(bh * 0.82), "display");
  famText3D(doc, word, bx + Math.round(bw * 0.06), by + Math.round(bh * 0.70), ws,
            Math.round(ws * 0.035), "monument-word");

  var body = cfg.body || [];
  var bs = BS;
  for (var i = 0; i < body.length; i++) bs = Math.min(bs, famFitSize(body[i], W - M * 2, BS, "body"));
  for (var b = 0; b < body.length; b++) {
    famText(doc, body[b], M, Math.round(H * 0.74) + Math.round(bs * 1.45 * b), bs,
            ACTIVE.body, FONT_UI, 0, "mon-b" + b);
  }
  if (cfg.cta) {
    var pillY = Math.round(H * 0.74) + Math.round(bs * 1.45 * body.length) + Math.round(H * 0.02);
    var ceiling = H - Math.round(H * 0.135) - Math.round(BS * 2.6);
    famPill(doc, M, Math.min(pillY, ceiling), cfg.cta, Math.max(16, Math.round(BS * 0.74)));
  }
  famSignature(doc, W, H, M, FS, cfg);
}

FAM_LAYOUTS["comparison"] = famLayoutComparison;
FAM_LAYOUTS["monument"]   = famLayoutMonument;
