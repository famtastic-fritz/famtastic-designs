#!/usr/bin/env node
/**
 * Static customer/staff navigation and action inventory validator.
 *
 * It deliberately operates on source only: no server, browser, credentials,
 * mutation, or provider call is involved. It catches the high-confidence
 * affordance failures that source can prove and leaves dynamic/runtime targets
 * to browser and integration validation.
 */
import {existsSync, readdirSync, readFileSync} from 'node:fs';
import {resolve, relative, join} from 'node:path';

const rootArg = process.argv.indexOf('--root');
const root = resolve(rootArg === -1 ? import.meta.dirname + '/..' : process.argv[rootArg + 1]);
const errors = [];
const notes = [];

function files(dir, predicate = () => true) {
  if (!existsSync(dir)) return [];
  return readdirSync(dir, {withFileTypes: true}).flatMap((entry) => {
    const path = join(dir, entry.name);
    return entry.isDirectory() ? files(path, predicate) : predicate(path) ? [path] : [];
  });
}
function source(path) { return readFileSync(path, 'utf8'); }
function fail(path, message) { errors.push(`${relative(root, path)}: ${message}`); }
function staticMatches(text, regex) { return [...text.matchAll(regex)]; }

const app = resolve(root, 'frontend/src/App.jsx');
const reactRoutes = new Set(['/']);
if (existsSync(app)) {
  for (const match of staticMatches(source(app), /<Route\s+[^>]*\bpath=(['"])([^'"]+)\1/g)) reactRoutes.add(match[2]);
}
else notes.push('React route map is unavailable; React internal href validation skipped.');

function matchesReactRoute(target) {
  const path = target.split(/[?#]/, 1)[0];
  return [...reactRoutes].some((pattern) => {
    if (pattern === '*') return true;
    const expression = '^' + pattern
      .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
      .replace(/:[A-Za-z][A-Za-z0-9_]*/g, '[^/]+') + '$';
    return new RegExp(expression).test(path);
  });
}

function validateReactFile(path) {
  const text = source(path);
  for (const match of staticMatches(text, /<(?:Link|NavLink|a)\b[^>]*?\b(?:to|href)\s*=\s*(['"])(.*?)\1[^>]*>/gs)) {
    const target = match[2].trim();
    if (!target || target === '#') fail(path, `empty or placeholder navigation target ${JSON.stringify(match[2])}`);
    else if (target.startsWith('/') && !matchesReactRoute(target)) fail(path, `internal React target is not registered: ${target}`);
  }
  for (const match of staticMatches(text, /<button\b([^>]*)>/gs)) {
    const attrs = match[1];
    const hasAction = /\bonClick\s*=/.test(attrs) || /\btype\s*=\s*(['"])submit\1/.test(attrs);
    const explicitNonAction = /\b(?:disabled|aria-disabled\s*=\s*(['"])true\1|data-non-action\s*=)/.test(attrs);
    if (!hasAction && !explicitNonAction) fail(path, 'button has no handler/submit behavior or explicit non-action semantics');
  }
  // These phrases assert external state or a consequential operation. A
  // customer CTA may not use them unless it has an explicit durable-record
  // evidence annotation that a reviewer can trace to its data source.
  for (const match of staticMatches(text, /<(?:button|Link|a)\b([^>]*)>([\s\S]*?)<\/(?:button|Link|a)>/g)) {
    const attrs = match[1];
    const label = match[2].replace(/<[^>]+>/g, ' ').replace(/\{[^}]+\}/g, ' ').replace(/\s+/g, ' ').trim();
    if (/\b(?:publish(?:ed|ing)?|deploy(?:ed|ing)?|charge(?:d|ing)?|confirm payment|send to site studio)\b/i.test(label)
      && !/data-record-backed\s*=\s*(['"])true\1/.test(attrs)) {
      fail(path, `customer CTA makes unsupported operational claim: ${JSON.stringify(label)}`);
    }
  }
}

const portalFiles = [
  ...files(resolve(root, 'frontend/src/components/portal'), (path) => /\.(?:jsx|tsx)$/.test(path)),
  resolve(root, 'frontend/src/pages/CustomerPortalDashboard.jsx'),
].filter(existsSync);
for (const path of portalFiles) validateReactFile(path);

const routing = resolve(root, 'backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.routing.yml');
const drupalRoutes = new Set();
if (existsSync(routing)) {
  for (const match of staticMatches(source(routing), /^([a-zA-Z][\w.]*)\s*:/gm)) drupalRoutes.add(match[1]);
}
else notes.push('Drupal routing map is unavailable; Drupal route validation skipped.');

function validateDrupalFile(path) {
  const text = source(path);
  for (const match of staticMatches(text, /(?:Url::fromRoute|path)\(\s*(['"])([\w.]+)\1/g)) {
    const route = match[2];
    if (route.startsWith('famtastic_pipeline.') && !drupalRoutes.has(route)) fail(path, `unregistered Drupal route: ${route}`);
  }
  for (const match of staticMatches(text, /<(?:a|form)\b[^>]*?\b(?:href|action)\s*=\s*(['"])(.*?)\1[^>]*>/gs)) {
    const target = match[2].trim();
    if (!target || target === '#') fail(path, `empty or placeholder href/action ${JSON.stringify(match[2])}`);
  }
  for (const match of staticMatches(text, /<button\b([^>]*)>/gs)) {
    const attrs = match[1];
    const hasSubmit = /\btype\s*=\s*(['"])submit\1/.test(attrs);
    const explicitNonAction = /\b(?:disabled|aria-disabled\s*=\s*(['"])true\1|data-non-action\s*=)/.test(attrs);
    if (!hasSubmit && !explicitNonAction) fail(path, 'button has no submit behavior or explicit non-action semantics');
  }
}

const drupalFiles = [
  ...files(resolve(root, 'backend/web/themes/custom/famtastic_admin'), (path) => /\.(?:php|twig|html)$/.test(path)),
  ...files(resolve(root, 'backend/web/modules/custom/famtastic_pipeline/src/Controller'), (path) => path.endsWith('.php')),
];
for (const path of drupalFiles) validateDrupalFile(path);

if (errors.length) {
  console.error('Navigation/action inventory: FAIL');
  for (const error of errors) console.error(`- ${error}`);
  process.exitCode = 1;
}
else console.log(`Navigation/action inventory: PASS (${portalFiles.length} portal sources, ${drupalFiles.length} Drupal sources)`);
for (const note of notes) console.log(`NOTE: ${note}`);
