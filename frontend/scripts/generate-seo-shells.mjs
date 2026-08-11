import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SEO_PAGES, seoForPath } from '../src/seo.js';

const root = dirname(fileURLToPath(import.meta.url));
const distDir = join(root, '..', 'dist');
const templatePath = join(distDir, 'index.html');
const template = await readFile(templatePath, 'utf8');

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

function replaceTag(html, pattern, replacement) {
  return pattern.test(html) ? html.replace(pattern, replacement) : html.replace('</head>', `    ${replacement}\n  </head>`);
}

function renderShell(path) {
  const seo = seoForPath(path);
  let html = template;
  html = html.replace(/<title>[\s\S]*?<\/title>/i, `<title>${escapeHtml(seo.title)}</title>`);
  html = replaceTag(
    html,
    /<meta\s+name=["']description["']\s+content=["'][^"']*["']\s*\/?>/i,
    `<meta name="description" content="${escapeHtml(seo.description)}" />`,
  );
  html = replaceTag(
    html,
    /<meta\s+name=["']keywords["']\s+content=["'][^"']*["']\s*\/?>/i,
    `<meta name="keywords" content="${escapeHtml(seo.keywords)}" />`,
  );
  html = replaceTag(
    html,
    /<link\s+rel=["']canonical["']\s+href=["'][^"']*["']\s*\/?>/i,
    `<link rel="canonical" href="${escapeHtml(seo.canonical)}" />`,
  );

  const tags = {
    'og:site_name': seo.siteName,
    'og:type': 'website',
    'og:title': seo.title,
    'og:description': seo.ogDescription,
    'og:url': seo.canonical,
    'og:image': seo.image,
  };
  for (const [property, content] of Object.entries(tags)) {
    html = replaceTag(
      html,
      new RegExp(`<meta\\s+property=["']${property}["']\\s+content=["'][^"']*["']\\s*/?>`, 'i'),
      `<meta property="${property}" content="${escapeHtml(content)}" />`,
    );
  }

  const twitter = {
    'twitter:card': 'summary_large_image',
    'twitter:title': seo.title,
    'twitter:description': seo.twitterDescription,
    'twitter:image': seo.image,
  };
  for (const [name, content] of Object.entries(twitter)) {
    html = replaceTag(
      html,
      new RegExp(`<meta\\s+name=["']${name}["']\\s+content=["'][^"']*["']\\s*/?>`, 'i'),
      `<meta name="${name}" content="${escapeHtml(content)}" />`,
    );
  }
  return html;
}

function renderDynamicShell(path, title, description) {
  let html = renderShell('/');
  const canonical = `https://famtasticdesigns.com${path}/`;
  html = html.replace(/<title>[\s\S]*?<\/title>/i, `<title>${escapeHtml(title)} | FAMtastic Designs</title>`);
  html = html.replace(/<meta name="description" content="[^"]*" \/>/i, `<meta name="description" content="${escapeHtml(description)}" />`);
  html = html.replace(/<link rel="canonical" href="[^"]*" \/>/i, `<link rel="canonical" href="${escapeHtml(canonical)}" />`);
  html = html.replace(/<meta property="og:url" content="[^"]*" \/>/i, `<meta property="og:url" content="${escapeHtml(canonical)}" />`);
  html = html.replace(/<meta property="og:title" content="[^"]*" \/>/i, `<meta property="og:title" content="${escapeHtml(title)} | FAMtastic Designs" />`);
  return html;
}

async function dynamicRoutes() {
  const source = process.env.FAMTASTIC_SITEMAP_SOURCE_URL || 'https://famtasticdesigns.com/web/jsonapi/node';
  const types = ['service_page', 'package_page', 'case_study', 'blog_post'];
  const routes = [];
  for (const type of types) {
    let next = `${source}/${type}?page%5Blimit%5D=50`;
    let pages = 0;
    while (next && pages < 20) {
      const response = await fetch(next, { signal: AbortSignal.timeout(10000) });
      if (!response.ok) throw new Error(`Sitemap source failed for ${type}: ${response.status}`);
      const payload = await response.json();
      for (const node of payload.data || []) {
        const attributes = node.attributes || {};
        const path = attributes.path?.alias;
        if (!attributes.status || !/^\/(services|packages|work|blog)\/[a-z0-9][a-z0-9/-]*$/.test(path || '')) continue;
        routes.push({
          path,
          title: attributes.field_meta_title || attributes.title || 'FAMtastic Designs',
          description: attributes.field_meta_description || `Learn about ${attributes.title || 'this solution'} from FAMtastic Designs.`,
          lastmod: attributes.changed?.slice(0, 10) || '',
        });
      }
      next = payload.links?.next?.href || '';
      pages += 1;
    }
  }
  return routes;
}

for (const path of Object.keys(SEO_PAGES)) {
  if (path === '/') continue;
  const target = join(distDir, path.replace(/^\//, ''), 'index.html');
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, renderShell(path));
}

const discoveredRoutes = await dynamicRoutes();
for (const route of discoveredRoutes) {
  const target = join(distDir, route.path.replace(/^\//, ''), 'index.html');
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, renderDynamicShell(route.path, route.title, route.description));
}

const changedByPath = new Map(discoveredRoutes.map((route) => [route.path, route.lastmod]));
const buildDate = new Date().toISOString().slice(0, 10);
const sitemapPaths = [...new Set([...Object.keys(SEO_PAGES), ...discoveredRoutes.map((route) => route.path)])];
const sitemapUrls = sitemapPaths.map((path) => {
  const canonical = `https://famtasticdesigns.com${path === '/' ? '/' : `${path}/`}`;
  const lastmod = changedByPath.get(path) || buildDate;
  return `  <url>\n    <loc>${escapeHtml(canonical)}</loc>\n    <lastmod>${lastmod}</lastmod>\n  </url>`;
});

await writeFile(
  join(distDir, 'sitemap.xml'),
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${sitemapUrls.join('\n')}\n</urlset>\n`,
);

await writeFile(
  join(distDir, 'robots.txt'),
  `User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /login\nDisallow: /portal\nDisallow: /p/\nDisallow: /reset-password\nDisallow: /verify-email\nDisallow: /web/admin\n\nSitemap: https://famtasticdesigns.com/sitemap.xml\n`,
);
