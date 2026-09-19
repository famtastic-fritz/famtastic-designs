#!/usr/bin/env node
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { creatorCreditHtml, approvedLogo } from './creator-credit.mjs';
approvedLogo();
const root = process.argv[2];
if (!root) throw new Error('Usage: check-creator-credit.mjs ARTIFACT_DIRECTORY');
let count = 0;
function check(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const file = join(dir, entry.name);
    if (entry.isSymbolicLink()) throw new Error('Symlink not allowed');
    if (entry.isDirectory()) check(file);
    else if (entry.name.endsWith('.html')) {
      const html = readFileSync(file, 'utf8');
      if (!html.includes(creatorCreditHtml())) throw new Error(`Missing exact creator credit: ${file}`);
      count++;
    }
  }
}
check(root);
if (!count) throw new Error('No HTML artifacts checked');
console.log(`PASS creator credit: ${count} HTML artifacts`);
