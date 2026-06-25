import { useRequestFetch } from '#imports';
import { getLocalFamtasticContent } from '../app/utils/cms';

export function useFamtasticContent() {
  const fallback = getLocalFamtasticContent();
  const requestFetch = useRequestFetch();

  async function getContent() {
    try {
      return await requestFetch('/api/famtastic-content', {
        headers: {
          'cache-control': 'no-cache',
        },
      });
    } catch (error) {
      console.error('[cms] Falling back to local content.', error);
      return fallback;
    }
  }

  return {
    getContent,
    fallback,
  };
}
