#!/usr/bin/env node
/**
 * Verify a rendered file against frame.md's grading contract.
 *
 * A render that completes is not a render that is correct. This script
 * measures the delivered frames rather than the source plates:
 *
 *   1. container/stream facts (ffprobe)
 *   2. per-frame mean luminance vs the anchor's measured 150-175 band
 *   3. per-frame olive-accent area fraction vs the 1-2% budget
 *   4. writes a PNG per sampled second so the frames can be LOOKED at
 *
 * Usage: node scripts/verify-render.mjs renders/borrowed-land-1080x1920.mp4 [outDir]
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
  "-show_entries", "stream=codec_type,codec_name,width,height,r_frame_rate,nb_frames,pix_fmt,sample_rate,channels",
  "-of", "default=nw=1",
  file,
]);
console.log("== ffprobe ==\n" + probe + "\n");

const duration = Number(
  sh("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", file]),
);

// ---- 2/3. per-second frame measurement ---------------------------------
// Sample one frame per second at a small size; luminance and accent fraction
// are both stable under downscale and this keeps the whole pass under a second.
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

// The accent as it renders under the anchor's lighting (#7FB449) with a
// tolerance wide enough to catch the same hue at other exposures, but narrow
// enough to exclude the plates' warm timber.
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
  rows.push({ t: f, lum: lum / px, accentPct: (accent / px) * 100 });
}
rmSync(raw, { force: true });

const BAND = [150, 175];
const ACCENT_MAX = 2.0;
let fails = 0;

console.log("== per-second grade (target: luminance 150-175, accent <= 2.0%) ==");
console.log("   t     mean-lum   accent%   verdict");
for (const r of rows) {
  const lumOk = r.lum >= BAND[0] && r.lum <= BAND[1];
  const accOk = r.accentPct <= ACCENT_MAX;
  if (!lumOk || !accOk) fails++;
  const v = lumOk && accOk ? "ok" : `${lumOk ? "" : "LUM "}${accOk ? "" : "ACCENT"}`;
  console.log(
    `${String(r.t).padStart(4)}s ${r.lum.toFixed(1).padStart(11)} ${r.accentPct.toFixed(2).padStart(9)}   ${v}`,
  );
}

const mean = (a) => a.reduce((x, y) => x + y, 0) / a.length;
console.log(
  `\nfilm mean luminance: ${mean(rows.map((r) => r.lum)).toFixed(1)} ` +
    `(anchor take measured 161.9)`,
);
console.log(
  `film mean accent:    ${mean(rows.map((r) => r.accentPct)).toFixed(2)}% ` +
    `(budget 1-2% per frame, never a green field)`,
);
console.log(`seconds outside contract: ${fails} / ${rows.length}`);

// ---- 4. frames to actually look at -------------------------------------
const at = [1.5, 3.0, 5.0, 8.0, 11.5, 14.0, 16.5, 18.5, 22.0, 24.0, 27.0]
  .filter((t) => t < duration);
for (const t of at) {
  execFileSync("ffmpeg", [
    "-y", "-v", "error", "-ss", String(t), "-i", file,
    "-frames:v", "1", join(outDir, `t-${t}.png`),
  ]);
}
execFileSync("ffmpeg", [
  "-y", "-v", "error", "-i", file,
  "-vf", `fps=1/2.5,scale=200:-1,tile=4x3`,
  "-frames:v", "1", join(outDir, "sheet.png"),
]);
console.log(`\nwrote ${at.length} stills + sheet.png to ${outDir}/ — look at them.`);

process.exit(fails > 0 ? 1 : 0);
