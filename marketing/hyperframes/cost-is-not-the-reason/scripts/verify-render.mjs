#!/usr/bin/env node
/**
 * Verify a rendered file against frame.md's grading contract.
 *
 * A render that completes is not a render that is correct, and a render that
 * measures correctly is not a render that LOOKS correct. This script does the
 * measurable half and then writes frames so a person can do the other half:
 *
 *   1. container/stream facts (ffprobe)
 *   2. per-second luma vs the anchor's measured 150-175 band, using
 *      ffmpeg signalstats YAVG - the SAME measure the campaign anchor and the
 *      accepted platform-dependency film were graded by (anchor 155.4,
 *      accepted film 160.1, rejected Remotion cut 212.1)
 *   3. per-second olive-accent area fraction vs the 1-2% budget
 *   4. a still per sampled second plus a contact sheet, to be LOOKED at
 *
 * WHY signalstats AND NOT Rec.709-from-RGB. Both live in this repo and they do
 * not agree. signalstats reads the decoded Y plane; converting the decoded RGB
 * back to luma lands 6-8 points higher on the same file. The first version of
 * this script used the RGB conversion against the signalstats band and failed
 * 16 of 28 seconds on a film that measures 165.6 by the contract's own
 * command - a gate failing good work, which is worse than no gate. The RGB
 * mean is still printed, clearly labelled, because the accent detector needs
 * RGB anyway; only signalstats is gated on.
 *
 * Two defects in this repo were caught only by eye and two automated detectors
 * written to catch one of them both failed, so step 4 is not optional.
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
  "stream=codec_type,codec_name,width,height,r_frame_rate,nb_frames,pix_fmt,sample_rate,channels",
  "-of", "default=nw=1",
  file,
]);
console.log("== ffprobe ==\n" + probe + "\n");

const duration = Number(
  sh("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", file]),
);

// ---- 2/3. per-second frame measurement ---------------------------------
// One frame per second at a small size; both luminance and accent fraction are
// stable under downscale and this keeps the whole pass under a second.
const W = 108;
const H = 192;
const raw = join(outDir, "frames.rgb");
execFileSync("ffmpeg", [
  "-y", "-v", "error", "-i", file,
  "-vf", `fps=1,scale=${W}:${H}`,
  "-pix_fmt", "rgb24", "-f", "rawvideo", raw,
]);

// Per-second signalstats YAVG. This is the gated measure: it is exactly what
// `ffmpeg -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG"` reports,
// which is how reference-tokens.json's companions were measured.
const sigOut = execFileSync(
  "ffmpeg",
  ["-v", "error", "-i", file, "-vf", "fps=1,signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-", "-f", "null", "-"],
  { encoding: "utf8", stdio: ["ignore", "pipe", "pipe"], maxBuffer: 1e8 },
);
const yavg = [...sigOut.matchAll(/YAVG=([0-9.]+)/g)].map((m) => Number(m[1]));

const buf = readFileSync(raw);
const px = W * H;
const frameBytes = px * 3;
const frames = Math.floor(buf.length / frameBytes);

// The accent as it renders under the anchor's lighting (#7FB449), with a
// tolerance wide enough to catch the same hue at other exposures but narrow
// enough to exclude warm timber and foliage.
const isAccent = (r, g, b) => {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  if (max < 60 || max > 235) return false;
  if (g <= r + 14 || g <= b + 24) return false; // green must actually dominate
  return (max - min) / max > 0.2; // and be saturated, not a grey-green
};

const rows = [];
for (let f = 0; f < frames; f++) {
  const off = f * frameBytes;
  let lum = 0;
  let accent = 0;
  for (let i = 0; i < px; i++) {
    const r = buf[off + i * 3];
    const g = buf[off + i * 3 + 1];
    const b = buf[off + i * 3 + 2];
    lum += 0.2126 * r + 0.7152 * g + 0.0722 * b;
    if (isAccent(r, g, b)) accent++;
  }
  rows.push({ t: f, yavg: yavg[f], rgbLum: lum / px, accentPct: (accent / px) * 100 });
}
rmSync(raw, { force: true });

const BAND = [150, 175];
const ACCENT_MAX = 2.0;
let fails = 0;

console.log("== per-second grade (gated: signalstats YAVG 150-175, accent <= 2.0%) ==");
console.log("   t        YAVG   (rgb-luma)   accent%   verdict");
for (const r of rows) {
  if (r.yavg === undefined) continue;
  const lumOk = r.yavg >= BAND[0] && r.yavg <= BAND[1];
  const accOk = r.accentPct <= ACCENT_MAX;
  if (!lumOk || !accOk) fails++;
  const v = lumOk && accOk ? "ok" : `${lumOk ? "" : "LUM "}${accOk ? "" : "ACCENT"}`;
  console.log(
    `${String(r.t).padStart(4)}s ${r.yavg.toFixed(1).padStart(11)} ${("(" + r.rgbLum.toFixed(1) + ")").padStart(12)} ${r.accentPct
      .toFixed(2)
      .padStart(9)}   ${v}`,
  );
}

const mean = (a) => a.reduce((x, y) => x + y, 0) / a.length;
const measured = rows.filter((r) => r.yavg !== undefined);
console.log(
  `\nfilm mean YAVG:      ${mean(measured.map((r) => r.yavg)).toFixed(1)} ` +
    `(anchor take 155.4, accepted platform-dependency film 160.1)`,
);
console.log(
  `film mean rgb-luma:  ${mean(measured.map((r) => r.rgbLum)).toFixed(1)} ` +
    `(reference only — NOT the gated measure; runs 6-8 points above YAVG)`,
);
console.log(
  `film mean accent:    ${mean(rows.map((r) => r.accentPct)).toFixed(2)}% ` +
    `(budget 1-2% per frame, never a green field)`,
);
console.log(`seconds outside contract: ${fails} / ${measured.length}`);

// ---- 4. frames to actually look at -------------------------------------
const at = [1.4, 2.8, 5.2, 7.4, 9.6, 12.4, 15.0, 17.0, 19.4, 22.0, 24.4, 26.6].filter(
  (t) => t < duration,
);
for (const t of at) {
  execFileSync("ffmpeg", [
    "-y", "-v", "error", "-ss", String(t), "-i", file,
    "-frames:v", "1", join(outDir, `t-${t}.png`),
  ]);
}
execFileSync("ffmpeg", [
  "-y", "-v", "error", "-i", file,
  "-vf", "fps=1/2.4,scale=220:-1,tile=4x3",
  "-frames:v", "1", join(outDir, "sheet.png"),
]);
console.log(`\nwrote ${at.length} stills + sheet.png to ${outDir}/ — look at them.`);

process.exit(fails > 0 ? 1 : 0);
