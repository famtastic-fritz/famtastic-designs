import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { createError } from 'h3';

const projectDataRoot = () => join(process.env.INIT_CWD || process.cwd(), '.data');
const overrideFile = () => join(projectDataRoot(), 'famtastic-admin-content.json');
const leadsFile = () => join(projectDataRoot(), 'famtastic-leads.json');

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

export function isAdminProofEnabled() {
  return process.env.ENABLE_ADMIN_PROOF === 'true';
}

export function assertAdminProofEnabled() {
  if (!isAdminProofEnabled()) {
    throw createError({ statusCode: 404, statusMessage: 'Local proof admin is disabled.' });
  }
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
  await mkdir(projectDataRoot(), { recursive: true });
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
