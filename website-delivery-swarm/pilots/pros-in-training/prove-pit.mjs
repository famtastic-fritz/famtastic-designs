#!/usr/bin/env node
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawn } from 'node:child_process';

const output = resolve(process.argv[2] || '');
if (!output) throw new Error('Usage: node prove-pit.mjs <artifact-directory>');
const manifest = JSON.parse(readFileSync(join(output, 'manifest.json'), 'utf8'));
if (manifest.proof_count !== 3 || manifest.directions.length !== 3) throw new Error('P.I.T. contract requires exactly three directions.');
const shots = join(output, 'screenshots'); mkdirSync(shots, { recursive: true });
const port = 9600 + (process.pid % 300);
const server = spawn('python3', ['-m', 'http.server', String(port), '--bind', '127.0.0.1', '--directory', output], { stdio: 'ignore' });
await new Promise((done) => setTimeout(done, 600));
const browser = await chromium.launch({ headless: true });
const results = [];
try {
  for (const profile of [{ name: 'desktop', width: 1440, height: 1000 }, { name: 'mobile', width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    for (const direction of manifest.directions) {
      const page = await context.newPage(); const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      const response = await page.goto(`http://127.0.0.1:${port}/${direction.entry}`, { waitUntil: 'networkidle' });
      const inspect = await page.evaluate(() => ({
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        h1s: document.querySelectorAll('h1').length,
        brokenImages: [...document.images].filter((image) => !image.complete || image.naturalWidth === 0).length,
        missingAlt: [...document.images].filter((image) => !image.hasAttribute('alt')).length,
        activeForms: document.querySelectorAll('form button[type="submit"], form input[type="submit"]').length,
      }));
      const screenshot = `${direction.id}-${profile.name}.png`;
      await page.screenshot({ path: join(shots, screenshot), fullPage: true });
      const pass = response?.ok() && inspect.scrollWidth <= inspect.width + 1 && inspect.h1s === 1 && inspect.brokenImages === 0 && inspect.missingAlt === 0 && inspect.activeForms === 0 && errors.length === 0;
      results.push({ direction_id: direction.id, profile: profile.name, screenshot: `screenshots/${screenshot}`, screenshot_sha256: createHash('sha256').update(readFileSync(join(shots, screenshot))).digest('hex'), pass, inspect, page_errors: errors });
      await page.close();
    }
    await context.close();
  }
} finally { await browser.close(); server.kill('SIGTERM'); }
const qa = { schema: 'famtastic.pit.browser-qa.v1', generated_at: new Date().toISOString(), proof_count: 3, results, passed: results.every((result) => result.pass), independent_visual_review: 'not_run' };
writeFileSync(join(output, 'browser-qa.json'), `${JSON.stringify(qa, null, 2)}\n`);
if (!qa.passed) process.exit(1);
console.log(`PASS: P.I.T. browser QA (${results.length} renders)`);
