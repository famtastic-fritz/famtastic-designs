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

for (const path of Object.keys(SEO_PAGES)) {
  if (path === '/') continue;
  const target = join(distDir, path.replace(/^\//, ''), 'index.html');
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, renderShell(path));
}

const sitemapUrls = Object.keys(SEO_PAGES).map((path) => {
  const { canonical } = seoForPath(path);
  return `  <url>\n    <loc>${escapeHtml(canonical)}</loc>\n  </url>`;
});

await writeFile(
  join(distDir, 'sitemap.xml'),
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${sitemapUrls.join('\n')}\n</urlset>\n`,
);

await writeFile(
  join(distDir, 'robots.txt'),
  `User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /login\nDisallow: /portal\nDisallow: /p/\nDisallow: /reset-password\nDisallow: /verify-email\nDisallow: /web/admin\n\nSitemap: https://famtasticdesigns.com/sitemap.xml\n`,
);
