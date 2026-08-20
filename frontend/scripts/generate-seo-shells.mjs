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

function safeJsonLd(value) {
  return JSON.stringify(value).replaceAll('<', '\\u003c');
}

function injectJsonLd(html, graph) {
  const script = `<script type="application/ld+json">${safeJsonLd({
    '@context': 'https://schema.org',
    '@graph': graph,
  })}</script>`;
  const existing = /<script type="application\/ld\+json">[\s\S]*?<\/script>/i;
  return existing.test(html)
    ? html.replace(existing, script)
    : html.replace('</head>', `    ${script}\n  </head>`);
}

function safePrerenderMarkup(value) {
  return String(value || '')
    .replace(/<script\b[\s\S]*?<\/script>/gi, '')
    .replace(/<style\b[\s\S]*?<\/style>/gi, '')
    .replace(/\son\w+\s*=\s*("[^"]*"|'[^']*')/gi, '')
    .replace(/\s(?:src|href)\s*=\s*("|')\s*javascript:[\s\S]*?\1/gi, '');
}

function injectPrerenderedContent(html, { title, description, body = '', contentType = 'page' }) {
  const articleBody = safePrerenderMarkup(body);
  const tag = contentType === 'blog_post' || contentType === 'case_study' ? 'article' : 'section';
  const fallback = `<${tag} class="seo-prerender" data-seo-prerender="true"><h1>${escapeHtml(title)}</h1><p>${escapeHtml(description)}</p>${articleBody}</${tag}>`;
  return html.replace(/<div id="root">[\s\S]*?<\/div>/i, `<div id="root">${fallback}</div>`);
}

function organizationEntities() {
  return [
    {
      '@type': 'Organization',
      '@id': 'https://famtasticdesigns.com/#organization',
      name: 'FAMtastic Designs',
      url: 'https://famtasticdesigns.com/',
      logo: {
        '@type': 'ImageObject',
        url: 'https://famtasticdesigns.com/brand/famtastic-mark.svg',
      },
      address: {
        '@type': 'PostalAddress',
        streetAddress: '1729 NW St. Lucie West Blvd #1181',
        addressLocality: 'Port Saint Lucie',
        addressRegion: 'FL',
        postalCode: '34986',
        addressCountry: 'US',
      },
    },
    {
      '@type': 'WebSite',
      '@id': 'https://famtasticdesigns.com/#website',
      url: 'https://famtasticdesigns.com/',
      name: 'FAMtastic Designs',
      publisher: { '@id': 'https://famtasticdesigns.com/#organization' },
      inLanguage: 'en-US',
    },
  ];
}

function breadcrumbEntity(path, title) {
  if (path === '/') return null;
  const pieces = path.split('/').filter(Boolean);
  const items = [{ '@type': 'ListItem', position: 1, name: 'Home', item: 'https://famtasticdesigns.com/' }];
  pieces.forEach((piece, index) => {
    const itemPath = `/${pieces.slice(0, index + 1).join('/')}/`;
    items.push({
      '@type': 'ListItem',
      position: index + 2,
      name: index === pieces.length - 1 ? title : piece.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
      item: `https://famtasticdesigns.com${itemPath}`,
    });
  });
  return { '@type': 'BreadcrumbList', '@id': `https://famtasticdesigns.com${path}/#breadcrumb`, itemListElement: items };
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
  const graph = organizationEntities();
  const breadcrumb = breadcrumbEntity(path, seo.title.split(' | ')[0]);
  if (breadcrumb) graph.push(breadcrumb);
  graph.push({
    '@type': path === '/' ? 'WebPage' : path === '/contact' ? 'ContactPage' : 'WebPage',
    '@id': `${seo.canonical}#webpage`,
    url: seo.canonical,
    name: seo.title,
    description: seo.description,
    isPartOf: { '@id': 'https://famtasticdesigns.com/#website' },
    ...(breadcrumb ? { breadcrumb: { '@id': `${seo.canonical}#breadcrumb` } } : {}),
  });
  return injectPrerenderedContent(injectJsonLd(html, graph), {
    title: seo.title.split(' | ')[0],
    description: seo.description,
  });
}

function renderDynamicShell(path, title, description, contentType, changed, body) {
  let html = renderShell('/');
  const canonical = `https://famtasticdesigns.com${path}/`;
  const brandSuffix = ' | FAMtastic Designs';
  const fullTitle = title.includes('FAMtastic Designs') || title.length + brandSuffix.length > 70
    ? title
    : `${title}${brandSuffix}`;
  html = html.replace(/<title>[\s\S]*?<\/title>/i, `<title>${escapeHtml(fullTitle)}</title>`);
  html = html.replace(/<meta name="description" content="[^"]*" \/>/i, `<meta name="description" content="${escapeHtml(description)}" />`);
  html = html.replace(/<link rel="canonical" href="[^"]*" \/>/i, `<link rel="canonical" href="${escapeHtml(canonical)}" />`);
  html = html.replace(/<meta property="og:url" content="[^"]*" \/>/i, `<meta property="og:url" content="${escapeHtml(canonical)}" />`);
  html = html.replace(/<meta property="og:title" content="[^"]*" \/>/i, `<meta property="og:title" content="${escapeHtml(fullTitle)}" />`);
  html = html.replace(/<meta property="og:description" content="[^"]*" \/>/i, `<meta property="og:description" content="${escapeHtml(description)}" />`);
  html = html.replace(/<meta property="og:type" content="[^"]*" \/>/i, `<meta property="og:type" content="${contentType === 'blog_post' ? 'article' : 'website'}" />`);
  html = html.replace(/<meta name="twitter:title" content="[^"]*" \/>/i, `<meta name="twitter:title" content="${escapeHtml(fullTitle)}" />`);
  html = html.replace(/<meta name="twitter:description" content="[^"]*" \/>/i, `<meta name="twitter:description" content="${escapeHtml(description)}" />`);

  const breadcrumb = breadcrumbEntity(path, title);
  const typeByContent = { blog_post: 'BlogPosting', service_page: 'Service', package_page: 'Product', case_study: 'Article' };
  const entityType = typeByContent[contentType] || 'WebPage';
  const entity = {
    '@type': entityType,
    '@id': `${canonical}#primary`,
    url: canonical,
    name: title,
    description,
    ...(entityType === 'BlogPosting' || entityType === 'Article' ? {
      headline: title,
      dateModified: changed || undefined,
      author: { '@type': 'Organization', '@id': 'https://famtasticdesigns.com/#organization' },
      publisher: { '@id': 'https://famtasticdesigns.com/#organization' },
    } : {}),
    ...(entityType === 'Service' ? { provider: { '@id': 'https://famtasticdesigns.com/#organization' } } : {}),
    ...(entityType === 'Product' ? { brand: { '@id': 'https://famtasticdesigns.com/#organization' } } : {}),
  };
  return injectPrerenderedContent(
    injectJsonLd(html, [...organizationEntities(), breadcrumb, entity].filter(Boolean)),
    { title, description, body, contentType },
  );
}

function fieldMarkup(attributes, contentType) {
  if (contentType === 'blog_post' || contentType === 'case_study') {
    return attributes.body?.processed || attributes.body?.value || '';
  }
  const sections = [
    ['Overview', attributes.field_hero_subheadline],
    ['The challenge', attributes.field_pain_points],
    ['How FAMtastic helps', attributes.field_solution_bullets],
    ['Deliverables', attributes.field_features || attributes.field_whats_included],
    ['Best fit', attributes.field_best_for],
  ];
  return sections.map(([heading, value]) => {
    const items = Array.isArray(value) ? value.filter(Boolean) : [];
    if (items.length) return `<h2>${escapeHtml(heading)}</h2><ul>${items.map((item) => `<li>${escapeHtml(typeof item === 'string' ? item : item.value || item.title || '')}</li>`).join('')}</ul>`;
    const text = typeof value === 'string' ? value : value?.value || '';
    return text ? `<h2>${escapeHtml(heading)}</h2><p>${escapeHtml(text)}</p>` : '';
  }).join('');
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
          contentType: type,
          body: fieldMarkup(attributes, type),
        });
      }
      next = payload.links?.next?.href || '';
      pages += 1;
    }
  }
  return routes;
}

// Vite writes the root document first; normalize it through the same metadata
// and structured-data path as every other public shell.
await writeFile(templatePath, renderShell('/'));

// `/portal` also contains public media. Without a physical entry document,
// Apache treats that directory as a forbidden listing instead of handing the
// authenticated SPA route to React.
const portalShellPath = join(distDir, 'portal', 'index.html');
await mkdir(dirname(portalShellPath), { recursive: true });
await writeFile(
  portalShellPath,
  template.replace('</head>', '    <meta name="robots" content="noindex,nofollow" />\n  </head>'),
);

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
  await writeFile(target, renderDynamicShell(route.path, route.title, route.description, route.contentType, route.lastmod, route.body));
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
