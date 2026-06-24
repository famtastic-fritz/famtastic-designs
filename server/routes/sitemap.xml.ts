export default defineEventHandler(() => {
  const config = useRuntimeConfig();
  const base = config.public.siteUrl || 'http://localhost:3001';
  const urls = ['/', '/services', '/pricing', '/packages', '/work', '/get-started', '/contact', '/portal', '/client-portal-login', '/privacy-policy', '/terms-of-service', '/cookie-policy', '/sitemap', '/admin-proof'];
  const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls
    .map((path) => `  <url><loc>${base}${path}</loc></url>`)
    .join('\n')}\n</urlset>`;

  return new Response(xml, {
    headers: {
      'content-type': 'application/xml; charset=utf-8',
    },
  });
});