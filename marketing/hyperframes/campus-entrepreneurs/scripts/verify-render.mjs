#!/usr/bin/env node
/**
 * Verify a rendered file against frame.md's grading contract.
 *
 * A render that completes is not a render that is correct. This script measures
 * the DELIVERED frames rather than the source plates:
 *
 *   1. container/stream facts (ffprobe) — including that the audio bed is there
 *   2. per-second mean luminance (ffmpeg signalstats YAVG) vs the 150-175 band
 *   3. per-second olive-accent area fraction vs the 1-2% budget
 *   4. per-second SATURATED-COOL fraction, which guards a real defect this film
 *      already had: the campaign video's phone and laptop screens rendered as a
 *      cyan beacon in an otherwise warm-neutral frame. It was removed with a
 *      canonical HSL secondary treatment; this number stops it coming back.
 *   5. writes stills + a contact sheet so the frames can be LOOKED at
 *
 * Usage: node scripts/verify-render.mjs renders/<file>.mp4 [outDir]
 * Cost: $0. Local ffmpeg only.
 */
import { execFileSync } from "node:child_process";
import { mkdirSync, readFileSync, rmSync } from "node:fs";
import { join } from "node:path";

const file = process.argv[2];
if (!file) {
  console.error("usage: node scripts/verify-render.mjs <file.mp4> [outDir]");
  process.exit(2);
}
const outDir = process.argv[3] ?? "verify";
rmSync(outDir, { recursive: true, force: true });
mkdirSync(outDir, { recursive: true });

const sh = (bin, args) => execFileSync(bin, args, { encoding: "utf8" }).trim();

// ---- 1. container facts ------------------------------------------------
const probe = sh("ffprobe", [
  "-v", "error",
  "-show_entries", "format=duration,size,bit_rate",
  "-show_entries",
  "stream=codec_type,codec_name,profile,width,height,r_frame_rate,nb_frames,pix_fmt,sample_rate,channels",
  "-of", "default=nw=1",
  file,
]);
console.log("== ffprobe ==\n" + probe + "\n");

const hasAudio = /codec_type=audio/.test(probe);
if (!hasAudio) {
  console.log(
    "!! NO AUDIO STREAM. This film is supposed to carry a music bed. An <audio>\n" +
      "   element without an id is silently ignored by the mixer and the render\n" +
      "   comes out silent with no warning — check index.html #bed first.\n",
  );
}

const duration = Number(
  sh("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", file]),
);

// ---- 2. per-second luminance, on ffmpeg's own YAVG scale -----------------
// signalstats YAVG reads the decoded Y' plane, which is NOT the same number as
// a Rec.709 luminance computed over full-range RGB — for these files the two
// differ by roughly six levels. Every reference figure this project is judged
// against is a YAVG: the HeyGen anchor take measures 155.4, and the accepted
// platform-dependency film measures 160.1. So the band is checked on YAVG, and
// the RGB pass below is used only for the colour-area measurements, where the
// scale does not matter.
const yavg = execFileSync(
  "ffmpeg",
  ["-v", "info", "-i", file, "-vf",
   "fps=1,signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-",
   "-f", "null", "/dev/null"],
  { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] },
)
  .split("\n")
  .map((l) => l.match(/lavfi\.signalstats\.YAVG=([\d.]+)/))
  .filter(Boolean)
  .map((m) => Number(m[1]));

// ---- 3/4. per-second colour-area measurement ----------------------------
const W = 108;
const H = 192;
const raw = join(outDir, "frames.rgb");
execFileSync("ffmpeg", [
  "-y", "-v", "error", "-i", file,
  "-vf", `fps=1,scale=${W}:${H}`,
  "-pix_fmt", "rgb24", "-f", "rawvideo", raw,
]);

const buf = readFileSync(raw);
const px = W * H;
const frameBytes = px * 3;
const frames = Math.floor(buf.length / frameBytes);

// #7FB449 as it actually renders, MEASURED off the delivered file rather than
// guessed. Sampled from renders/campus-somebody-elses-app-1080x1920.mp4:
//
//   the accent rule (t=3.2, t=21.6)  (124,179,70)  sat 0.61  g/r 1.44  g/b 2.56
//   the quad card's sunlit lawn      (117,146,44)  sat 0.70  g/r 1.25  g/b 3.32
//   the same lawn, in shade          ( 86,124,31)  sat 0.75  g/r 1.44  g/b 4.00
//
// Hue and saturation CANNOT separate these: a photograph of a lawn is a real,
// saturated green. Only luminance does — the composition's accent is a flat
// solid at max 179 and the brightest lawn pixel measures 146. The floor sits
// between them, which means this number reports the accent the DESIGN adds and
// deliberately excludes foliage inside a photograph. That is the right question
// to gate: the reference-tokens rule is about not flooding a frame with the
// brand colour, not about what a photograph contains. The margin is only 33
// levels, so treat a brighter green plate as a reason to re-measure this
// detector rather than to trust it.
const isAccent = (r, g, b) => {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  if (max < 160) return false;
  if ((max - min) / max < 0.5) return false;
  return g > r * 1.25 && g > b * 2.0;
};

