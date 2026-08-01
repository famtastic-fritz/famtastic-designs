#!/usr/bin/env node
/**
 * Renders every post in posts.json to PNG at exact platform pixel sizes.
 *
 * Uses the Chromium already present in this environment via its headless
 * screenshot mode — no npm dependency, nothing to install, and the output is
 * byte-identical on re-runs because the background constellation is seeded.
 *
 *   node src/render.mjs                 # everything
 *   node src/render.mjs --id=price-led  # one post
 *   node src/render.mjs --size=square   # one size
 */

import { execFile } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

import { renderPost, SIZES } from './templates.mjs';

const run = promisify(execFile);
const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'out');
const WORK = join(ROOT, '.work');

/** Common install locations; the bundled Playwright build is checked first. */
const CHROMIUM_CANDIDATES = [
  process.env.CHROMIUM_PATH,
  '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  '/usr/bin/chromium',
  '/usr/bin/chromium-browser',
  '/usr/bin/google-chrome',
].filter(Boolean);

async function findChromium() {
  const { access } = await import('node:fs/promises');
  for (const candidate of CHROMIUM_CANDIDATES) {
    try {
      await access(candidate);
      return candidate;
    } catch {
      /* try the next one */
    }
  }
  throw new Error(
    `No Chromium found. Set CHROMIUM_PATH, or install Chrome/Chromium.\nLooked in:\n  ${CHROMIUM_CANDIDATES.join('\n  ')}`,
  );
}

async function loadFonts() {
  const read = async (name) => (await readFile(join(ROOT, 'fonts', name))).toString('base64');
  return {
    displayBold: await read('Outfit-Bold.ttf'),
    bodyRegular: await read('InstrumentSans-Regular.ttf'),
    bodyBold: await read('InstrumentSans-Bold.ttf'),
  };
}

function flag(name) {
  const hit = process.argv.find((arg) => arg.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : null;
}

async function shoot(chromium, htmlPath, pngPath, { w, h }) {
  await run(
    chromium,
    [
      '--headless',
      '--no-sandbox',
      '--disable-gpu',
      '--hide-scrollbars',
      '--force-device-scale-factor=1',
      '--default-background-color=00000000',
      `--window-size=${w},${h}`,
      `--screenshot=${pngPath}`,
      // Fonts are inlined as data URIs, so a short budget is enough to lay out
      // and paint; no network fetch is ever attempted.
      '--virtual-time-budget=3000',
      `file://${htmlPath}`,
    ],
    { maxBuffer: 1024 * 1024 * 32 },
  );
}

async function main() {
  const onlyId = flag('id');
  const onlySize = flag('size');

  const { posts } = JSON.parse(await readFile(join(ROOT, 'posts.json'), 'utf8'));
  const queue = onlyId ? posts.filter((p) => p.id === onlyId) : posts;
  if (queue.length === 0) throw new Error(`No post with id "${onlyId}".`);

  const [chromium, fonts] = await Promise.all([findChromium(), loadFonts()]);
  await mkdir(OUT, { recursive: true });
  await mkdir(WORK, { recursive: true });

  let made = 0;
  for (const post of queue) {
    const sizes = (post.sizes ?? ['square']).filter((s) => !onlySize || s === onlySize);
    for (const sizeName of sizes) {
      const size = SIZES[sizeName];
      if (!size) throw new Error(`Post "${post.id}" requests unknown size "${sizeName}".`);

      const htmlPath = join(WORK, `${post.id}-${sizeName}.html`);
      const pngPath = join(OUT, `${post.id}-${sizeName}.png`);
      await writeFile(htmlPath, renderPost(post, sizeName, fonts));
      await shoot(chromium, htmlPath, pngPath, size);

      console.log(`  out/${post.id}-${sizeName}.png  ${size.w}×${size.h}  — ${size.label}`);
      made += 1;
    }
  }

  await rm(WORK, { recursive: true, force: true });
  console.log(`\n${made} graphic(s) in tools/social-graphics/out/`);
  console.log('Captions for each post live alongside the design in posts.json.');
}

main().catch((err) => {
  console.error(`\nError: ${err.message}`);
  process.exit(1);
});
