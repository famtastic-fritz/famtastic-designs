#!/usr/bin/env node
import { chromium } from '../frontend/node_modules/playwright/index.mjs';
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const repo = resolve(root, '..');
const output = resolve(process.argv[2] || join(repo, 'artifacts', 'website-delivery-swarm', 'latest'));
mkdirSync(output, { recursive: true });
const generated = spawnSync('python3', [join(root, 'engine.py'), '--output', output], { encoding: 'utf8' });
process.stdout.write(generated.stdout); process.stderr.write(generated.stderr);
if (generated.status !== 0) process.exit(generated.status || 1);
const port = 8777;
const server = spawn('python3', ['-m', 'http.server', String(port), '--bind', '127.0.0.1', '--directory', output], { stdio: 'ignore' });
await new Promise(resolveWait => setTimeout(resolveWait, 500));
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1050 }, deviceScaleFactor: 1 });
const evidence = JSON.parse(readFileSync(join(output, 'evidence.json'), 'utf8'));
evidence.source_sha = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: repo, encoding: 'utf8' }).trim();
const browserAssertions = {};
try {
  for (const run of evidence.runs) {
    const id = run.scenario.id;
    const response = await page.goto(`http://127.0.0.1:${port}/${id}.html`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: join(output, `${id}.png`), fullPage: true });
    browserAssertions[id] = response?.ok() === true && await page.locator('text=All assertions passed').count() === 1 && await page.locator('.directions article').count() === 3;
  }
} finally {
  await browser.close(); server.kill('SIGTERM');
}
evidence.browser = { engine:'Playwright Chromium', viewport:'1440x1050', assertions:browserAssertions,
  screenshots:Object.keys(browserAssertions).map(id => { const file=`${id}.png`; return {file,sha256:createHash('sha256').update(readFileSync(join(output,file))).digest('hex')}; }) };
evidence.assertions.browser_proof = Object.values(browserAssertions).every(Boolean);
writeFileSync(join(output, 'evidence.json'), JSON.stringify(evidence, null, 2) + '\n');
if (!evidence.assertions.browser_proof) process.exit(1);
console.log('PASS: Playwright screenshots and visible proof assertions');
console.log(`Evidence: ${join(output, 'evidence.json')}`);
