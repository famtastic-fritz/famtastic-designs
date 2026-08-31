import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '../public/showcase/thirst-trap-772');
const [html, css, js, components, prototype] = await Promise.all([
  readFile(path.join(root, 'index.html'), 'utf8'),
  readFile(path.join(root, 'thirst-trap.css'), 'utf8'),
  readFile(path.join(root, 'thirst-trap.js'), 'utf8'),
  readFile(path.join(root, 'component-system.json'), 'utf8').then(JSON.parse),
  readFile(path.join(root, 'prototype-manifest.json'), 'utf8').then(JSON.parse)
]);

assert.match(html, /THIRST[\s\S]*TRAP/);
assert.match(html, /Crave\. Drink\. Repeat\./);
assert.match(html, /data-page-template-id="thirst-trap-social-front-door-v1"/);
assert.match(html, /data-component-system="thirst-trap-772-components-v1"/);
assert.match(html, /https:\/\/www\.instagram\.com\/thirst_trap772\//);
assert.match(html, /https:\/\/www\.facebook\.com\/ThirstTrap772\//);
assert.match(html, /FAMtastic gift concept/i);
assert.match(html, /No charge, no obligation, no account created/);
assert.match(html, /Nothing sends automatically/i);
assert.match(js, /Nothing was sent/);
assert.doesNotMatch(html, /[A-Za-z0-9._%+-]+@(yahoo|gmail)\.com/i);
assert.doesNotMatch(html, /\b(?:\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]\d{3}[-.\s]\d{4}\b/);
assert.doesNotMatch(html, /\$\d/);
assert.doesNotMatch(js, /\bfetch\s*\(/, 'gift prototype must not call an application API');
assert.doesNotMatch(js, /smtp|stripe|twilio|postMessage/i, 'gift prototype must not imply mail, payment, SMS, or cross-window effects');
assert.match(css, /@media \(max-width: 720px\)/);
assert.match(css, /prefers-reduced-motion/);
assert.match(css, /--pink: #ff2f93/);
assert.equal(components.page_recipe.ordered_instances.length, 7);
assert.equal(components.components.length, 7);
assert.equal(components.business_bindings.email, null);
assert.equal(components.business_bindings.phone, null);
assert.equal(prototype.verified_public_destinations.length, 2);
assert.deepEqual(prototype.experience.external_effects, []);

for (const relative of [
  'assets/thirst-trap-hero.webp',
  'assets/thirst-trap-feed.svg',
  'assets/thirst-trap-story.svg'
]) {
  const file = await stat(path.join(root, relative));
  assert.ok(file.size > 2_000, `${relative} should be a substantive local asset`);
}

console.log('PASS Thirst Trap 772: seven reusable components, two verified social destinations, three promotional graphics, zero public contact leakage, and zero application API effects.');
