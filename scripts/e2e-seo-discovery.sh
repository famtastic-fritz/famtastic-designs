#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root/frontend"
npm run build >/dev/null

test -s dist/sitemap.xml
test -s dist/robots.txt
grep -q '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' dist/sitemap.xml
grep -q '<loc>https://famtasticdesigns.com/</loc>' dist/sitemap.xml
grep -q '<loc>https://famtasticdesigns.com/start/</loc>' dist/sitemap.xml
grep -q '<loc>https://famtasticdesigns.com/services/ai-chatbot/</loc>' dist/sitemap.xml
grep -q '^Sitemap: https://famtasticdesigns.com/sitemap.xml$' dist/robots.txt
grep -q '^Disallow: /portal$' dist/robots.txt
grep -q '<link rel="canonical" href="https://famtasticdesigns.com/services/"' dist/services/index.html
grep -q '<link rel="canonical" href="https://famtasticdesigns.com/services/ai-chatbot/"' dist/services/ai-chatbot/index.html

echo "PASS: crawlable public routes have canonical shells, XML sitemap discovery, and private-route robots exclusions."
