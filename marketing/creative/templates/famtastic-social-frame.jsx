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
var FAM_CHAR_W = { display: 0.42, body: 0.50, bodyBold: 0.52 };

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

function famApplyArt(doc, key, cfg) {
  var region = FAM_ART_REGIONS[key];
  if (!region) return [];
  var applied = [];
  var box = [region[0], region[1], region[2], region[3]];

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
    var art = famApplyArt(doc, key, cfg);

    famText(doc, cfg.eyebrow, M, Math.round(H * 0.135), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");
    famBar(doc, M, Math.round(H * 0.155), Math.round(W * 0.155),
           Math.max(4, Math.round(H * 0.004)), ACTIVE.accent, "rule-top");

    // When a concept object owns the right side, type gets the left column only.
    var colW = W - (M * 2);
    if (cfg.concept && FAM_ART_REGIONS[key]) {
      colW = Math.max(Math.round(W * 0.30), FAM_ART_REGIONS[key][0] - M - 40);
    }
    var hs = Math.min(famFitSize(cfg.head1, colW, HS, "display"),
                      famFitSize(cfg.head2, colW, HS, "display"));
    famText(doc, cfg.head1, M, HT,      hs, ACTIVE.head,   FONT_DISPLAY, -20, "head-1");
    famText(doc, cfg.head2, M, HT + HL, hs, ACTIVE.accent, FONT_DISPLAY, -20, "head-2");

    var bodyTop = HT + HL + Math.round(HL * 0.72);
    var bs = BS;
    for (var bf = 0; bf < body.length; bf++) {
      bs = Math.min(bs, famFitSize(body[bf], colW, BS, "body"));
    }
    for (var b = 0; b < body.length; b++) {
      famText(doc, body[b], M, bodyTop + Math.round(bs * 1.45 * b), bs,
              ACTIVE.body, FONT_UI, 0, "body-" + (b + 1));
    }

    // A concept object owns the right side, so the rule stops at the text column
    // rather than sliding behind the artwork.
    var ruleW = W - (M * 2);
    if (cfg.concept && FAM_ART_REGIONS[key]) {
      ruleW = Math.max(Math.round(W * 0.28), FAM_ART_REGIONS[key][0] - M - 40);
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
