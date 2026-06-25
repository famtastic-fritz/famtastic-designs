export default defineEventHandler(() => {
  const config = useRuntimeConfig();
  const base = config.public.siteUrl || 'http://localhost:3001';

  return new Response(
    [`User-agent: *`, `Allow: /`, `Disallow: /admin-proof`, `Disallow: /payment-proof`, `Sitemap: ${base}/sitemap.xml`].join('\n'),
    {
      headers: {
        'content-type': 'text/plain; charset=utf-8',
      },
    },
  );
});