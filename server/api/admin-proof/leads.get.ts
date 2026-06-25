import { createError, defineEventHandler, getQuery } from 'h3';
import { useRuntimeConfig } from '#imports';
import { assertAdminProofEnabled, readLocalLeads } from '../../utils/admin-proof';

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  assertAdminProofEnabled();
  const query = getQuery(event);
  if (!config.adminProofPin || query.pin !== config.adminProofPin) {
    throw createError({ statusCode: 401, statusMessage: 'Invalid admin proof PIN.' });
  }

  return await readLocalLeads();
});
