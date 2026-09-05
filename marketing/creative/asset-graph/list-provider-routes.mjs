#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import process from 'node:process';

const args = process.argv.slice(2);
const familyIndex = args.indexOf('--family');
const family = familyIndex === -1 ? 'all' : args[familyIndex + 1];
const registryIndex = args.indexOf('--registry');
const registryPath = resolve(registryIndex === -1 ? 'marketing/providers.json' : args[registryIndex + 1]);
const families = {
  copy: ['codex_text', 'poe'],
  still: ['openai_image', 'openart', 'gemini_image', 'muapi', 'adobe_firefly_web', 'adobe_photoshop_desktop_mcp'],
  video: ['heygen', 'openart', 'hyperframes', 'remotion', 'moneyprinterturbo', 'adobe_premiere_desktop_mcp', 'adobe_photoshop_desktop_mcp'],
  composition: ['hyperframes', 'remotion', 'adobe_premiere_desktop_mcp', 'adobe_photoshop_desktop_mcp']
};

if (family !== 'all' && !(family in families)) {
  console.error(`Usage: node list-provider-routes.mjs [--family all|copy|still|video|composition] [--registry path]`);
  process.exit(2);
}

try {
  const registry = JSON.parse(await readFile(registryPath, 'utf8'));
  const byId = new Map(registry.providers.map((provider) => [provider.id, provider]));
  const requestedIds = family === 'all' ? registry.providers.map((provider) => provider.id) : families[family];
  const missing = requestedIds.filter((id) => !byId.has(id));
  if (missing.length) throw new Error(`provider registry is missing route ids: ${missing.join(', ')}`);
  const routes = requestedIds.map((id) => {
    const provider = byId.get(id);
    return {
      id: provider.id,
      status: provider.status,
      automation: provider.automation,
      purpose: provider.purpose,
      proof: provider.proof ?? null,
      requires: provider.requires ?? null,
      notes: provider.notes ?? null
    };
  });
  process.stdout.write(`${JSON.stringify({ family, registry: registryPath, routes }, null, 2)}\n`);
} catch (error) {
  console.error(`INVALID PROVIDER CATALOG: ${error.message}`);
  process.exit(1);
}
