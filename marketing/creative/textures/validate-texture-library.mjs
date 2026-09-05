#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const library = JSON.parse(await readFile(path.join(ROOT, 'texture-library.json'), 'utf8'));

if (library.schema !== 'famtastic.creative-texture-library.v1') throw new Error('Unexpected texture-library schema.');
if (!Array.isArray(library.assets) || library.assets.length < 5) throw new Error('Expected at least five texture assets.');

const ids = new Set();
for (const asset of library.assets) {
  if (!asset.id || ids.has(asset.id)) throw new Error(`Duplicate or missing asset id: ${asset.id}`);
  ids.add(asset.id);
  if (asset.status !== 'procedural_local') throw new Error(`${asset.id}: must be a local procedural asset.`);
  const source = await readFile(path.join(ROOT, asset.file), 'utf8');
  if (!/^<svg\b/.test(source.trim())) throw new Error(`${asset.id}: not an SVG.`);
  if (!/viewBox="0 0 1600 1000"/.test(source)) throw new Error(`${asset.id}: unexpected viewBox.`);
  if (/<text\b|<tspan\b/i.test(source)) throw new Error(`${asset.id}: texture must not contain text.`);
}

console.log(`Texture library validated: ${library.assets.length} local SVG sources, no embedded text.`);
