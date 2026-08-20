#!/usr/bin/env node
import { chromium } from '../frontend/node_modules/playwright/index.mjs';
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawn } from 'node:child_process';

const output = resolve(process.argv[2] || '');
if (!output) throw new Error('Usage: provider-browser-qa.mjs <artifact-directory>');
const manifest = JSON.parse(readFileSync(join(output, 'manifest.json'), 'utf8'));
const directions = JSON.parse(readFileSync(join(output, 'directions.json'), 'utf8'));
if (directions.length !== 6 || manifest.directions?.length !== 6) throw new Error('Exactly six directions required');
const screenshotsDir = join(output, 'screenshots');
mkdirSync(screenshotsDir, { recursive: true });
const sha = (bytes) => createHash('sha256').update(bytes).digest('hex');
const port = 9400 + (process.pid % 400);
const server = spawn('python3', ['-m', 'http.server', String(port), '--bind', '127.0.0.1', '--directory', output], { stdio: 'ignore' });
await new Promise((resolveWait) => setTimeout(resolveWait, 800));

const profiles = { desktop: { width: 1440, height: 1000 }, mobile: { width: 390, height: 844 } };
const routes = [{ id: 'review', entry: 'index.html' }, ...manifest.directions.map((item) => ({ id: item.id, entry: item.entry }))];
const results = {};
const screenshots = [];
const reviewContactSheets = [];
const browser = await chromium.launch({ headless: true });
try {
  for (const [profile, viewport] of Object.entries(profiles)) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    for (const route of routes) {
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];
      const failedRequests = [];
      page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
      page.on('pageerror', (error) => pageErrors.push(error.message));
      page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));
      const response = await page.goto(`http://127.0.0.1:${port}/${route.entry}`, { waitUntil: 'networkidle' });
      const inspect = await page.evaluate(() => {
        const visible = (element) => {
          const rect = element.getBoundingClientRect();
          return rect.width > 0 && rect.height > 0;
        };
        const links = [...document.querySelectorAll('a[href]')];
        const reviewDirectionLinks = [...document.querySelectorAll('[data-proof-direction] a[href]')];
        const localAnchors = links.map((link) => link.getAttribute('href')).filter((href) => href?.startsWith('#') && href !== '#');
        const externalResources = performance.getEntriesByType('resource')
          .map((entry) => entry.name)
          .filter((url) => /^https?:/.test(url) && !url.startsWith(location.origin));
        const canvas = document.createElement('canvas');
        const measure = canvas.getContext('2d');
        const headingWordOverflow = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(visible).flatMap((heading) => {
          const style = getComputedStyle(heading);
          measure.font = style.font;
          const available = heading.clientWidth;
          return (heading.textContent || '').trim().split(/\s+/).filter((word) => measure.measureText(word).width > available + 1)
            .map((word) => ({ tag: heading.tagName, word, measuredWidth: Math.round(measure.measureText(word).width), clientWidth: available, overflowWrap: style.overflowWrap, wordBreak: style.wordBreak }));
        });
        const inaccessibleHorizontalScrollers = [...document.querySelectorAll('body *')].filter((element) => {
          const style = getComputedStyle(element);
          const scrolls = ['auto', 'scroll'].includes(style.overflowX) && element.scrollWidth > element.clientWidth + 1;
          const named = element.hasAttribute('aria-label') || element.hasAttribute('aria-labelledby');
          const keyboardReachable = element.tabIndex >= 0;
          return scrolls && !(named && keyboardReachable);
        }).map((element) => ({ tag: element.tagName, className: element.className, scrollWidth: element.scrollWidth, clientWidth: element.clientWidth }));
        const parseColor = (value) => {
          const match = value.match(/rgba?\((\d+)[, ]+(\d+)[, ]+(\d+)/);
          return match ? match.slice(1,4).map(Number) : null;
        };
        const opaqueColor = (value) => {
          if (!value || value === 'transparent') return null;
          const alpha = value.match(/rgba\([^)]*[, /]\s*([\d.]+)\s*\)$/);
          if (alpha && Number(alpha[1]) < .98) return null;
          return parseColor(value);
        };
        const luminance = ([r,g,b]) => {
          const values = [r,g,b].map((value) => { const v = value / 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; });
          return .2126 * values[0] + .7152 * values[1] + .0722 * values[2];
        };
        const ratio = (one, two) => { const [high, low] = [luminance(one), luminance(two)].sort((a,b) => b-a); return (high + .05) / (low + .05); };
        const lowContrastNumbers = [...document.querySelectorAll('.number')].flatMap((element) => {
          const foreground = parseColor(getComputedStyle(element).color);
          let parent = element.parentElement;
          let background = null;
          while (parent && !background) {
            const color = getComputedStyle(parent).backgroundColor;
            if (opaqueColor(color)) background = opaqueColor(color);
            parent = parent.parentElement;
          }
          if (!foreground || !background) return [];
          const contrast = ratio(foreground, background);
          const fontSize = parseFloat(getComputedStyle(element).fontSize);
          const threshold = fontSize >= 24 ? 3 : 4.5;
          return contrast + .01 < threshold ? [{ text: element.textContent?.trim(), contrast: Number(contrast.toFixed(2)), threshold, foreground: getComputedStyle(element).color }] : [];
        });
        const paintedBackground = (element) => {
          let parent = element;
          while (parent) {
            const color = getComputedStyle(parent).backgroundColor;
            if (opaqueColor(color)) return opaqueColor(color);
            parent = parent.parentElement;
          }
          return [255,255,255];
        };
        const solidBackground = (element) => {
          let parent = element;
          while (parent) {
            const style = getComputedStyle(parent);
            const before = getComputedStyle(parent, '::before');
            const after = getComputedStyle(parent, '::after');
            const hasPaintedPseudo = [before, after].some((pseudo) => {
              if (!pseudo || pseudo.content === 'none') return false;
              const pseudoColor = pseudo.backgroundColor;
              return (pseudo.backgroundImage && pseudo.backgroundImage !== 'none') || Boolean(opaqueColor(pseudoColor));
            });
            if (hasPaintedPseudo || (style.backgroundImage && style.backgroundImage !== 'none')) return null;
            const color = style.backgroundColor;
            if (opaqueColor(color)) return opaqueColor(color);
            parent = parent.parentElement;
          }
          return [255,255,255];
        };
        const solidSurfaceTextContrastFailures = [...document.querySelectorAll('body *')].flatMap((element) => {
          if (!visible(element) || ['SCRIPT','STYLE','OPTION','SELECT','INPUT','TEXTAREA'].includes(element.tagName)) return [];
          const directText = [...element.childNodes].filter((node) => node.nodeType === Node.TEXT_NODE).map((node) => node.textContent || '').join(' ').trim();
          if (!directText) return [];
          const foreground = parseColor(getComputedStyle(element).color);
          const background = solidBackground(element);
          if (!foreground || !background) return [];
          const style = getComputedStyle(element);
          const fontSize = parseFloat(style.fontSize);
          const fontWeight = Number.parseInt(style.fontWeight, 10) || 400;
          const threshold = fontSize >= 24 || (fontSize >= 18.66 && fontWeight >= 700) ? 3 : 4.5;
          const contrast = ratio(foreground, background);
          return contrast + .01 < threshold ? [{ tag: element.tagName, text: directText.slice(0,100), contrast: Number(contrast.toFixed(2)), threshold, foreground: style.color, background: getComputedStyle(element).backgroundColor }] : [];
        });
        const nonNeutralSelects = [...document.querySelectorAll('select')].flatMap((select) => {
          if (!visible(select)) return [];
          const first = select.options[0];
          const neutralText = /choose|select|pick|arrival|path|role|era/i.test(first?.textContent || '');
          return select.selectedIndex !== 0 || !first || !first.disabled || !neutralText
            ? [{ id: select.id, selectedIndex: select.selectedIndex, firstText: first?.textContent?.trim() || '', firstDisabled: Boolean(first?.disabled) }]
            : [];
        });
        const inlineEventHandlers = [...document.querySelectorAll('body *')].flatMap((element) => [...element.attributes]
          .filter((attribute) => /^on/i.test(attribute.name))
          .map((attribute) => ({ tag: element.tagName, attribute: attribute.name })));
        const activeFormSubmitters = [...document.querySelectorAll('form')].flatMap((form) => {
          const submitters = [...form.querySelectorAll('button:not([type]),button[type="submit"],input[type="submit"],input[type="image"]')];
          return submitters.length ? [{ id: form.id, submitterCount: submitters.length }] : [];
        });
        const focusContrastFailures = [...document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].flatMap((element) => {
          if (!visible(element) || element.hasAttribute('disabled')) return [];
          element.focus({preventScroll:true});
          const style = getComputedStyle(element);
          const background = paintedBackground(element.parentElement || element);
          const candidates = [parseColor(style.outlineColor), parseColor(style.boxShadow)].filter(Boolean);
          const best = candidates.length ? Math.max(...candidates.map((color) => ratio(color, background))) : 0;
          return best + .01 < 3 ? [{ tag: element.tagName, text: (element.textContent || element.getAttribute('name') || element.getAttribute('aria-label') || '').trim().slice(0,80), contrast: Number(best.toFixed(2)), outlineColor: style.outlineColor, boxShadow: style.boxShadow, background: getComputedStyle(element).backgroundColor }] : [];
        });
        const numberHeadingCollisions = [...document.querySelectorAll('.movement')].flatMap((movement) => {
          const number = movement.querySelector('.number');
          const heading = movement.querySelector('h2');
          if (!number || !heading) return [];
          const a = number.getBoundingClientRect();
          const b = heading.getBoundingClientRect();
          const overlapX = Math.max(0, Math.min(a.right,b.right) - Math.max(a.left,b.left));
          const overlapY = Math.max(0, Math.min(a.bottom,b.bottom) - Math.max(a.top,b.top));
          return overlapX > 1 && overlapY > 1 ? [{ number: number.textContent?.trim(), heading: heading.textContent?.trim(), overlapX: Math.round(overlapX), overlapY: Math.round(overlapY) }] : [];
        });
        return {
          title: document.title,
          lang: document.documentElement.lang,
          h1Count: document.querySelectorAll('h1').length,
          h1Text: document.querySelector('h1')?.textContent?.replace(/\s+/g, ' ').trim() || '',
          textLength: document.body.innerText.length,
          visibleLinks: links.filter(visible).length,
          badAnchors: localAnchors.filter((href) => !document.querySelector(href)),
          unnamedLinks: links.filter((link) => visible(link) && !(link.textContent || '').trim() && !link.getAttribute('aria-label')).length,
          missingAlt: [...document.images].filter((image) => !image.hasAttribute('alt')).length,
          brokenImages: [...document.images].filter((image) => !image.complete || image.naturalWidth < 1).length,
          externalResources,
          scriptSources: [...document.scripts].map((script) => script.src).filter(Boolean),
          iframeCount: document.querySelectorAll('iframe').length,
          scrollWidth: document.documentElement.scrollWidth,
          innerWidth: window.innerWidth,
          fictionalDisclosure: /fictional|concept proof|prototype/i.test(document.body.innerText) && /confirm|confirmation|not a live/i.test(document.body.innerText),
          mainPresent: Boolean(document.querySelector('main')),
          navPresent: Boolean(document.querySelector('nav, [role="navigation"]')),
          sectionCount: document.querySelectorAll('main section').length
          ,headingWordOverflow
          ,inaccessibleHorizontalScrollers
          ,lowContrastNumbers
          ,focusContrastFailures
          ,numberHeadingCollisions
          ,solidSurfaceTextContrastFailures
          ,nonNeutralSelects
          ,inlineEventHandlers
          ,activeFormSubmitters
          ,reviewGuidePresent: Boolean(document.querySelector('[data-review-guide]'))
          ,reviewGuideSteps: document.querySelectorAll('[data-review-guide] .step').length
          ,reviewDirectionHrefs: reviewDirectionLinks.map((link) => link.getAttribute('href')).filter(Boolean)
          ,reviewPlainLanguage: /six separate|not six links|shortlist one or two/i.test(document.body.innerText)
        };
      });
      const file = `${route.id}-${profile}.png`;
      await page.screenshot({ path: join(screenshotsDir, file), fullPage: true });
      const directionRoute = route.id !== 'review';
      const expectedReviewEntries = manifest.directions.map((item) => item.entry).sort();
      const actualReviewEntries = [...inspect.reviewDirectionHrefs].sort();
      const guidedReviewPass = directionRoute || (
        inspect.reviewGuidePresent
        && inspect.reviewGuideSteps === 3
        && inspect.reviewPlainLanguage
        && actualReviewEntries.length === 6
        && actualReviewEntries.every((entry, index) => entry === expectedReviewEntries[index])
      );
      const passed = response?.ok() === true
        && inspect.lang === 'en'
        && inspect.h1Count === 1
        && inspect.textLength >= (directionRoute ? 700 : 400)
        && inspect.visibleLinks >= (directionRoute ? 4 : 6)
        && inspect.badAnchors.length === 0
        && inspect.unnamedLinks === 0
        && inspect.missingAlt === 0
        && inspect.brokenImages === 0
        && inspect.externalResources.length === 0
        && inspect.scriptSources.length === 0
        && inspect.iframeCount === 0
        && inspect.scrollWidth <= inspect.innerWidth + 1
        && inspect.headingWordOverflow.length === 0
        && inspect.inaccessibleHorizontalScrollers.length === 0
        && inspect.lowContrastNumbers.length === 0
        && inspect.focusContrastFailures.length === 0
        && inspect.numberHeadingCollisions.length === 0
        && inspect.solidSurfaceTextContrastFailures.length === 0
        && inspect.nonNeutralSelects.length === 0
        && inspect.inlineEventHandlers.length === 0
        && inspect.activeFormSubmitters.length === 0
        && inspect.fictionalDisclosure
        && inspect.mainPresent
        && guidedReviewPass
        && (!directionRoute || (inspect.navPresent && inspect.sectionCount >= 5))
        && consoleErrors.length === 0
        && pageErrors.length === 0
        && failedRequests.length === 0;
      results[`${route.id}:${profile}`] = { passed, status: response?.status() || 0, ...inspect, guidedReviewPass, consoleErrors, pageErrors, failedRequests };
      screenshots.push({ route: route.id, profile, file: `screenshots/${file}`, sha256: sha(readFileSync(join(screenshotsDir, file))) });
      await page.close();
    }
    await context.close();
  }
  for (const profile of Object.keys(profiles)) {
    const sheet = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 });
    const cards = manifest.directions.map((item) => {
      const label = String(item.name || item.id).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
      return `<figure><figcaption>${item.id.toUpperCase()} — ${label}</figcaption><img src="http://127.0.0.1:${port}/screenshots/${item.id}-${profile}.png" alt="${label} ${profile} capture"></figure>`;
    }).join('');
    await sheet.setContent(`<!doctype html><html><head><style>html,body{margin:0;background:#111;color:#fff;font:700 16px/1.25 Arial,sans-serif}main{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;padding:12px;align-items:start}figure{margin:0;background:#222;border:1px solid #555}figcaption{padding:10px;position:sticky;top:0;background:#111;z-index:1}img{display:block;width:100%;height:auto}</style></head><body><main>${cards}</main></body></html>`, { waitUntil: 'networkidle' });
    await sheet.waitForFunction(() => [...document.images].every((image) => image.complete && image.naturalWidth > 0));
    const file = `review-contact-sheet-${profile}.png`;
    await sheet.screenshot({ path: join(screenshotsDir, file), fullPage: true });
    reviewContactSheets.push({ profile, file: `screenshots/${file}`, sha256: sha(readFileSync(join(screenshotsDir, file))) });
    await sheet.close();
  }
} finally {
  await browser.close();
  server.kill('SIGTERM');
}

