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
    famText(doc, cfg.footer, M, H - Math.round(H * 0.075), FS, FAM.lime, FONT_UI_BOLD, 40, "footer");

    var path = cfg.outDir + cfg.slug + "-" + key + ".png";
    famExportPNG(doc, path);
    written.push(key + " " + W + "x" + H + " -> " + path);
    doc.close(SaveOptions.DONOTSAVECHANGES);
  }
  return { slug: cfg.slug, written: written };
}
