import { getLocalFamtasticContent } from '../app/utils/cms';

export function useFamtasticContent() {
  const fallback = getLocalFamtasticContent();

  async function getContent() {
    try {
      const response = await fetch('/api/famtastic-content');
      if (!response.ok) {
        throw new Error(`Failed to load content: ${response.status}`);
      }
      return await response.json();
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
