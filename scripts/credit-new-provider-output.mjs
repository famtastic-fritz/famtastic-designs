// Called only during construction, before the manifest and independent QA.
import { readFileSync, writeFileSync, copyFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { addCreatorCredit, approvedLogo } from './creator-credit.mjs';
const [output, ...entries] = process.argv.slice(2);
if (!output || !entries.length) throw new Error('Expected output and exact authored HTML entries');
if (existsSync(join(output, 'site-studio-packet.json'))) throw new Error('Refusing a finalized packet; author a new version');
approvedLogo();
for (const entry of entries) {
  if (!/^(?:[a-zA-Z0-9_-]+\/)?index\.html$/.test(entry)) throw new Error('Invalid authored entry');
  const file = join(output, entry);
  // A local byte-identical PNG preserves this runner\'s offline resource contract.
  const logoPath = entry.includes('/') ? '../creator-credit-logo.png' : 'creator-credit-logo.png';
  const html = addCreatorCredit(readFileSync(file, 'utf8')).replace('src="https://famtasticdesigns.com/brand/famtastic-designs-logo-v1.png"', `src="${logoPath}"`);
  writeFileSync(file, html);
}
copyFileSync(new URL('../frontend/public/brand/famtastic-designs-logo-v1.png', import.meta.url), join(output, 'creator-credit-logo.png'));
