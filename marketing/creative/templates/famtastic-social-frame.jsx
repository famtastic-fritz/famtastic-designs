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
var FAM = {
  lime:   [124, 252, 0],    // #7cfc00 accent
  white:  [255, 255, 255],
  dim:    [150, 163, 150],  // body copy
  hair:   [38, 48, 38],     // divider rules
  ground: [7, 9, 7]         // #070907
};

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
  "wide-16x9":  [1280,  720, 76, 24,  84, 100, 32, 28, 300]   // YouTube thumbnail, X inline
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
  "wide-16x9":  [620,  60,  660, 600, 980,  330]
};

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
  layer.blendMode = BlendMode.SCREEN;   // dark pixels disappear against the ground
  layer.opacity = opacity;
}

// Soft lime bloom. Sells depth on its own and anchors the other themes.
function famAtmosphere(doc, cx, cy, radius, opacity) {
  var l = doc.artLayers.add(); l.name = "art-atmosphere";
  famCircle(doc, cx, cy, radius, FAM.lime, 100, 40);
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
        famLine(doc, pts[a][0], pts[a][1], pts[b][0], pts[b][1], 2, FAM.lime, 55);
      }
    }
  }
  for (var c = 0; c < pts.length; c++) {
    famCircle(doc, pts[c][0], pts[c][1], 7 + (c % 4) * 4, FAM.lime, 100);
  }
  famFinishArtLayer(l, 2, 26);
  return l;
}

// Receding grid - infrastructure, the thing a real website sits on.
function famThemeGrid(doc, region, step) {
  var x = region[0], y = region[1], w = region[2], h = region[3];
  var l = doc.artLayers.add(); l.name = "art-grid";
  var gap = step || 78;
  for (var gx = x; gx <= x + w; gx += gap) famLine(doc, gx, y, gx, y + h, 1.5, FAM.lime, 40);
  var row = 0;
  for (var gy = y; gy <= y + h; gy += gap) {
    famLine(doc, x, gy, x + w, gy, 1.5, FAM.lime, Math.max(8, 60 - row * 6));
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
                   cx + Math.cos(a2) * r, cy + Math.sin(a2) * r, 2, FAM.lime, 45);
    }
  }
  famFinishArtLayer(l, 2, 22);
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
    doc.selection.fill(famColor(FAM.ground[0], FAM.ground[1], FAM.ground[2]),
                       ColorBlendMode.NORMAL, 100);
    doc.selection.deselect();

    // ART FIRST, TYPE ON TOP. Never the other way around.
    var art = famApplyArt(doc, key, cfg);

    famText(doc, cfg.eyebrow, M, Math.round(H * 0.135), ES, FAM.lime, FONT_UI_BOLD, 240, "eyebrow");
    famBar(doc, M, Math.round(H * 0.155), Math.round(W * 0.155),
           Math.max(4, Math.round(H * 0.004)), FAM.lime, "rule-top");

    famText(doc, cfg.head1, M, HT,      HS, FAM.white, FONT_DISPLAY, -20, "head-1");
    famText(doc, cfg.head2, M, HT + HL, HS, FAM.lime,  FONT_DISPLAY, -20, "head-2");

    var bodyTop = HT + HL + Math.round(HL * 0.72);
    for (var b = 0; b < body.length; b++) {
      famText(doc, body[b], M, bodyTop + Math.round(BS * 1.45 * b), BS,
              FAM.dim, FONT_UI, 0, "body-" + (b + 1));
    }

    famBar(doc, M, H - Math.round(H * 0.135), W - (M * 2), 2, FAM.hair, "rule-bot");

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
              Math.max(14, Math.round(FS * 0.58)), FAM.dim, FONT_UI_BOLD, 120, "studio");
    }
    famText(doc, cfg.footer, M, H - Math.round(H * 0.038), FS, FAM.lime, FONT_UI_BOLD, 40, "footer");

    var path = cfg.outDir + cfg.slug + "-" + key + ".png";
    famExportPNG(doc, path);
    written.push(key + " " + W + "x" + H + " art[" + art.join(",") + "] -> " + path);
    doc.close(SaveOptions.DONOTSAVECHANGES);
  }
  return { slug: cfg.slug, written: written };
}
