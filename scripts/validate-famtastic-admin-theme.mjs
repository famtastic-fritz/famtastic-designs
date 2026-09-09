import {readFileSync, existsSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..');
const theme = resolve(root, 'backend/web/themes/custom/famtastic_admin');
const css = readFileSync(resolve(theme, 'css/famtastic-admin.css'), 'utf8');
const themePhp = readFileSync(resolve(theme, 'famtastic_admin.theme'), 'utf8');
const readme = readFileSync(resolve(theme, 'README.md'), 'utf8');
const required = [
  '.famtastic-admin-shell', '.famtastic-admin-nav', '.famtastic-ops',
  '.tabs', '.messages', '.pager', '.claro-details', '.views-exposed-form',
  '@media (max-width: 782px)', 'prefers-reduced-motion', 'overflow-x: clip',
];
for (const token of required) {
  if (!css.includes(token)) throw new Error(`Admin theme CSS is missing primitive coverage: ${token}`);
}
for (const token of ['famtastic_admin_preprocess_html', 'famtastic_admin_theme_suggestions_page_alter']) {
  if (!themePhp.includes(token)) throw new Error(`Admin theme hook is missing: ${token}`);
}
for (const file of ['templates/layout/page--famtastic-admin.html.twig', 'tests/fixtures/future-module-primitives.html']) {
  if (!existsSync(resolve(theme, file))) throw new Error(`Admin theme contract fixture is missing: ${file}`);
}
if (!readme.includes('Extension contract') || !readme.includes('does **not** claim')) {
  throw new Error('Admin theme README must document extension and fallback boundaries.');
}
console.log('FAMtastic admin theme contract: PASS');
