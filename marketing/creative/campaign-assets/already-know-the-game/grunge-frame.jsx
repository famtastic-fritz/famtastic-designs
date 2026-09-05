/*
 * grunge-frame.jsx - campaign compositor for "already-know-the-game".
 *
 * WHY THIS FILE EXISTS, AND WHY IT IS NOT AN EDIT TO THE SHARED TEMPLATE
 *
 * famtastic-social-frame.jsx composites a full-bleed plate only in its
 * "standard" path (famApplyArt). The archetype layouts - offer-card, split,
 * stat, checklist, comparison, monument - build their own document and draw
 * straight onto flat palette ground, so a plate never reaches them. This
 * campaign needs BOTH: rotated archetypes (Rule 3) AND a photographic grunge
 * surface under every frame.
 *
 * Rather than change shared behaviour while three other campaign lanes are
 * running against the same file, this driver evaluates the template, reuses its
 * component kit and archetype functions verbatim, and adds only the compositing
 * order the grunge treatment needs:
 *
 *     ground -> plate (cover) -> scrim -> xerox texture (SOFT_LIGHT) -> grain
 *            -> torn band behind the type zone -> archetype or standard type
 *
 * The only shared-file change this campaign makes is the PALETTES.shutter entry.
 *
 * Usage (Photoshop must be running):
 *   $.evalFile("<repo>/marketing/creative/campaign-assets/already-know-the-game/grunge-frame.jsx");
 *   agRender({ ... });
 *
 * ASCII only in every string. A non-ASCII character throws
 * "ExtendScript: Required value is missing" and leaves an empty text layer.
 */

var AG_REPO = "/Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/";
$.evalFile(AG_REPO + "marketing/creative/templates/famtastic-social-frame.jsx");

var AG_TEXTURE = AG_REPO + "marketing/creative/campaign-assets/already-know-the-game/plates/akg-tex-xerox-9x16.jpg";

/* Deterministic jitter. Two runs of the same frame must be byte-identical, so
 * no Math.random anywhere in this file. */
var AG_SEED = 20260906;
function agRnd() {
  AG_SEED = (AG_SEED * 9301 + 49297) % 233280;
  return AG_SEED / 233280;
}
function agReseed(s) { AG_SEED = s; }

/* Place an image and scale it to COVER the canvas. Same maths famPlateFull uses;
 * lifted out so a texture can use it too - famTexture places at native size,
 * which leaves a 768px-wide file uncovered on a 1080px canvas. */
function agPlaceCover(doc, path, name, shiftY) {
  var f = new File(path);
  if (!f.exists) return null;
  var before = doc.artLayers.length;
  var d = new ActionDescriptor();
  d.putPath(charIDToTypeID("null"), f);
  d.putBoolean(charIDToTypeID("Lnkd"), false);
  executeAction(charIDToTypeID("Plc "), d, DialogModes.NO);
  if (doc.artLayers.length <= before) return null;
  var l = doc.activeLayer;
  l.name = name;
  var b = l.bounds;
  var w = b[2].value - b[0].value, h = b[3].value - b[1].value;
  var k = Math.max(doc.width.value / w, doc.height.value / h) * 100;
  l.resize(k, k, AnchorPosition.MIDDLECENTER);
  /*
   * A 9:16 plate cover-scaled into 4:5 or 1:1 crops from the CENTRE, which on
   * every object plate in this campaign dropped the subject - the bay marking,
   * the signboard, the flyer pole - straight under the signature scrim. Caught
   * by looking at the first square render, not by any check. shiftY moves the
   * plate UP so the lower half of the source survives the crop.
   */
  if (shiftY) l.translate(0, new UnitValue(-shiftY, 'px'));
  return l;
}

/* A flat wash of palette ground at a given opacity. Used to hold type contrast
 * over a photograph without hand-masking. */
function agScrim(doc, opacity, name) {
  var sc = doc.artLayers.add();
  sc.name = name || "scrim";
  doc.selection.selectAll();
  doc.selection.fill(famColor(ACTIVE.ground[0], ACTIVE.ground[1], ACTIVE.ground[2]),
                     ColorBlendMode.NORMAL, 100);
  doc.selection.deselect();
  sc.opacity = opacity;
  return sc;
}

/*
 * A torn strip of dark ground behind the type block.
 *
 * This is the campaign's tape-and-torn-edge move and it is also load-bearing,
 * not decorative: plate 01 and plate 05 both carry bright concrete and white
 * paper exactly where the headline lands, and a flat scrim strong enough to fix
 * that would kill the photograph. A torn band fixes contrast locally and reads
 * as a pasted strip rather than as a UI panel.
 */
function agTornBand(doc, x, y, w, h, opacity, seed) {
  agReseed(seed || 4242);
  var l = doc.artLayers.add();
  l.name = "torn-band";
  var steps = 30;
  var jag = Math.max(6, Math.round(h * 0.045));
  var pts = [];
  var i;
  for (i = 0; i <= steps; i++) {
    pts.push([x + (w / steps) * i, y + Math.round((agRnd() - 0.5) * jag * 2)]);
  }
  for (i = steps; i >= 0; i--) {
    pts.push([x + (w / steps) * i, y + h + Math.round((agRnd() - 0.5) * jag * 2)]);
  }
  famPoly(doc, pts, ACTIVE.ground, 100);
  l.opacity = opacity === undefined ? 86 : opacity;
  return l;
}