// Saturated cool pixels. The anchor is warm-neutral daylight; a saturated cyan
// or blue in frame is the defect this film already had once.
const isCoolSaturated = (r, g, b) => {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  if (max < 70) return false;
  if ((max - min) / max < 0.34) return false;
  return b > r * 1.2 && b >= g;
};

const rows = [];
for (let f = 0; f < frames; f++) {
  const off = f * frameBytes;
  let accent = 0;
  let cool = 0;
  for (let i = 0; i < px; i++) {
    const r = buf[off + i * 3];
    const g = buf[off + i * 3 + 1];
    const b = buf[off + i * 3 + 2];
    if (isAccent(r, g, b)) accent++;
    if (isCoolSaturated(r, g, b)) cool++;
  }
  rows.push({
    t: f,
    lum: yavg[f] ?? Number.NaN,
    accentPct: (accent / px) * 100,
    coolPct: (cool / px) * 100,
  });
}
rmSync(raw, { force: true });

const BAND = [150, 175]; // the anchor's measured band — this film IS matched to it
const ACCENT_MAX = 2.0;
const COOL_MAX = 0.5;
let fails = 0;

console.log(
  `== per-second grade (anchor band ${BAND[0]}-${BAND[1]}, ` +
    `olive <= ${ACCENT_MAX.toFixed(1)}%, saturated cool <= ${COOL_MAX.toFixed(1)}%) ==`,
);
console.log("   t     mean-lum    olive%    cool%   verdict");
for (const r of rows) {
  const lumOk = r.lum >= BAND[0] && r.lum <= BAND[1];
  const accOk = r.accentPct <= ACCENT_MAX;
  const coolOk = r.coolPct <= COOL_MAX;
  if (!lumOk || !accOk || !coolOk) fails++;
  const v =
    lumOk && accOk && coolOk
      ? "ok"
      : `${lumOk ? "" : "LUM "}${accOk ? "" : "OLIVE "}${coolOk ? "" : "COOL"}`;
  console.log(
    `${String(r.t).padStart(4)}s ${r.lum.toFixed(1).padStart(11)} ` +
      `${r.accentPct.toFixed(2).padStart(9)} ${r.coolPct.toFixed(2).padStart(8)}   ${v}`,
  );
}

const mean = (a) => a.reduce((x, y) => x + y, 0) / a.length;
console.log(
  `\nfilm mean luminance: ${mean(rows.map((r) => r.lum)).toFixed(1)}  ` +
    `(HeyGen anchor take 155.4; the accepted platform-dependency film 160.1)`,
);
console.log(
  `film mean olive:     ${mean(rows.map((r) => r.accentPct)).toFixed(2)}%  ` +
    `(budget 1-2% per frame, never a field, gradient or wash)`,
);
console.log(
  `film mean cool:      ${mean(rows.map((r) => r.coolPct)).toFixed(2)}%  ` +
    `(warm-neutral daylight; no competing saturated colour)`,
);
console.log(`audio stream present: ${hasAudio ? "yes" : "NO"}`);
console.log(`seconds outside contract: ${fails} / ${rows.length}`);

// ---- 5. frames to actually look at -------------------------------------
const at = [1.5, 3.2, 5.9, 8.2, 10.5, 14.0, 16.5, 20.0, 22.5, 24.0, 26.2].filter(
  (t) => t < duration,
);
for (const t of at) {
  execFileSync("ffmpeg", [
    "-y", "-v", "error", "-ss", String(t), "-i", file,
    "-frames:v", "1", join(outDir, `t-${t}.png`),
  ]);
}
const step = Math.max(1, duration / 12);
execFileSync("ffmpeg", [
  "-y", "-v", "error", "-i", file,
  "-vf", `fps=1/${step.toFixed(3)},scale=200:-1,tile=4x3`,
  "-frames:v", "1", "-q:v", "3", join(outDir, "sheet.jpg"),
]);
console.log(`\nwrote ${at.length} stills + sheet.jpg to ${outDir}/ — look at them.`);

process.exit(fails > 0 || !hasAudio ? 1 : 0);
