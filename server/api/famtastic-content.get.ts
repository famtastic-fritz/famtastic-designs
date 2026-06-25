import { defineEventHandler, setHeader } from 'h3';
import { getServerCmsContent } from '~/server/utils/cms';

export default defineEventHandler(async (event) => {
  setHeader(event, 'cache-control', 'no-store, max-age=0');
  return await getServerCmsContent();
});
