import { createError, defineEventHandler } from 'h3';
import { useRuntimeConfig } from '#imports';
import { getServerCmsContent } from '../../utils/cms';

export default defineEventHandler(async () => {
  return await getServerCmsContent();
});