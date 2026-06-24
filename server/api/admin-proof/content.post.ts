import { createError, defineEventHandler, readBody } from 'h3';
import { useRuntimeConfig } from '#imports';
import { writeAdminOverrides } from '../../utils/admin-proof';

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const body = await readBody<Record<string, any>>(event);
  const pin = typeof body?.pin === 'string' ? body.pin : '';
  if (!config.adminProofPin || pin !== config.adminProofPin) {
    throw createError({ statusCode: 401, statusMessage: 'Invalid admin proof PIN.' });
  }

  if (!body?.content || typeof body.content !== 'object') {
    throw createError({ statusCode: 400, statusMessage: 'Missing content payload.' });
  }

  const path = await writeAdminOverrides(body.content);
  return { ok: true, savedTo: path };
});