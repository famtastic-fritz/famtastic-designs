import { getLocalFamtasticContent } from '~/app/utils/cms';

export function useFamtasticContent() {
  const config = useRuntimeConfig();
  const mode = computed(() => config.public.cmsMode || 'local');

  async function getContent() {
    if (mode.value === 'local') {
      return getLocalFamtasticContent();
    }

    try {
      return await $fetch('/api/famtastic-content');
    } catch (error) {
      console.error('[cms] Failed to load Directus content in app composable; using local fallback.', error);
      return getLocalFamtasticContent();
    }
  }

  return {
    mode,
    getContent,
    fallback: getLocalFamtasticContent(),
  };
}
