import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { SEO_PAGES, seoForPath, offerJsonLd, OFFER_JSONLD_ID } from '../src/seo.js';

/** Paths whose shell carries structured data crawlers must see without JS. */
const JSON_LD_BY_PATH = {
  '/199': offerJsonLd,
};

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

  const jsonLd = JSON_LD_BY_PATH[path];
  if (jsonLd) {
    // JSON-LD is escaped only for `<`, which is all that can break out of a
    // script block; escaping quotes here would corrupt the JSON itself.
    const payload = JSON.stringify(jsonLd()).replaceAll('<', '\\u003c');
    // Carries the same id the runtime injector looks up, so the client updates
    // this block in place instead of appending a duplicate Product graph.
    html = html.replace(
      '</head>',
      `    <script type="application/ld+json" id="${OFFER_JSONLD_ID}">${payload}</script>\n  </head>`,
    );
  }

  return html;
}

for (const path of Object.keys(SEO_PAGES)) {
  if (path === '/') continue;
  const target = join(distDir, path.replace(/^\//, ''), 'index.html');
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, renderShell(path));
}

/* ------------------------------------------------------------------ */
/* robots.txt + sitemap.xml                                            */
/* ------------------------------------------------------------------ */

const SITE_URL = 'https://famtasticdesigns.com';

/**
 * CMS-backed URLs (services, packages, posts, case studies) are only known at
 * build time if a Drupal is reachable. Set SITEMAP_SOURCE_URL to include them;
 * without it the sitemap still ships with every static route rather than not
 * shipping at all.
 */
async function dynamicUrls() {
  const base = (process.env.SITEMAP_SOURCE_URL ?? '').replace(/\/+$/, '');
  if (!base) return [];

  const bundles = [
    ['service_page', '/services'],
    ['package_page', '/packages'],
    ['blog_post', '/blog'],
    ['case_study', '/work'],
  ];
  const urls = [];

  for (const [bundle, prefix] of bundles) {
    try {
      const res = await fetch(`${base}/jsonapi/node/${bundle}?filter[status]=1&page[limit]=200`, {
        headers: { Accept: 'application/vnd.api+json' },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      for (const node of json.data ?? []) {
        const aliasPath = node?.attributes?.path?.alias;
        const slug = aliasPath ? aliasPath.split('/').filter(Boolean).pop() : null;
        if (slug) urls.push(`${prefix}/${slug}`);
      }
    } catch (err) {
      console.warn(`[sitemap] skipped ${bundle}: ${err.message}`);
    }
  }

  return urls;
}

const staticUrls = ['/', ...Object.keys(SEO_PAGES).filter((path) => path !== '/')];
const allUrls = [...new Set([...staticUrls, ...(await dynamicUrls())])];

const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${allUrls
  .map((path) => {
    const loc = `${SITE_URL}${path === '/' ? '/' : `${path}/`}`;
    // The offer page is the campaign destination, so it outranks the rest.
    const priority = path === '/' ? '1.0' : path === '/199' ? '0.9' : '0.7';
    return `  <url>\n    <loc>${loc}</loc>\n    <priority>${priority}</priority>\n  </url>`;
  })
  .join('\n')}
</urlset>
`;

await writeFile(join(distDir, 'sitemap.xml'), sitemap);

await writeFile(
  join(distDir, 'robots.txt'),
  `User-agent: *
Allow: /

# Token-scoped prospect pages are private to one customer and must not be indexed.
Disallow: /p/
Disallow: /login
Disallow: /admin

Sitemap: ${SITE_URL}/sitemap.xml
`,
);

console.log(`[seo] ${allUrls.length} urls in sitemap.xml, robots.txt written`);
