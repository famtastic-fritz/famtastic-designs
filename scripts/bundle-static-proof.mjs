#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const [sourceDirectory, outputPath] = process.argv.slice(2);
if (!sourceDirectory || !outputPath) {
  console.error('Usage: node scripts/bundle-static-proof.mjs SOURCE_DIRECTORY OUTPUT_HTML');
  process.exit(2);
}

const sourceRoot = fs.realpathSync(sourceDirectory);
const sourceHtml = path.join(sourceRoot, 'index.html');
if (!fs.statSync(sourceHtml).isFile()) {
  throw new Error(`Missing source HTML: ${sourceHtml}`);
}

const mediaTypes = new Map([
  ['.jpg', 'image/jpeg'],
  ['.jpeg', 'image/jpeg'],
  ['.png', 'image/png'],
  ['.webp', 'image/webp'],
]);

const assetPattern = /\{\{asset:([a-zA-Z0-9._-]+)\}\}/g;
let html = fs.readFileSync(sourceHtml, 'utf8');
html = html.replace(assetPattern, (_placeholder, filename) => {
  const assetPath = fs.realpathSync(path.join(sourceRoot, filename));
  if (!assetPath.startsWith(`${sourceRoot}${path.sep}`)) {
    throw new Error(`Asset escapes the source directory: ${filename}`);
  }
  const mediaType = mediaTypes.get(path.extname(assetPath).toLowerCase());
  if (!mediaType) {
    throw new Error(`Unsupported proof asset type: ${filename}`);
  }
  return `data:${mediaType};base64,${fs.readFileSync(assetPath).toString('base64')}`;
});

if (assetPattern.test(html) || html.includes('{{asset:')) {
  throw new Error('One or more proof asset placeholders were not resolved.');
}
if (Buffer.byteLength(html, 'utf8') > 500_000) {
  throw new Error('Bundled proof exceeds the 500 KB callback limit.');
}

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, html);
console.log(JSON.stringify({ output: path.resolve(outputPath), bytes: Buffer.byteLength(html, 'utf8') }));
