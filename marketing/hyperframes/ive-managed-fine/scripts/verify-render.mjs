#!/usr/bin/env node
/**
 * Verify a delivered film against frame.md's grading contract.
 *
 * A render that completes is not a render that is correct, and a green exit is
 * not evidence. This measures the DELIVERED frames — not the source plates —
 * and then writes stills so a person can look at them:
 *
 *   1. container / stream facts, including that an audio stream exists and is
 *      not silence (a muxed file with a dead track passes every structural check)
 *   2. per-second mean luminance against the anchor's measured 150-175 band
 *   3. per-second olive-accent area fraction against the 1-2 % budget
 *   4. a contact sheet and per-beat stills
 *
 * Usage: node scripts/verify-render.mjs <film-dir> [outDir]
 * Cost: $0. Local ffmpeg only.
 */
import { execFileSync } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, rmSync } from "node:fs";
import { basename, join, resolve } from "node:path";

const dir = process.argv[2];
if (!dir) {
  console.error("usage: node scripts/verify-render.mjs <film-dir> [outDir]");
  process.exit(2);
}
const name = basename(resolve(dir));
const file = join(dir, "renders", `${name}-1080x1920.mp4`);
if (!existsSync(file)) {
  console.error(`missing render: ${file}`);
  process.exit(2);
}
const outDir = process.argv[3] ?? join(dir, "verify");
rmSync(outDir, { recursive: true, force: true });
mkdirSync(outDir, { recursive: true });

const sh = (bin, args) => execFileSync(bin, args, { encoding: "utf8" }).trim();

// ---- 1. container facts -------------------------------------------------
console.log(`\n================ ${name} ================`);
console.log(
  sh("ffprobe", [
    "-v", "error",
    "-show_entries", "format=duration,size",
    "-show_entries", "stream=codec_type,codec_name,width,height,r_frame_rate,nb_frames,pix_fmt,sample_rate,channels",
    "-of", "default=nw=1",
    file,
  ]),
);
const duration = Number(
  sh("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", file]),
);

// Audio must exist AND carry signal. `volumedetect` on a dead track reports
// -91 dB; anything above -60 is a real read.
// volumedetect reports on STDERR, not stdout. Reading stdout returns an empty
// string, the regex misses, and the script reports a perfectly good soundtrack
// as SILENT — which is exactly what the first run of this script did.
const vol = execFileSync(
  "sh",
  ["-c", `ffmpeg -hide_banner -nostats -i ${JSON.stringify(file)} -af volumedetect -f null /dev/null 2>&1`],
  { encoding: "utf8" },
);
const meanDb = Number((/mean_volume: (-?[\d.]+) dB/.exec(vol) ?? [])[1]);
const maxDb = Number((/max_volume: (-?[\d.]+) dB/.exec(vol) ?? [])[1]);
const audioOk = Number.isFinite(meanDb) && meanDb > -60;
console.log(`audio: mean ${meanDb} dB, peak ${maxDb} dB — ${audioOk ? "carries signal" : "SILENT"}`);

// ---- 2/3. per-second measurement ---------------------------------------
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

// The accent as it renders (#7FB449): green must actually dominate and be
// saturated, so the plates' warm timber and the paper ground are excluded.
const isAccent = (r, g, b) => {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  if (max < 60 || max > 235) return false;
  if (g <= r + 14 || g <= b + 24) return false;
  return (max - min) / max > 0.2;
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
let lumFails = 0;
let accFails = 0;
console.log("\n  t   mean-lum  accent%   verdict");
for (const r of rows) {
  const lumOk = r.lum >= BAND[0] && r.lum <= BAND[1];
  const accOk = r.accentPct <= ACCENT_MAX;
  if (!lumOk) lumFails++;
  if (!accOk) accFails++;
  console.log(
    `${String(r.t).padStart(3)}s ${r.lum.toFixed(1).padStart(10)} ${r.accentPct.toFixed(2).padStart(8)}   ` +
      `${lumOk && accOk ? "ok" : `${lumOk ? "" : "LUM "}${accOk ? "" : "ACCENT"}`}`,
  );
}
const mean = (a) => a.reduce((x, y) => x + y, 0) / a.length;
const filmLum = mean(rows.map((r) => r.lum));
const filmAcc = mean(rows.map((r) => r.accentPct));
console.log(
  `\nFILM MEAN LUMINANCE: ${filmLum.toFixed(1)}  (anchor take measured 161.9; contract 150-175)`,
);
console.log(
  `film mean accent:    ${filmAcc.toFixed(2)}%  peak ${Math.max(...rows.map((r) => r.accentPct)).toFixed(2)}%  (budget 1-2%)`,
);
console.log(`seconds outside luminance band: ${lumFails}/${rows.length}; over accent budget: ${accFails}/${rows.length}`);

// ---- 4. frames to actually look at --------------------------------------
execFileSync("ffmpeg", [
  "-y", "-v", "error", "-i", file,
  "-vf", "fps=1/2,scale=200:-1,tile=6x2",
  "-frames:v", "1", join(outDir, "sheet.jpg"),
]);
for (const t of [1.6, 3.4, 6.5, 9.5, 13.0, 17.0, 20.0].filter((t) => t < duration)) {
  execFileSync("ffmpeg", [
    "-y", "-v", "error", "-ss", String(t), "-i", file,
    "-frames:v", "1", join(outDir, `t-${t}.jpg`),
  ]);
}
console.log(`wrote stills + sheet.jpg to ${outDir}/ — look at them.`);

// The film mean is the contract. Individual seconds may sit outside the band
// (a photograph-heavy beat is darker by design); a whole film outside it is a
// grading failure.
const filmOk = filmLum >= BAND[0] && filmLum <= BAND[1];
process.exit(filmOk && audioOk && accFails === 0 ? 0 : 1);
