import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { createError, defineEventHandler, readBody } from 'h3';
import { useRuntimeConfig } from '#imports';
import { createDirectusLead } from '~/server/utils/directus';

const storageDir = () => join(process.env.INIT_CWD || process.cwd(), '.data');
const storageFile = () => join(storageDir(), 'famtastic-leads.json');

function normalizeString(value: unknown) {
  return typeof value === 'string' ? value.trim() : '';
}

function normalizeList(value: unknown) {
  if (Array.isArray(value)) return value.map((item) => normalizeString(item)).filter(Boolean);
  if (typeof value === 'string' && value.trim()) return [value.trim()];
  return [];
}

function detectFormType(body: Record<string, any>) {
  const requested = normalizeString(body.form_type);
  if (requested) return requested;
  return body.project_type || body.industry || body.goals ? 'full_intake' : 'consultation';
}

function normalizeLead(body: Record<string, any>) {
  const formType = detectFormType(body);
  return {
    form_type: formType,
    project_type: normalizeString(body.project_type) || (formType === 'consultation' ? normalizeString(body.service_needed) || 'Not sure yet' : 'Not sure yet'),
    business_name: normalizeString(body.business_name),
    industry: normalizeString(body.industry),
    current_website: normalizeString(body.current_website),
    social_url: normalizeString(body.social_url),
    location: normalizeString(body.location),
    goals: normalizeList(body.goals),
    timeline: normalizeString(body.timeline),
    budget: normalizeString(body.budget),
    name: normalizeString(body.name),
    email: normalizeString(body.email),
    phone: normalizeString(body.phone),
    preferred_contact_method: normalizeString(body.preferred_contact_method) || 'Email',
    best_time_to_reach: normalizeString(body.best_time_to_reach),
    message: normalizeString(body.message),
    service_needed: normalizeString(body.service_needed),
    utm_source: normalizeString(body.utm_source),
    utm_medium: normalizeString(body.utm_medium),
    utm_campaign: normalizeString(body.utm_campaign),
    utm_content: normalizeString(body.utm_content),
    utm_term: normalizeString(body.utm_term),
    referrer: normalizeString(body.referrer),
    landing_page: normalizeString(body.landing_page),
    device_type: normalizeString(body.device_type),
    submitted_at: normalizeString(body.submitted_at) || new Date().toISOString(),
    status: normalizeString(body.status) || 'New Lead',
    notes: normalizeString(body.notes),
  };
}

async function saveLeadLocally(lead: Record<string, any>) {
  await mkdir(storageDir(), { recursive: true });
  const existing = existsSync(storageFile()) ? JSON.parse(await readFile(storageFile(), 'utf8')) : { leads: [] as Record<string, any>[] };
  existing.leads.push(lead);
  await writeFile(storageFile(), JSON.stringify(existing, null, 2));
  return {
    ok: true,
    mode: 'local',
    saved_to: '.data/famtastic-leads.json',
    lead,
  };
}

export default defineEventHandler(async (event) => {
  const body = await readBody<Record<string, any>>(event);
  const config = useRuntimeConfig();
  const lead = normalizeLead(body || {});

  if (!lead.name || !lead.email || !/^\S+@\S+\.\S+$/.test(lead.email)) {
    throw createError({ statusCode: 400, statusMessage: 'A valid name and email are required.' });
  }

  if (lead.form_type === 'consultation' && !lead.service_needed) {
    throw createError({ statusCode: 400, statusMessage: 'Service needed is required for consultation requests.' });
  }

  if (lead.form_type === 'full_intake' && !lead.business_name) {
    throw createError({ statusCode: 400, statusMessage: 'Business name is required for the full intake form.' });
  }

  const desiredMode = config.public.leadStorageMode || 'local';
  if (desiredMode === 'directus' && config.directusUrl) {
    try {
      await createDirectusLead(lead);
      return {
        ok: true,
        mode: 'directus',
        saved_to: 'directus.leads',
        lead,
      };
    } catch (error) {
      console.error('[leads] Directus write failed, falling back to local storage.', error);
    }
  }

  return await saveLeadLocally(lead);
});
