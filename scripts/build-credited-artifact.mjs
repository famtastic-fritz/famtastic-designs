#!/usr/bin/env node
// Author a new version, never retrofit an approved input directory in place.
import { cpSync, existsSync, readdirSync, readFileSync, writeFileSync, lstatSync, realpathSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';
import { addCreatorCredit, approvedLogo, sha256 } from './creator-credit.mjs';
const [sourceArg, outputArg] = process.argv.slice(2);
if (!sourceArg || !outputArg) throw new Error('Usage: build-credited-artifact.mjs SOURCE NEW_OUTPUT');
const source = realpathSync(sourceArg), output = resolve(outputArg);
if (existsSync(output) || output.startsWith(source + '/')) throw new Error('Output must be a new directory outside source');
const files = [];
function walk(dir) {
  for (const name of readdirSync(dir)) {
    const file = join(dir, name), stat = lstatSync(file);
    if (stat.isSymbolicLink()) throw new Error('Symlinks are not supported');
    if (stat.isDirectory()) walk(file);
    else files.push(relative(source, file));
  }
}
walk(source);
approvedLogo();
cpSync(source, output, { recursive: true });
const artifacts = files.filter(file => file.endsWith('.html')).map(file => {
  const before = readFileSync(join(source, file), 'utf8');
  const after = addCreatorCredit(before);
  writeFileSync(join(output, file), after);
  return { path: file, source_sha256: sha256(before), output_sha256: sha256(after) };
});
writeFileSync(join(output, 'creator-credit-build-v1.json'), JSON.stringify({ version: 1, status: 'new-authored-version-needs-review', provenance: 'Copied pre-existing manifests remain historical source receipts, not approval of this version.', artifacts }, null, 2) + '\n');
console.log(`New credited version: ${artifacts.length} HTML files`);
