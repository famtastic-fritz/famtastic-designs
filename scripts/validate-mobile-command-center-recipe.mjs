import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const registryPath = resolve(root, 'frontend/public/component-systems/mobile-command-center.v1.json');
const recipePath = resolve(root, 'docs/design/proofs/tighten-up-your-locs-v2/site-recipe.json');
const [registry, recipe] = await Promise.all([registryPath, recipePath].map(async (path) => JSON.parse(await readFile(path, 'utf8'))));

if (registry.schema !== 'famtastic.component-system.v1' || registry.system_id !== 'mobile-command-center') {
  throw new Error('Mobile command-center registry contract is invalid.');
}
if (recipe.component_system?.ref !== 'frontend/public/component-systems/mobile-command-center.v1.json') {
  throw new Error('Shay recipe is not bound to the mobile command-center registry.');
}
const definitions = new Set(registry.components.map((component) => component.id));
const instances = new Set();
for (const page of recipe.pages || []) {
  if (!page.page_id || !page.route || !Array.isArray(page.components)) throw new Error('Every recipe page needs stable identity, route, and components.');
  for (const component of page.components) {
    if (!definitions.has(component.definition_id)) throw new Error(`Unknown component definition: ${component.definition_id}`);
    if (instances.has(component.instance_id)) throw new Error(`Duplicate component instance: ${component.instance_id}`);
    instances.add(component.instance_id);
  }
}
for (const required of ['public-home', 'owner-desk']) {
  if (!recipe.pages.some((page) => page.page_id === required)) throw new Error(`Missing required recipe page: ${required}`);
}
console.log(`PASS: ${instances.size} stable component instances resolve to ${definitions.size} mobile command-center definitions.`);
