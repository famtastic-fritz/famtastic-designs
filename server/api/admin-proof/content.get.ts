import { defineEventHandler, setHeader } from 'h3';
import { useRuntimeConfig } from '#imports';
import { getServerCmsContent } from '../../utils/cms';
import { assertAdminProofEnabled } from '../../utils/admin-proof';

export default defineEventHandler(async (event) => {
  assertAdminProofEnabled();
  setHeader(event, 'cache-control', 'no-store, max-age=0');
  return await getServerCmsContent();
});
