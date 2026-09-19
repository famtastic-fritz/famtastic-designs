// Canonical frontend assets are shared with Drupal, never independently redrawn.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const names = ['famtastic-dna.css', 'famtastic-crown-flat.png'];
for (const name of names) {
  const source = path.join(root, 'frontend/public/brand', name);
  const target = path.join(root, 'backend/web/themes/custom/famtastic_admin/brand', name);
  if (process.argv.includes('--check')) assert.deepEqual(fs.readFileSync(target), fs.readFileSync(source), name);
  else fs.copyFileSync(source, target);
}
const logoSource = path.join(root, 'frontend/public/brand/famtastic-designs-logo-v1.png');
const customerLogo = path.join(root, 'backend/web/themes/custom/famtastic_customer/famtastic-designs-logo-v1.png');
if (process.argv.includes('--check')) assert.deepEqual(fs.readFileSync(customerLogo), fs.readFileSync(logoSource), 'customer canonical logo');
else fs.copyFileSync(logoSource, customerLogo);
console.log(process.argv.includes('--check') ? 'PASS: shared brand assets identical' : 'Shared brand assets synchronized');