/*
 * A stencil-paint mark: a short accent bar with a ragged end, the way a roller
 * or a brush actually stops. Sits under the eyebrow so the frame opens with
 * pigment rather than with a hairline rule.
 */
function agPaintMark(doc, x, y, w, h, seed) {
  agReseed(seed || 918);
  var l = doc.artLayers.add();
  l.name = "paint-mark";
  var steps = 12;
  var pts = [[x, y]];
  var i;
  for (i = 0; i <= steps; i++) {
    pts.push([x + (w / steps) * i, y + Math.round((agRnd() - 0.5) * h * 0.5)]);
  }
  pts.push([x + w + Math.round(h * 0.9), y + Math.round(h * 0.5)]);
  for (i = steps; i >= 0; i--) {
    pts.push([x + (w / steps) * i, y + h + Math.round((agRnd() - 0.5) * h * 0.5)]);
  }
  famPoly(doc, pts, ACTIVE.accent, 100);
  l.opacity = 100;
  return l;
}

/*
 * TWO OVERRIDES OF SHARED COMPONENTS, both found by looking at renders.
 *
 * 1. famChip sizes its block at text.length * size * 0.62, which ignores the
 *    140/1000-em tracking it then applies to the label. "WEB BASICS" ran its
 *    final S off the right edge of the yellow block. 0.62 + 0.14 is the honest
 *    advance width at that tracking.
 *
 * 2. famLayoutOfferCard places the headline at 0.34 of panel height and the
 *    price baseline at 0.58, with the price sized off the format's display
 *    size. At 1:1 the panel is short enough that the price cap-height climbed
 *    into the headline and the two collided. This version spaces the rows off
 *    panel height and caps the price against the panel, not the format.
 *
 * Both are overridden here rather than patched in the shared template because
 * three other campaign lanes are rendering against that file right now. The
 * defects are recorded in this campaign's README for a later shared fix.
 */
function famChip(doc, x, y, text, size) {
  var padX = Math.round(size * 0.9), padY = Math.round(size * 0.62);
  var w = Math.round(text.length * size * 0.76) + padX * 2;
  var h = size + padY * 2;
  var l = doc.artLayers.add(); l.name = "chip";
  famRect(doc, x, y, w, h, ACTIVE.accent, 100);
  famText(doc, text, x + padX, y + h - padY - Math.round(size * 0.15), size,
          ACTIVE.ground, FONT_UI_BOLD, 140, "chip-text");
  return { w: w, h: h };
}

function famLayoutOfferCard(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], BS = F[6], FS = F[7];
  var px = M, pw = W - M * 2;
  var py = Math.round(H * 0.20), ph = Math.round(H * 0.50);
  var inx = px + Math.round(pw * 0.07);
  var innerW = pw - Math.round(pw * 0.14);

  famText(doc, cfg.eyebrow, M, Math.round(H * 0.115), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");
  famElevate(doc, px, py, pw, ph, 1);
  famPanel(doc, px, py, pw, ph, 0.12, true);

  famChip(doc, inx, py + Math.round(ph * 0.08), cfg.chip || "SPECIAL OFFER",
          Math.max(16, Math.round(BS * 0.62)));

  var headSize = famFitSize(cfg.head1, innerW, Math.round(F[4] * 0.62), "display");
  famText(doc, cfg.head1, inx, py + Math.round(ph * 0.26), headSize,
          ACTIVE.head, FONT_DISPLAY, -20, "oc-head");

  var priceCap = Math.min(Math.round(ph * 0.40), Math.round(F[4] * 1.35));
  var priceSize = famFitSize(cfg.price || "55 cents", innerW, priceCap, "display");
  famText3D(doc, cfg.price || "55 cents", inx, py + Math.round(ph * 0.68), priceSize,
            Math.round(priceSize * 0.035), "oc-price");

  var termSize = famFitSize(cfg.terms || "", innerW, Math.max(16, Math.round(BS * 0.86)), "body");
  famText(doc, cfg.terms || "", inx, py + Math.round(ph * 0.80), termSize,
          ACTIVE.body, FONT_UI, 0, "oc-terms");
  famPill(doc, inx, py + Math.round(ph * 0.88), cfg.cta || "See what is included",
          Math.max(16, Math.round(BS * 0.74)));

  famSignature(doc, W, H, M, FS, cfg);
}
FAM_LAYOUTS["offer-card"] = famLayoutOfferCard;

/* Photocopy tooth over the whole frame, then film grain on a merged pass. */
function agSurface(doc, cfg) {
  var tex = agPlaceCover(doc, AG_TEXTURE, "xerox-texture");
  if (tex) {
    tex.blendMode = BlendMode.SOFTLIGHT;
    tex.opacity = cfg.textureOpacity === undefined ? 30 : cfg.textureOpacity;
  }
  return tex ? "texture" : "texture MISSING";
}

