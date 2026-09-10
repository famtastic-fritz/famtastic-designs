import {readFileSync, existsSync} from 'node:fs';
import {resolve} from 'node:path';

const root = resolve(import.meta.dirname, '..');
const theme = resolve(root, 'backend/web/themes/custom/famtastic_admin');
const css = readFileSync(resolve(theme, 'css/famtastic-admin.css'), 'utf8');
const themePhp = readFileSync(resolve(theme, 'famtastic_admin.theme'), 'utf8');
const pipelineModule = readFileSync(resolve(root, 'backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module'), 'utf8');
const pageTemplate = readFileSync(resolve(theme, 'templates/layout/page--famtastic-admin.html.twig'), 'utf8');
const authTemplate = readFileSync(resolve(theme, 'templates/layout/page--famtastic-auth.html.twig'), 'utf8');
const readme = readFileSync(resolve(theme, 'README.md'), 'utf8');
const required = [
  '.famtastic-admin-shell', '.famtastic-admin-nav', '.famtastic-ops',
  '.tabs', '.messages', '.pager', '.claro-details', '.views-exposed-form',
  '@media (max-width: 782px)', '@media (min-width: 783px)', 'prefers-reduced-motion', 'overflow-x: clip',
];
for (const token of required) {
  if (!css.includes(token)) throw new Error(`Admin theme CSS is missing primitive coverage: ${token}`);
}
for (const token of ['famtastic_admin_preprocess_html', 'famtastic_admin_theme_suggestions_page_alter']) {
  if (!themePhp.includes(token)) throw new Error(`Admin theme hook is missing: ${token}`);
}
for (const file of ['templates/layout/page--famtastic-admin.html.twig', 'templates/layout/page--famtastic-auth.html.twig', 'tests/fixtures/future-module-primitives.html']) {
  if (!existsSync(resolve(theme, file))) throw new Error(`Admin theme contract fixture is missing: ${file}`);
}
if (!readme.includes('Extension contract') || !readme.includes('does **not** claim')) {
  throw new Error('Admin theme README must document extension and fallback boundaries.');
}
if (!pageTemplate.includes('page.pre_content')) throw new Error('Admin page template must preserve page.pre_content.');
if (!authTemplate.includes('page.content') || !authTemplate.includes('famtastic-auth__card')) throw new Error('Account-entry template must preserve native form content inside the FAMtastic shell.');
if (!pipelineModule.includes('famtastic_pipeline_custom_theme') || !pipelineModule.includes("'user.login', 'user.pass', 'user.reset'")) {
  throw new Error('Staff login and recovery routes must explicitly select the FAMtastic admin theme.');
}
for (const token of ['grid-template-columns: 14rem minmax(0, 1fr)', 'position: fixed', 'min-height: 44px']) {
  if (!css.includes(token)) throw new Error(`Admin navigation layout coverage is missing: ${token}`);
}
console.log('FAMtastic admin theme contract: PASS');
