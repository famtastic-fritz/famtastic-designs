import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const dataDir = () => join(process.cwd(), '.data');
const overrideFile = () => join(dataDir(), 'famtastic-admin-content.json');
const leadsFile = () => join(dataDir(), 'famtastic-leads.json');

function mergeDeep(base: any, override: any): any {
  if (Array.isArray(base) || Array.isArray(override)) {
    return override ?? base;
  }

  if (typeof base === 'object' && base && typeof override === 'object' && override) {
    const merged: Record<string, any> = { ...base };
    for (const key of Object.keys(override)) {
      merged[key] = key in base ? mergeDeep(base[key], override[key]) : override[key];
    }
    return merged;
  }

  return override ?? base;
}

export async function readAdminOverrides() {
  const file = overrideFile();
  if (!existsSync(file)) {
    return {};
  }

  try {
    return JSON.parse(await readFile(file, 'utf8'));
  } catch {
    return {};
  }
}

export async function writeAdminOverrides(payload: Record<string, any>) {
  await mkdir(dataDir(), { recursive: true });
  await writeFile(overrideFile(), JSON.stringify(payload, null, 2));
  return overrideFile();
}

export async function applyAdminOverrides(base: Record<string, any>) {
  const overrides = await readAdminOverrides();
  return mergeDeep(base, overrides);
}

export async function readLocalLeads() {
  const file = leadsFile();
  if (!existsSync(file)) {
    return { leads: [] };
  }

  try {
    return JSON.parse(await readFile(file, 'utf8'));
  } catch {
    return { leads: [] };
  }
}

export function getAdminOverridePath() {
  return overrideFile();
}

export function getLocalLeadsPath() {
  return leadsFile();
}