/*
 * The standard composition's type stack, re-implemented here because
 * renderFamtasticFrames() owns its own document lifecycle and cannot be handed
 * one that already carries a composited plate. Identical grid, sizes and
 * signature; the difference is the torn band and the paint mark.
 */
function agTypeStandard(doc, F, cfg) {
  var W = F[0], H = F[1], M = F[2], ES = F[3], HS = F[4], HL = F[5],
      BS = F[6], FS = F[7], HT = F[8];
  var body = cfg.body || [];
  var colW = W - M * 2;

  var hs = Math.min(famFitSize(cfg.head1, colW, HS, "display"),
                    famFitSize(cfg.head2, colW, HS, "display"));

  var bs = BS;
  var i;
  for (i = 0; i < body.length; i++) bs = Math.min(bs, famFitSize(body[i], colW, BS, "body"));

  var bodyTop = HT + HL + Math.round(HL * 0.72);
  var bandTop = Math.round(H * 0.105) - Math.round(ES * 0.4);
  var bandBot = bodyTop + Math.round(bs * 1.45 * Math.max(body.length - 1, 0)) + Math.round(bs * 0.9);
  agTornBand(doc, -20, bandTop, W + 40, bandBot - bandTop,
             cfg.bandOpacity === undefined ? 84 : cfg.bandOpacity, cfg.seed);

  agPaintMark(doc, M, Math.round(H * 0.152), Math.round(W * 0.155),
              Math.max(6, Math.round(H * 0.006)), (cfg.seed || 0) + 31);
  famText(doc, cfg.eyebrow, M, Math.round(H * 0.135), ES, ACTIVE.accent, FONT_UI_BOLD, 240, "eyebrow");

  famText(doc, cfg.head1, M, HT, hs, ACTIVE.head, FONT_DISPLAY, -20, "head-1");
  famText(doc, cfg.head2, M, HT + HL, hs, ACTIVE.accent, FONT_DISPLAY, -20, "head-2");

  for (i = 0; i < body.length; i++) {
    famText(doc, body[i], M, bodyTop + Math.round(bs * 1.45 * i), bs,
            ACTIVE.body, FONT_UI, 0, "body-" + (i + 1));
  }
  famSignature(doc, W, H, M, FS, cfg);
}

/*
 * Entry point. Same cfg vocabulary as famRender plus:
 *   plate           absolute path to the campaign plate
 *   scrim           0-100, ground wash over the plate
 *   textureOpacity  0-100, xerox tooth
 *   bandOpacity     0-100, torn strip behind the type block
 *   seed            deterministic jitter seed for the torn edges
 */
function agRender(cfg) {
  ACTIVE = PALETTES[cfg.palette || "shutter"] || PALETTES["shutter"];
  var formats = cfg.formats || ["story-9x16", "feed-4x5", "square-1x1"];
  var written = [];
  var layoutFn = (!cfg.layout || cfg.layout === "standard") ? null : FAM_LAYOUTS[cfg.layout];
  if (cfg.layout && cfg.layout !== "standard" && !layoutFn) {
    return { error: "unknown layout: " + cfg.layout };
  }

  for (var i = 0; i < formats.length; i++) {
    var key = formats[i], F = FAM_FORMATS[key];
    if (!F) { written.push(key + " SKIPPED: unknown format"); continue; }

    var doc = famNewDoc(F[0], F[1], cfg.slug + "-" + key);
    var applied = [];

    var shift = 0;
    if (cfg.plateShift && typeof cfg.plateShift[key] === "number") shift = cfg.plateShift[key];

    if (cfg.plate) {
      if (agPlaceCover(doc, cfg.plate, "plate", shift)) {
        applied.push("plate");
        agScrim(doc, cfg.scrim === undefined ? 46 : cfg.scrim, "plate-scrim");
        FAM_PLATE_ACTIVE = true;
      } else {
        applied.push("plate MISSING: " + cfg.plate);
        FAM_PLATE_ACTIVE = false;
      }
    } else {
      FAM_PLATE_ACTIVE = false;
    }

    applied.push(agSurface(doc, cfg));

    if (layoutFn) {
      // Archetypes draw their own panels; the torn band would fight them, so
      // they get the plate and the tooth but not the strip.
      layoutFn(doc, F, cfg);
      applied.push("layout:" + cfg.layout);
    } else {
      agTypeStandard(doc, F, cfg);
      applied.push("layout:standard");
    }

    var path = cfg.outDir + cfg.slug + "-" + key + ".png";
    famExportPNG(doc, path);
    written.push(key + " " + F[0] + "x" + F[1] + " [" + applied.join(",") + "] -> " + path);
    doc.close(SaveOptions.DONOTSAVECHANGES);
  }
  return { slug: cfg.slug, palette: cfg.palette || "shutter",
           layout: cfg.layout || "standard", written: written };
}

"grunge-frame.jsx loaded";
