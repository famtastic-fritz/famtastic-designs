import { createError, defineEventHandler, setHeader } from 'h3';
import { useRuntimeConfig } from '#imports';
import { getServerCmsContent } from '../../utils/cms';

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  if (!config.public.enableAdminProof || config.public.enableAdminProof === 'false') {
    throw createError({ statusCode: 404, statusMessage: 'Admin proof is disabled.' });
  }
  setHeader(event, 'cache-control', 'no-store, max-age=0');
  return await getServerCmsContent();
});
