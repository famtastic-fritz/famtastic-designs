import { defineEventHandler } from 'h3';
import { getServerCmsContent } from '~/server/utils/cms';

export default defineEventHandler(async () => {
  return await getServerCmsContent();
});
