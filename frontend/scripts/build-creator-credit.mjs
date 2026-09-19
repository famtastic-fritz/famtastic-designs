// New build outputs only. Historical public source and approval manifests stay intact.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { addCreatorCredit, approvedLogo, sha256 } from '../../scripts/creator-credit.mjs';
const root = new URL('../dist/', import.meta.url);
const directory = decodeURIComponent(root.pathname);
approvedLogo();
const rows = [];
function walk(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const file = join(dir, entry.name);
    if (entry.isDirectory()) walk(file);
    else if (entry.name.endsWith('.html')) {
      const before = readFileSync(file, 'utf8');
      // React renders its credit after Routes; static/SEO HTML needs a fallback.
      const react = before.includes('id="root"');
      const after = react ? before : addCreatorCredit(before);
      if (after !== before) writeFileSync(file, after);
      rows.push({ path: relative(directory, file), source_sha256: sha256(before), output_sha256: sha256(after), credit: react ? 'react-app' : 'static-v1' });
    }
  }
}
walk(directory);
writeFileSync(join(directory, 'creator-credit-build-v1.json'), JSON.stringify({ version: 1, status: 'new-build-not-deployment-or-customer-approval', artifacts: rows }, null, 2) + '\n');
console.log(`Creator credit: ${rows.length} HTML outputs inventoried`);
