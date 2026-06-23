import { createDirectus, createItem, readItems, rest, staticToken } from '@directus/sdk';

type DirectusClient = ReturnType<typeof createDirectus>;

function getDirectusClient() {
  const config = useRuntimeConfig();
  if (!config.directusUrl) {
    return null;
  }

  let client = createDirectus(config.directusUrl).with(rest());
  if (config.directusToken) {
    client = client.with(staticToken(config.directusToken));
  }
  return client;
}

export async function fetchDirectusCollection(collection: string, query: Record<string, any> = {}) {
  const client = getDirectusClient();
  if (!client) {
    throw new Error('Directus URL is not configured');
  }
  return await client.request(readItems(collection as never, query as never));
}

export async function createDirectusLead(payload: Record<string, any>) {
  const client = getDirectusClient();
  if (!client) {
    throw new Error('Directus URL is not configured');
  }
  return await client.request(createItem('leads' as never, payload as never));
}
