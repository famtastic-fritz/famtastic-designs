import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { createError, defineEventHandler, readBody } from 'h3';
import { createDirectusLead } from '~/server/utils/directus';

const requiredFields = [
  'project_type',
  'business_name',
  'industry',
  'current_website',
  'social_url',
  'location',
  'goals',
  'timeline',
  'budget',
  'name',
  'email',
  'phone',
  'preferred_contact_method',
  'best_time_to_reach',
  'message',
  'utm_source',
  'utm_medium',
  'utm_campaign',
  'utm_content',
  'utm_term',
  'referrer',
  'landing_page',
  'device_type',
  'submitted_at',
  'status',
] as const;

function normalizeLead(body: Record<string, any>) {
  return {
    project_type: body.project_type || 'Not sure yet',
    business_name: body.business_name || '',
    industry: body.industry || '',
    current_website: body.current_website || '',
    social_url: body.social_url || '',
    location: body.location || '',
    goals: Array.isArray(body.goals) ? body.goals : body.goals ? [body.goals] : [],
    timeline: body.timeline || '',
    budget: body.budget || '',
    name: body.name || '',
    email: body.email || '',
    phone: body.phone || '',
    preferred_contact_method: body.preferred_contact_method || 'Email',
    best_time_to_reach: body.best_time_to_reach || '',
    message: body.message || '',
    utm_source: body.utm_source || '',
    utm_medium: body.utm_medium || '',
    utm_campaign: body.utm_campaign || '',
    utm_content: body.utm_content || '',
    utm_term: body.utm_term || '',
    referrer: body.referrer || '',
    landing_page: body.landing_page || '',
    device_type: body.device_type || '',
    submitted_at: body.submitted_at || new Date().toISOString(),
    status: body.status || 'New Lead',
    assigned_owner: body.assigned_owner || '',
    notes: body.notes || '',
  };
}

async function saveLeadLocally(lead: Record<string, any>) {
  const storageDir = join(process.cwd(), '.data');
  const storageFile = join(storageDir, 'famtastic-leads.json');
  await mkdir(storageDir, { recursive: true });
  const existing = existsSync(storageFile)
    ? JSON.parse(await readFile(storageFile, 'utf8'))
    : { leads: [] as Record<string, any>[] };

  existing.leads.push(lead);
  await writeFile(storageFile, JSON.stringify(existing, null, 2));

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

  if (!body?.project_type || !body?.business_name || !body?.name || !body?.email) {
    throw createError({ statusCode: 400, statusMessage: 'Missing required lead fields' });
  }

  const lead = normalizeLead(body);
  for (const field of requiredFields) {
    if (!(field in lead)) {
      throw createError({ statusCode: 500, statusMessage: `Lead payload missing field: ${field}` });
    }
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
      return await saveLeadLocally(lead);
    }
  }

  return await saveLeadLocally(lead);
});
