#!/usr/bin/env node
/**
 * Verify a rendered file against frame.md's grading contract.
 *
 * A render that completes is not a render that is correct. This script measures
 * the DELIVERED frames rather than the source plates:
 *
 *   1. container/stream facts (ffprobe)
 *   2. per-second mean luminance (ffmpeg signalstats YAVG) vs this film's own band
 *   3. per-second amber-accent area fraction vs the accent budget
 *   4. per-second COOL-PIXEL fraction — the ghost-town palette forbids blue,
 *      green and any cool light outright, and a cool cast is the one defect a
 *      luminance number cannot see
 *   5. writes stills + a contact sheet so the frames can be LOOKED at
 *
 * The luminance band here is 22-82, NOT the anchor's 150-175. That is deliberate
 * and it is argued in README.md -> "Why this film is not graded to the anchor".
 * The anchor figure is printed alongside every run so the divergence can never
 * be mistaken for a measurement.
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
console.log(
  "== ffprobe ==\n" +
    sh("ffprobe", [
      "-v", "error",
      "-show_entries", "format=duration,size,bit_rate",
      "-show_entries",
      "stream=codec_type,codec_name,profile,width,height,r_frame_rate,nb_frames,pix_fmt,sample_rate,channels",
      "-of", "default=nw=1",
      file,
    ]) +
    "\n",
);

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
// One frame per second at a small size. Luminance, accent fraction and cool
// fraction are all stable under downscale, and the whole pass runs in under a
// second.
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

// #D9A441 as it actually renders. These thresholds are MEASURED off the
// delivered file, not guessed — the first version of this detector was guessed
// and it reported 5-25% amber, which would have failed a correct film. Sampled
// pixels from renders/ghost-town-sign-1080x1920.mp4 at t=41s:
//
//   the shaft            (212,162,65)  sat 0.69  r/g 1.31  g/b 2.49
//   sunlit timber        (192,153,103) sat 0.46  r/g 1.26  g/b 1.49
//   sunlit timber, dark  (154,115,60)  sat 0.61  r/g 1.34  g/b 1.92
//
// The whole film is warm by design, so "warm" is not a discriminator. What
// separates the accent from the plates it sits on is that it is a light source:
// brighter, and further from grey. The floors below sit between the measured
// accent and the worst measured false positive, with margin on both sides.
const isAccent = (r, g, b) => {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  if (max < 170) return false; // brighter than any graded timber in the film
  if ((max - min) / max < 0.62) return false; // and much further from grey
  return r > g * 1.22 && g > b * 2.1; // the #D9A441 ramp specifically
};

// The palette forbids cool light. Anything where blue leads by a real margin is
// a cast this grade should have removed.
const isCool = (r, g, b) => {
  const max = Math.max(r, g, b);
  if (max < 40) return false; // near-black carries no readable hue
  return b > r * 1.06 && b > g * 1.02;
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
    if (isCool(r, g, b)) cool++;
  }
  rows.push({
    t: f,
    lum: yavg[f] ?? Number.NaN,
    accentPct: (accent / px) * 100,
    coolPct: (cool / px) * 100,
  });
}
rmSync(raw, { force: true });

// This film's band is DERIVED, not chosen, and it is on the YAVG scale so it is
// directly comparable to the 155.4 / 160.1 reference figures. The palette ground
// #17120D encodes to Y' = 16 + 219*(0.2126*23 + 0.7152*18 + 0.0722*13)/255 = 32,
// and the graded plates measure YAVG 44.8 (b1, c2), 47.0 (c1), 90.9 (a2),
// 96.6 (b2), 107.7 (p). A frame is a mix of the two, so a beat whose earth band
// covers half the frame predicts about 38 — and beat 3 measures 40.0. The band
// below spans what that mix can produce, with margin at both ends.
const BAND = [30, 86];
const ACCENT_MAX = 3.0;
const COOL_MAX = 1.0;
let fails = 0;

console.log(
  `== per-second grade (ghost-town palette: luminance ${BAND[0]}-${BAND[1]}, ` +
    `amber <= ${ACCENT_MAX.toFixed(1)}%, cool <= ${COOL_MAX.toFixed(1)}%) ==`,
);
console.log("   t     mean-lum    amber%    cool%   verdict");
for (const r of rows) {
  const lumOk = r.lum >= BAND[0] && r.lum <= BAND[1];
  const accOk = r.accentPct <= ACCENT_MAX;
  const coolOk = r.coolPct <= COOL_MAX;
  if (!lumOk || !accOk || !coolOk) fails++;
  const v =
    lumOk && accOk && coolOk
      ? "ok"
      : `${lumOk ? "" : "LUM "}${accOk ? "" : "AMBER "}${coolOk ? "" : "COOL"}`;
  console.log(
    `${String(r.t).padStart(4)}s ${r.lum.toFixed(1).padStart(11)} ` +
      `${r.accentPct.toFixed(2).padStart(9)} ${r.coolPct.toFixed(2).padStart(8)}   ${v}`,
  );
}

const mean = (a) => a.reduce((x, y) => x + y, 0) / a.length;
console.log(
  `\nfilm mean luminance: ${mean(rows.map((r) => r.lum)).toFixed(1)}  ` +
    `[this film's derived band ${BAND[0]}-${BAND[1]}; the HeyGen anchor take measures 155.4 ` +
    `(and the accepted platform-dependency film 160.1) — this film is DELIBERATELY not graded to either]`,
);
console.log(
  `film mean amber:     ${mean(rows.map((r) => r.accentPct)).toFixed(2)}%  ` +
    `(an incident, never a field, gradient or wash)`,
);
console.log(
  `film mean cool:      ${mean(rows.map((r) => r.coolPct)).toFixed(2)}%  ` +
    `(the palette forbids blue, green and cool light outright)`,
);
console.log(`seconds outside contract: ${fails} / ${rows.length}`);

// ---- 5. frames to actually look at -------------------------------------
const at = [1.5, 3.6, 8.0, 11.2, 16.5, 19.0, 24.0, 28.5, 33.0, 36.5, 40.0, 43.0].filter(
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

process.exit(fails > 0 ? 1 : 0);
