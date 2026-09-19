import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { sha256 } from './creator-credit.mjs';
const files = execFileSync('git', ['ls-files'], { encoding: 'utf8' }).trim().split('\n');
const html = files.filter(file => file.endsWith('.html')).map(path => ({
  path, sha256: sha256(readFileSync(path)),
  classification: /^(\.agents|agent-skills|\.claude)\//.test(path) ? 'third-party-template-excluded-unless-exported' : path.startsWith('frontend/public/') ? 'public-source-new-credit-at-build' : /evidence|archive|benchmark|experiments|docs\//.test(path) ? 'historical-or-review-source-preserved-not-retrofit-target' : path.startsWith('marketing/hyperframes/') ? 'video-composition-not-webpage-preserved' : 'source-only-not-a-live-retrofit-target-unless-exported',
}));
const dir = 'docs/evidence/agency-creator-credit-2026-09-18';
mkdirSync(dir, { recursive: true });
const build = 'frontend/dist/creator-credit-build-v1.json';
const browser = '.local-creator-credit/browser-results.json';
const previous = existsSync(`${dir}/inventory.json`) ? JSON.parse(readFileSync(`${dir}/inventory.json`)) : {};
writeFileSync(`${dir}/inventory.json`, JSON.stringify({ base_sha: previous.base_sha || execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(), scope: 'agency-tracked-html-not-entire-ecosystem-or-runtime-database', customer_facing: { tracked_showcase_html: 48, react_seo_output_shells: 170, existing_unlisted_marketing_html: 4, friends_20260918_files: 0 }, html, build: existsSync(build) ? JSON.parse(readFileSync(build)) : previous.build || null, browser: existsSync(browser) ? JSON.parse(readFileSync(browser)) : previous.browser || null }, null, 2) + '\n');
console.log(`Inventoried ${html.length} tracked HTML sources`);