const directionResults = Object.entries(results).filter(([key]) => !key.startsWith('review:'));
const reviewResults = Object.entries(results).filter(([key]) => key.startsWith('review:'));
const assertions = {
  exact_six_directions: directions.length === 6 && manifest.directions.length === 6,
  exact_fourteen_route_profiles: Object.keys(results).length === 14,
  twelve_direction_screenshots: screenshots.filter((item) => item.route !== 'review').length === 12,
  desktop_width_1440: profiles.desktop.width === 1440,
  mobile_width_390: profiles.mobile.width === 390,
  all_routes_passed: Object.values(results).every((item) => item.passed),
  review_hub_has_plain_language_three_step_guide: reviewResults.every(([, item]) => item.reviewGuidePresent && item.reviewGuideSteps === 3 && item.reviewPlainLanguage),
  review_hub_has_exact_six_direction_destinations: reviewResults.every(([, item]) => item.guidedReviewPass),
  no_mobile_overflow: directionResults.filter(([key]) => key.endsWith(':mobile')).every(([, item]) => item.scrollWidth <= item.innerWidth + 1),
  no_external_runtime_assets: directionResults.every(([, item]) => item.externalResources.length === 0),
  no_console_or_page_errors: directionResults.every(([, item]) => item.consoleErrors.length === 0 && item.pageErrors.length === 0)
  ,no_heading_word_fragmentation: directionResults.every(([, item]) => item.headingWordOverflow.length === 0)
  ,no_inaccessible_scoped_horizontal_scrollers: directionResults.every(([, item]) => item.inaccessibleHorizontalScrollers.length === 0)
  ,number_contrast_passed: directionResults.every(([, item]) => item.lowContrastNumbers.length === 0)
  ,focus_indicator_contrast_passed: directionResults.every(([, item]) => item.focusContrastFailures.length === 0)
  ,no_number_heading_collisions: directionResults.every(([, item]) => item.numberHeadingCollisions.length === 0)
  ,solid_surface_text_contrast_passed: directionResults.every(([, item]) => item.solidSurfaceTextContrastFailures.length === 0)
  ,forms_begin_neutral_and_do_not_submit: directionResults.every(([, item]) => item.nonNeutralSelects.length === 0 && item.activeFormSubmitters.length === 0)
  ,no_inline_event_handlers: directionResults.every(([, item]) => item.inlineEventHandlers.length === 0)
};
const report = {
  schema: 'famtastic.browser-qa.v1',
  generated_at: new Date().toISOString(),
  engine: 'Playwright Chromium',
  profiles,
  assertions,
  results,
  screenshots,
  review_contact_sheets: reviewContactSheets,
  passed: Object.values(assertions).every(Boolean)
};
writeFileSync(join(output, 'browser-results.json'), JSON.stringify(report, null, 2) + '\n');
console.log(`${report.passed ? 'PASS' : 'FAIL'}: provider browser QA (${Object.keys(results).length} route profiles)`);
console.log(`Evidence: ${join(output, 'browser-results.json')}`);
if (!report.passed) process.exit(1);
