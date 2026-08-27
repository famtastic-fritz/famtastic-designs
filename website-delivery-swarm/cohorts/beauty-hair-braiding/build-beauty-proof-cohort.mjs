#!/usr/bin/env node
/**
 * Local-only Beauty / Hair / Braiding proof preparation.
 *
 * The caller supplies a mapped JSON or CSV file. This tool never reads the
 * source spreadsheet, calls a model, writes Drupal, publishes, or sends mail.
 * It writes structurally compatible a/b/c bundles plus honest Build DNA gates.
 */

import { createHash } from 'node:crypto';
import { deflateSync } from 'node:zlib';
import { existsSync, mkdirSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { dirname, extname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, '../../..');
const INPUT_SCHEMA = 'famtastic.beauty-proof-cohort-input.v1';
const OUTPUT_SCHEMA = 'famtastic.beauty-proof-cohort-output.v1';
const DIRECTIONS = [
  {
    id: 'a',
    name: 'Safe — The Crown Edit',
    band: 'safe',
    level: 2,
    family: 'quiet-editorial',
    colors: ['#181515', '#eadfd3', '#bb765a'],
    art: 'calm editorial composition with the visual subject on the right and generous negative space on the left; warm paper, soft salon light, tactile hair detail, and quiet shadow depth',
  },
  {
    id: 'b',
    name: 'Medium FAMtastic — In Motion',
    band: 'medium-famtastic',
    level: 6,
    family: 'kinetic-poster',
    colors: ['#160c23', '#ff5c9d', '#78e8d1'],
    art: 'confident beauty editorial moving diagonally through frame with room for oversized typography; satin color, chrome accents, layered texture, paper-poster grain, and saturated studio light',
  },
  {
    id: 'c',
    name: 'Ultra FAMtastic — The Texture Room',
    band: 'ultra-famtastic',
    level: 10,
    family: 'immersive-texture-lab',
    colors: ['#07110e', '#c7ff31', '#ac67ff'],
    art: 'premium conceptual beauty tableau with macro texture foreground and sculptural silhouette distance; architectural braid geometry, luminous fibers, dark glass, metallic edge light, and emerald ultraviolet cinema light',
  },
];

function fail(message) {
  throw new Error(message);
}

function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function fileHash(path) {
  return sha256(readFileSync(path));
}

function toJson(value) {
  return JSON.stringify(value, null, 2) + '\n';
}

function writeJson(path, value) {
  writeFileSync(path, toJson(value));
}

function cleanText(value, label, maxLength = 500, required = true) {
  if (value === undefined || value === null || value === '') {
    if (required) fail(label + ' is required.');
    return '';
  }
  const text = String(value).trim().replace(/\s+/g, ' ');
  if (!text && required) fail(label + ' is required.');
  if (text.length > maxLength) fail(label + ' exceeds ' + maxLength + ' characters.');
  return text;
}

function cleanUrl(value, label) {
  const text = cleanText(value, label, 2048);
  let parsed;
  try {
    parsed = new URL(text);
  } catch {
    fail(label + ' must be an absolute http(s) URL.');
  }
  if (!['http:', 'https:'].includes(parsed.protocol) || parsed.username || parsed.password || parsed.search.includes('@')) {
    fail(label + ' must be a safe http(s) URL without credentials or an email address.');
  }
  return parsed.toString();
}

function cleanEmail(value) {
  if (value === undefined || value === null || value === '') return '';
  const email = String(value).trim().toLowerCase();
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) fail('contact_email is not valid.');
  return email;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, function (character) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
  });
}

function slug(value) {
  const result = String(value)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 56);
  return result || 'beauty-business';
}

function splitPipe(value) {
  if (!value) return [];
  return String(value).split('|').map(function (part) { return part.trim(); }).filter(Boolean);
}

function repoRelative(path) {
  const absolute = resolve(path);
  if (!(absolute === repositoryRoot || absolute.startsWith(repositoryRoot + '/'))) {
    fail('Artifacts must be inside this repository: ' + absolute);
  }
  return relative(repositoryRoot, absolute).split('\\').join('/');
}

function gitRevision() {
  const result = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: repositoryRoot, encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : 'unavailable';
}

function parseCsv(text) {
  const rows = [];
  let current = [];
  let field = '';
  let quote = false;
  for (let index = 0; index < text.length; index += 1) {
    const char = text[index];
    if (quote) {
      if (char === '"') {
        if (text[index + 1] === '"') {
          field += '"';
          index += 1;
        } else {
          quote = false;
        }
      } else {
        field += char;
      }
    } else if (char === '"') {
      quote = true;
    } else if (char === ',') {
      current.push(field);
      field = '';
    } else if (char === '\n') {
      current.push(field.replace(/\r$/, ''));
      rows.push(current);
      current = [];
      field = '';
    } else {
      field += char;
    }
  }
  if (quote) fail('CSV has an unterminated quoted value.');
  if (field || current.length) {
    current.push(field.replace(/\r$/, ''));
    rows.push(current);
  }
  if (rows.length < 2) fail('CSV requires a header and at least one lead.');
  const headers = rows.shift().map(function (header) { return header.trim(); });
  return rows.filter(function (row) { return row.some(function (cell) { return cell.trim(); }); }).map(function (row, index) {
    if (row.length !== headers.length) fail('CSV row ' + (index + 2) + ' has the wrong number of columns.');
    const result = {};
    headers.forEach(function (header, column) { result[header] = row[column]; });
    return result;
  });
}

function csvInput(path) {
  const rows = parseCsv(readFileSync(path, 'utf8'));
  const campaign = cleanText(rows[0].campaign_id, 'campaign_id', 128);
  return {
    schema: INPUT_SCHEMA,
    campaign_id: campaign,
    cohort_label: cleanText(rows[0].cohort_label, 'cohort_label', 160, false) || 'Beauty / Hair / Braiding proof cohort',
    source: {
      kind: cleanText(rows[0].source_kind, 'source_kind', 100, false) || 'operator-mapped-csv',
      mapping_version: cleanText(rows[0].mapping_version, 'mapping_version', 100, false) || 'unspecified',
      source_lane: cleanText(rows[0].source_lane, 'source_lane', 80, false) || 'unclassified',
    },
    package_profile: cleanText(rows[0].package_profile, 'package_profile', 120, false) || 'anonymous_safe_medium_ultra_v1',
    leads: rows.map(function (row, index) {
      if (cleanText(row.campaign_id, 'campaign_id row ' + (index + 2), 128) !== campaign) {
        fail('Every CSV row must share one campaign_id.');
      }
      const factUrl = cleanUrl(row.verified_fact_source_url || row.public_profile_url, 'verified_fact_source_url row ' + (index + 2));
      const teaserUrls = splitPipe(row.research_teaser_source_urls || factUrl).map(function (url, sourceIndex) {
        return cleanUrl(url, 'research_teaser_source_urls[' + sourceIndex + '] row ' + (index + 2));
      });
      return {
        lead_id: row.lead_id,
        business_name: row.business_name,
        contact_email: row.contact_email,
        category: row.category,
        location: { city: row.city, region: row.region },
        public_profile_url: row.public_profile_url,
        verified_facts: [{
          fact: row.verified_fact,
          source_url: factUrl,
          source_type: row.verified_fact_source_type || 'public-profile',
        }],
        research_teaser: {
          headline: row.research_teaser_headline,
          body: row.research_teaser_body,
          source_urls: teaserUrls,
        },
        design_clues: {
          palette_hints: splitPipe(row.palette_hints),
          motif_hints: splitPipe(row.motif_hints),
          audience_note: row.audience_note || '',
        },
        observed_service_terms: splitPipe(row.observed_service_terms),
      };
    }),
  };
}

function inputFile(path) {
  const raw = readFileSync(path, 'utf8');
  if (extname(path).toLowerCase() === '.csv') return { raw, value: csvInput(path) };
  if (extname(path).toLowerCase() !== '.json') fail('Input must end in .json or .csv.');
  try {
    return { raw, value: JSON.parse(raw) };
  } catch (error) {
    fail('Could not parse input JSON: ' + error.message);
  }
}

function category(value) {
  const item = cleanText(value, 'category', 80).toLowerCase().replace(/[^a-z]+/g, '-');
  const accepted = ['beauty', 'hair', 'hair-styling', 'braiding', 'hair-braiding', 'beauty-hair-braiding'];
  if (!accepted.includes(item)) fail('Unsupported category: ' + item);
  return item;
}

function hex(value) {
  const match = String(value || '').trim().match(/^#([0-9a-f]{6})$/i);
  return match ? '#' + match[1].toLowerCase() : '';
}

function normalizeLead(value, index) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) fail('leads[' + index + '] must be an object.');
  const factList = value.verified_facts;
  if (!Array.isArray(factList) || factList.length === 0) fail('leads[' + index + '] requires at least one verified_facts entry.');
  const teaser = value.research_teaser;
  if (!teaser || typeof teaser !== 'object' || !Array.isArray(teaser.source_urls) || teaser.source_urls.length === 0) {
    fail('leads[' + index + '] requires a source-backed research_teaser.');
  }
  const facts = factList.map(function (fact, factIndex) {
    return {
      fact: cleanText(fact && fact.fact, 'verified_facts[' + factIndex + '].fact', 600),
      source_url: cleanUrl(fact && fact.source_url, 'verified_facts[' + factIndex + '].source_url'),
      source_type: cleanText(fact && fact.source_type, 'verified_facts[' + factIndex + '].source_type', 100, false) || 'public-source',
    };
  });
  const clues = value.design_clues && typeof value.design_clues === 'object' ? value.design_clues : {};
  const paletteHints = Array.isArray(clues.palette_hints) ? clues.palette_hints.map(hex).filter(Boolean) : [];
  const motifHints = Array.isArray(clues.motif_hints) ? clues.motif_hints.map(function (item) {
    return cleanText(item, 'motif_hints', 120, false);
  }).filter(Boolean) : [];
  const terms = Array.isArray(value.observed_service_terms) ? value.observed_service_terms.map(function (item) {
    return cleanText(item, 'observed_service_terms', 100, false);
  }).filter(Boolean) : [];
  const city = cleanText((value.location || {}).city || value.city, 'location.city', 100, false);
  const region = cleanText((value.location || {}).region || value.region, 'location.region', 100, false);
  const email = cleanEmail(value.contact_email);
  const lead = {
    lead_id: cleanText(value.lead_id, 'lead_id', 160),
    business_name: cleanText(value.business_name, 'business_name', 140),
    category: category(value.category),
    contact_email: email,
    contact_reference: email ? 'sha256:' + sha256(email).slice(0, 20) : 'not-provided',
    location: { city, region },
    public_profile_url: value.public_profile_url ? cleanUrl(value.public_profile_url, 'public_profile_url') : '',
    verified_facts: facts,
    research_teaser: {
      headline: cleanText(teaser.headline, 'research_teaser.headline', 160),
      body: cleanText(teaser.body, 'research_teaser.body', 700),
      source_urls: teaser.source_urls.map(function (url, sourceIndex) {
        return cleanUrl(url, 'research_teaser.source_urls[' + sourceIndex + ']');
      }),
    },
    design_clues: {
      palette_hints: paletteHints,
      motif_hints: motifHints,
      audience_note: cleanText(clues.audience_note, 'audience_note', 300, false),
    },
    observed_service_terms: terms,
  };
  lead.fingerprint = sha256(JSON.stringify({
    lead_id: lead.lead_id,
    business_name: lead.business_name,
    category: lead.category,
    location: lead.location,
    verified_facts: lead.verified_facts,
    research_teaser: lead.research_teaser,
    design_clues: lead.design_clues,
    observed_service_terms: lead.observed_service_terms,
  }));
  return lead;
}

function normalizeInput(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) fail('Input must be an object.');
  if (value.schema && value.schema !== INPUT_SCHEMA) fail('Unsupported input schema: ' + value.schema);
  const campaign = cleanText(value.campaign_id, 'campaign_id', 128);
  if (!/^pc-[a-z0-9-]+$/.test(campaign)) fail('campaign_id must match pc-[a-z0-9-]+.');
  if (!Array.isArray(value.leads) || value.leads.length === 0) fail('Input needs a non-empty leads array.');
  const ids = new Set();
  const names = new Set();
  const leads = value.leads.map(function (lead, index) {
    const normalized = normalizeLead(lead, index);
    if (ids.has(normalized.lead_id)) fail('Duplicate lead_id: ' + normalized.lead_id);
    if (names.has(normalized.business_name.toLowerCase())) fail('Duplicate business_name: ' + normalized.business_name);
    ids.add(normalized.lead_id);
    names.add(normalized.business_name.toLowerCase());
    return normalized;
  });
  return {
    schema: INPUT_SCHEMA,
    campaign_id: campaign,
    cohort_label: cleanText(value.cohort_label, 'cohort_label', 160, false) || 'Beauty / Hair / Braiding proof cohort',
    source: value.source && typeof value.source === 'object' ? {
      kind: cleanText(value.source.kind, 'source.kind', 100, false) || 'operator-mapped-input',
      mapping_version: cleanText(value.source.mapping_version, 'source.mapping_version', 100, false) || 'unspecified',
      source_lane: cleanText(value.source.source_lane, 'source.source_lane', 80, false) || 'unclassified',
    } : { kind: 'operator-mapped-input', mapping_version: 'unspecified', source_lane: 'unclassified' },
    package_profile: cleanText(value.package_profile, 'package_profile', 120, false) || 'anonymous_safe_medium_ultra_v1',
    leads,
  };
}

function options(argv) {
  const value = { input: '', output: '', limit: 10 };
  for (let index = 0; index < argv.length; index += 1) {
    if (argv[index] === '--input') value.input = argv[++index] || '';
    else if (argv[index] === '--output') value.output = argv[++index] || '';
    else if (argv[index] === '--limit') value.limit = Number(argv[++index]);
    else if (argv[index] === '--help' || argv[index] === '-h') {
      console.log('Usage: node website-delivery-swarm/cohorts/beauty-hair-braiding/build-beauty-proof-cohort.mjs --input MAPPED.json|csv --output artifacts/beauty-proof-cohort/CAMPAIGN [--limit 10]');
      process.exit(0);
    } else fail('Unknown argument: ' + argv[index]);
  }
  if (!value.input || !value.output) fail('--input and --output are required.');
  if (!Number.isInteger(value.limit) || value.limit < 1 || value.limit > 50) fail('--limit must be an integer from 1 to 50.');
  return value;
}

function rgb(color) {
  return [Number.parseInt(color.slice(1, 3), 16), Number.parseInt(color.slice(3, 5), 16), Number.parseInt(color.slice(5, 7), 16)];
}

function rgbHex(value) {
  return '#' + value.map(function (channel) { return Math.max(0, Math.min(255, Math.round(channel))).toString(16).padStart(2, '0'); }).join('');
}

function mix(color, target, amount) {
  const origin = rgb(color);
  const end = rgb(target);
  return rgbHex(origin.map(function (channel, index) { return channel + (end[index] - channel) * amount; }));
}

function palette(lead, direction) {
  const source = lead.design_clues.palette_hints.length >= 2
    ? [lead.design_clues.palette_hints[0], lead.design_clues.palette_hints[1], lead.design_clues.palette_hints[2] || direction.colors[2]]
    : direction.colors;
  const adjustment = Number.parseInt(lead.fingerprint.slice(0, 2), 16) / 255;
  return {
    ink: mix(source[0], '#000000', adjustment * 0.16),
    paper: mix(source[1], '#ffffff', 0.06 + adjustment * 0.08),
    accent: source[2],
    soft: mix(source[2], '#ffffff', 0.48),
    deep: mix(source[0], '#000000', 0.36),
  };
}

function motif(lead) {
  if (lead.design_clues.motif_hints.length) return lead.design_clues.motif_hints[0];
  if (lead.category.includes('braid')) return 'interlocking braid geometry';
  if (lead.category.includes('hair')) return 'layered hair texture';
  return 'layered beauty texture';
}

function artSvg(colors, direction, seed) {
  const key = 'art' + seed.slice(0, 10) + direction.id;
  const waves = [];
  const count = direction.id === 'a' ? 5 : direction.id === 'b' ? 9 : 13;
  for (let index = 0; index < count; index += 1) {
    const y = 70 + index * (770 / count);
    const amplitude = 100 + (index % 3) * 31;
    waves.push('<path d="M -90 ' + y + ' C 290 ' + (y - amplitude) + ' 845 ' + (y + amplitude) + ' 1685 ' + (y - amplitude / 2) + '" fill="none" stroke="' + (index % 2 ? colors.accent : colors.soft) + '" stroke-width="' + (12 + (index % 3) * 6) + '" opacity="' + (direction.id === 'a' ? '.31' : direction.id === 'b' ? '.57' : '.72') + '"/>');
  }
  return [
    '<svg class="art" viewBox="0 0 1600 900" role="img" aria-label="Abstract original concept art treatment" preserveAspectRatio="xMidYMid slice">',
    '<defs><linearGradient id="' + key + '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + colors.ink + '"/><stop offset=".54" stop-color="' + colors.deep + '"/><stop offset="1" stop-color="' + colors.accent + '"/></linearGradient>',
    '<radialGradient id="' + key + 'r" cx="74%" cy="28%" r="65%"><stop offset="0" stop-color="' + colors.soft + '" stop-opacity=".9"/><stop offset=".42" stop-color="' + colors.accent + '" stop-opacity=".26"/><stop offset="1" stop-color="' + colors.ink + '" stop-opacity="0"/></radialGradient>',
    '<filter id="' + key + 'f"><feGaussianBlur stdDeviation="21"/></filter></defs>',
    '<rect width="1600" height="900" fill="url(#' + key + ')"/><rect width="1600" height="900" fill="url(#' + key + 'r)"/>',
    '<g transform="rotate(' + (direction.id === 'a' ? 0 : direction.id === 'b' ? 21 : -16) + ' 800 450)">' + waves.join('') + '</g>',
    '<circle cx="1220" cy="255" r="170" fill="' + colors.paper + '" opacity=".12" filter="url(#' + key + 'f)"/><circle cx="1260" cy="300" r="86" fill="' + colors.soft + '" opacity=".68"/>',
    '<path d="M 1050 820 C 1140 610 1280 495 1510 400" fill="none" stroke="' + colors.paper + '" stroke-width="2" opacity=".45"/>',
    '<text x="76" y="826" fill="' + colors.paper + '" opacity=".56" font-size="20" letter-spacing="8">CONCEPT / ' + direction.id.toUpperCase() + '</text></svg>',
  ].join('');
}

function commonCss() {
  return '*{box-sizing:border-box}html{scroll-behavior:smooth;overflow-x:clip}body{margin:0;overflow-x:clip}a{color:inherit}.boundary{padding:10px 4vw;background:#fff1bb;color:#342609;font:700 11px/1.4 Arial,sans-serif;letter-spacing:.02em;text-align:center}.nav{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:22px 5vw;position:relative;z-index:5}.wordmark{font:900 15px/1 Arial,sans-serif;letter-spacing:.12em;text-decoration:none;text-transform:uppercase}.wordmark span{display:block;font-size:9px;letter-spacing:.22em;margin-top:5px;opacity:.62}.links{display:flex;align-items:center;gap:20px}.links a{font:800 11px/1 Arial,sans-serif;letter-spacing:.11em;text-decoration:none;text-transform:uppercase}.button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:14px 19px;font:900 11px/1 Arial,sans-serif;letter-spacing:.1em;text-decoration:none;text-transform:uppercase}.wrap{width:min(1170px,90vw);margin:0 auto}.eyebrow{font:900 11px/1.3 Arial,sans-serif;letter-spacing:.18em;margin:0;text-transform:uppercase}.copy{font-size:18px;line-height:1.7;max-width:630px}.section{padding:108px 0}.section h2{font-size:clamp(46px,7.6vw,105px);letter-spacing:-.075em;line-height:.82;margin:19px 0 30px}.section h3{font-size:23px;letter-spacing:-.04em;margin:0 0 12px}.fact{font-size:14px;line-height:1.65}.footer{display:flex;justify-content:space-between;gap:24px;padding:35px 5vw;font-size:12px;line-height:1.5}@media(max-width:720px){.links a:not(.button){display:none}.nav{padding:18px 5vw}.section{padding:78px 0}.footer{display:block}.footer p{margin:0 0 12px}.copy{font-size:16px}}';
}

function copyFor(lead, direction) {
  const name = escapeHtml(lead.business_name);
  const sourceFact = escapeHtml(lead.verified_facts[0].fact);
  const teaser = escapeHtml(lead.research_teaser.headline);
  const teaserBody = escapeHtml(lead.research_teaser.body);
  const motifText = escapeHtml(motif(lead));
  const city = lead.location.city ? ' for a business visible in ' + escapeHtml(lead.location.city) : '';
  const terms = lead.observed_service_terms.slice(0, 3).map(escapeHtml);
  const chips = (terms.length ? terms : ['Details to confirm', 'Client references', 'Clear next step']).map(function (term) {
    return '<span>' + term + '</span>';
  }).join('');
  const leadText = direction.id === 'a'
    ? name + ' gets a calm, confident front door' + city + '. Hierarchy, breathing room, and an honest next step carry the first impression.'
    : direction.id === 'b'
      ? name + ' gets a bolder visual rhythm without losing the practical path. Type, contrast, and ' + motifText + ' make the direction memorable.'
      : name + ' gets a maximum-FAMtastic world built around material, depth, and a memorable visual thesis while leaving space for the facts a client needs.';
  return {
    name,
    sourceFact,
    teaser,
    teaserBody,
    leadText,
    chips,
    motifText,
    boundary: 'Visual concept only. Actual services, availability, prices, policies, and booking tools require owner confirmation.',
  };
}

function page(lead, direction, colors) {
  const content = copyFor(lead, direction);
  const visual = artSvg(colors, direction, lead.fingerprint);
  const nav = '<nav class="nav"><a class="wordmark" href="#top">' + content.name + '<span>Private concept preview</span></a><div class="links"><a href="#signal">The signal</a><a href="#system">The system</a><a class="button" href="#next">Make this real</a></div></nav>';
  let css;
  let body;
  if (direction.id === 'a') {
    css = ':root{--ink:' + colors.ink + ';--paper:' + colors.paper + ';--accent:' + colors.accent + ';--soft:' + colors.soft + ';--deep:' + colors.deep + '}body{background:var(--paper);color:var(--ink);font-family:Georgia,serif}.wordmark,.links,.button,.eyebrow,.boundary{font-family:Arial,sans-serif}.nav{border-bottom:1px solid #0002}.button{background:var(--ink);color:var(--paper)}.hero{display:grid;grid-template-columns:1.02fr .98fr;min-height:760px}.hero-copy{padding:8vw 5vw 7vw max(5vw,calc((100vw - 1170px)/2));display:flex;flex-direction:column;justify-content:center}.hero h1{font-size:clamp(67px,10vw,150px);font-weight:500;letter-spacing:-.09em;line-height:.73;margin:22px 0 30px}.hero h1 em{color:var(--accent)}.art-stage{min-height:530px;overflow:hidden;background:var(--deep)}.art{display:block;width:100%;height:100%}.signal{background:var(--ink);color:var(--paper)}.signal-grid{display:grid;grid-template-columns:.75fr 1.25fr;gap:90px}.fact-card{border-top:1px solid #fff6;padding-top:28px}.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--ink)}.step{background:var(--paper);padding:32px;min-height:270px}.step b{display:block;color:var(--accent);font:900 14px Arial,sans-serif;margin-bottom:65px}.chips{display:flex;flex-wrap:wrap;gap:10px}.chips span{border:1px solid var(--ink);border-radius:99px;padding:9px 13px;font:800 11px Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase}.next{background:linear-gradient(135deg,var(--soft),var(--paper))}.next-grid{display:grid;grid-template-columns:1fr .85fr;gap:70px}@media(max-width:720px){.hero,.signal-grid,.next-grid,.steps{grid-template-columns:1fr}.hero-copy{padding:76px 5vw}.hero h1{font-size:19vw}.art-stage{min-height:410px}.step b{margin-bottom:30px}}';
    body = '<section class="hero" id="top"><div class="hero-copy"><p class="eyebrow">SAFE / QUIET EDITORIAL</p><h1>Make the details<br><em>feel intentional.</em></h1><p class="copy">' + content.leadText + '</p><p><a class="button" href="#system">Explore the direction</a></p></div><div class="art-stage">' + visual + '</div></section><section class="section signal" id="signal"><div class="wrap signal-grid"><div><p class="eyebrow">Begin with a real signal</p><h2>' + content.teaser + '</h2></div><div class="fact-card"><p class="fact">' + content.sourceFact + '</p><p class="copy">' + content.teaserBody + '</p></div></div></section><section class="section" id="system"><div class="wrap"><p class="eyebrow">Direction logic</p><h2>The signal becomes<br>a system.</h2><p class="copy">A useful site makes facts easy to add, revise, and trust once the owner confirms them. It never fills a gap with made-up availability, prices, policies, or credentials.</p><div class="steps"><article class="step"><b>01</b><h3>Quiet first impression</h3><p class="fact">The owner can introduce the brand without asking visitors to hunt for the point.</p></article><article class="step"><b>02</b><h3>Texture with restraint</h3><p class="fact">Motif, contrast, and spacing do the visual work before a final asset library exists.</p></article><article class="step"><b>03</b><h3>Room for the real story</h3><p class="fact">Useful facts, requests, and integrations can arrive without breaking the visual system.</p></article></div></div></section><section class="section next" id="next"><div class="wrap next-grid"><div><p class="eyebrow">What could come next</p><h2>Built to grow<br>when facts arrive.</h2></div><div><div class="chips">' + content.chips + '</div><p class="copy">' + content.boundary + '</p><a class="button" href="#top">Return to concept</a></div></div></section>';
  } else if (direction.id === 'b') {
    css = ':root{--ink:' + colors.ink + ';--paper:' + colors.paper + ';--accent:' + colors.accent + ';--soft:' + colors.soft + ';--deep:' + colors.deep + '}body{background:var(--ink);color:var(--paper)}.nav{position:absolute;top:34px;left:0;right:0}.button{background:var(--soft);color:var(--ink);transform:rotate(-2deg)}.hero{min-height:870px;position:relative;display:flex;align-items:end;padding:145px 5vw 82px;overflow:hidden}.hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,var(--ink) 2%,#160c23bc 44%,transparent 80%);z-index:1}.art-stage{position:absolute;inset:0}.art{height:100%;width:100%;display:block}.hero-copy{position:relative;z-index:2;width:min(770px,90vw)}.hero h1{font:900 clamp(76px,13vw,188px)/.67 Arial,sans-serif;letter-spacing:-.115em;margin:20px 0 30px;text-transform:uppercase}.hero h1 em{color:var(--accent);font-style:normal}.hero .copy{font-family:Georgia,serif}.ticker{background:var(--accent);color:var(--ink);font:900 18px/1 Arial,sans-serif;letter-spacing:.11em;overflow:hidden;padding:17px 0;text-transform:uppercase;white-space:nowrap}.signal{background:var(--paper);color:var(--ink)}.signal-grid{display:grid;grid-template-columns:1.08fr .92fr;gap:35px}.signal-grid>div{padding:55px;background:var(--soft)}.signal-grid>div+div{background:var(--paper);border:2px solid var(--ink)}.signal h2{font-family:Arial,sans-serif;font-weight:900;text-transform:uppercase}.system{background:linear-gradient(135deg,var(--deep),var(--ink))}.stacks{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.stack{border:1px solid #fff5;padding:28px;min-height:285px;display:flex;flex-direction:column;justify-content:flex-end}.stack:nth-child(2){background:var(--accent);color:var(--ink);transform:translateY(42px)}.stack:nth-child(3){background:var(--soft);color:var(--ink)}.stack b{font:900 54px/1 Arial,sans-serif;letter-spacing:-.08em}.chips{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.chips span{display:grid;place-items:center;min-height:112px;background:var(--paper);color:var(--ink);font:900 13px/1.2 Arial,sans-serif;letter-spacing:.08em;padding:15px;text-align:center;text-transform:uppercase}.next{background:var(--accent);color:var(--ink)}.next h2{font-family:Arial,sans-serif;font-weight:900;text-transform:uppercase}.next .button{background:var(--ink);color:var(--paper)}@media(max-width:720px){.nav{position:relative;top:auto;background:var(--ink)}.hero{min-height:730px;padding:92px 5vw 62px}.hero h1{font-size:21vw}.signal-grid,.stacks,.chips{grid-template-columns:1fr}.signal-grid>div{padding:32px}.stack:nth-child(2){transform:none}.ticker{font-size:13px}}';
    body = '<section class="hero" id="top"><div class="art-stage">' + visual + '</div><div class="hero-copy"><p class="eyebrow">MEDIUM FAMTASTIC / KINETIC POSTER</p><h1>Give the work<br><em>some rhythm.</em></h1><p class="copy">' + content.leadText + '</p><p><a class="button" href="#system">See the system</a></p></div></section><div class="ticker" aria-hidden="true">RESEARCH FIRST · TEXTURE WITH PURPOSE · BUILT TO EXPAND · RESEARCH FIRST · TEXTURE WITH PURPOSE · BUILT TO EXPAND · </div><section class="section signal" id="signal"><div class="wrap signal-grid"><div><p class="eyebrow">Research, then personality</p><h2>' + content.teaser + '</h2></div><div><p class="fact">' + content.sourceFact + '</p><p class="copy">' + content.teaserBody + '</p></div></div></section><section class="section system" id="system"><div class="wrap"><p class="eyebrow">The FAMtastic layer</p><h2>Brand can move<br>without guessing.</h2><p class="copy">This direction turns verified signals into an editorial system with room for strong visuals, useful details, and client questions the owner can answer on their own terms.</p><div class="stacks"><article class="stack"><b>01</b><h3>Headline momentum</h3><p class="fact">A directional opening frame gives the future site a distinct visual memory.</p></article><article class="stack"><b>02</b><h3>Texture as asset</h3><p class="fact">A repeatable pattern can appear in campaigns, social cards, pages, and motion later.</p></article><article class="stack"><b>03</b><h3>Honest conversion</h3><p class="fact">The conversion path remains ready for real requirements, not placeholder promises.</p></article></div></div></section><section class="section next" id="next"><div class="wrap"><p class="eyebrow">The build can stay flexible</p><h2>Keep the energy.<br>Earn the trust.</h2><div class="chips">' + content.chips + '</div><p class="copy">' + content.boundary + '</p><a class="button" href="#top">Back to the top</a></div></section>';
  } else {
    css = ':root{--ink:' + colors.ink + ';--paper:' + colors.paper + ';--accent:' + colors.accent + ';--soft:' + colors.soft + ';--deep:' + colors.deep + '}body{background:var(--ink);color:var(--paper)}.nav{position:absolute;top:28px;left:0;right:0;border-bottom:1px solid #c7ff3166}.button{background:var(--accent);color:var(--ink);border-radius:99px}.hero{position:relative;min-height:920px;display:flex;align-items:end;padding:166px 5vw 75px;overflow:hidden}.art-stage{position:absolute;inset:0}.art{height:100%;width:100%;display:block}.hero:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 82% 20%,transparent 0 23%,var(--ink) 72%),linear-gradient(90deg,var(--ink) 0,#07110ebd 48%,transparent 85%);z-index:1}.hero-copy{position:relative;z-index:2;width:min(820px,92vw)}.hero h1{font:900 clamp(76px,13vw,194px)/.66 Arial,sans-serif;letter-spacing:-.115em;margin:21px 0 31px;text-transform:uppercase}.hero h1 em{color:var(--accent);font-family:Georgia,serif;font-weight:500}.hero .copy{font-size:20px}.signal{background:linear-gradient(135deg,var(--ink),var(--deep))}.signal-grid{display:grid;grid-template-columns:.75fr 1.25fr;gap:80px}.signal h2{color:var(--accent);font-size:clamp(51px,7vw,104px);text-transform:uppercase}.fact-card{position:relative;padding:34px;border:1px solid #c7ff3188;background:#0004}.fact-card:before{content:"SOURCE SIGNAL";position:absolute;top:-10px;left:22px;background:var(--ink);color:var(--soft);font:900 10px Arial,sans-serif;letter-spacing:.16em;padding:4px 10px}.system{background:var(--paper);color:var(--ink)}.system h2{font-family:Arial,sans-serif;font-weight:900;text-transform:uppercase}.orbital{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;align-items:stretch}.orb{position:relative;padding:38px 28px;min-height:330px;border:2px solid var(--ink);overflow:hidden}.orb:after{content:"";position:absolute;right:-64px;bottom:-84px;width:220px;height:220px;border-radius:50%;border:24px solid var(--accent);opacity:.75}.orb:nth-child(2){background:var(--accent)}.orb:nth-child(3){background:var(--soft)}.orb>*{position:relative;z-index:1}.orb b{display:block;font:900 48px/1 Arial,sans-serif;letter-spacing:-.08em;margin-bottom:75px}.chips{display:flex;flex-wrap:wrap;gap:13px}.chips span{border:1px solid var(--accent);border-radius:99px;padding:12px 15px;color:var(--paper);font:900 11px Arial,sans-serif;letter-spacing:.08em;text-transform:uppercase}.next{position:relative;overflow:hidden;background:radial-gradient(circle at 82% 30%,var(--accent),var(--deep) 24%,var(--ink) 65%)}.next h2{font-family:Arial,sans-serif;font-weight:900;text-transform:uppercase}.next-grid{display:grid;grid-template-columns:1fr .85fr;gap:65px}.next .button{background:var(--paper);color:var(--ink)}@media(max-width:720px){.nav{position:relative;top:auto;background:var(--ink)}.hero{min-height:760px;padding:96px 5vw 62px}.hero h1{font-size:20vw}.hero .copy{font-size:17px}.signal-grid,.orbital,.next-grid{grid-template-columns:1fr}.signal-grid{gap:38px}.orb{min-height:250px}}';
    body = '<section class="hero" id="top"><div class="art-stage">' + visual + '</div><div class="hero-copy"><p class="eyebrow">ULTRA FAMTASTIC / IMMERSIVE TEXTURE LAB</p><h1>Turn texture into<br><em>a destination.</em></h1><p class="copy">' + content.leadText + '</p><p><a class="button" href="#signal">Enter the direction</a></p></div></section><section class="section signal" id="signal"><div class="wrap signal-grid"><div><p class="eyebrow">One signal. A full world.</p><h2>' + content.teaser + '</h2></div><div class="fact-card"><p class="fact">' + content.sourceFact + '</p><p class="copy">' + content.teaserBody + '</p></div></div></section><section class="section system" id="system"><div class="wrap"><p class="eyebrow">The texture code</p><h2>Big energy.<br>Clear control.</h2><p class="copy">The visual language can be cinematic without pretending to know what has not been confirmed. The structure can receive real portfolio, policy, and conversion data later.</p><div class="orbital"><article class="orb"><b>01</b><h3>Immersive point of view</h3><p class="fact">A cinematic first frame gives the business a repeatable campaign idea—not just a hero image.</p></article><article class="orb"><b>02</b><h3>Surface language</h3><p class="fact">Material and motif make containers, cards, and visual transitions feel designed rather than merely colored.</p></article><article class="orb"><b>03</b><h3>Future-ready spine</h3><p class="fact">The system can add portfolio, booking, forms, ecommerce, or content modules without losing the thesis.</p></article></div></div></section><section class="section next" id="next"><div class="wrap next-grid"><div><p class="eyebrow">Next approved step</p><h2>From strong proof<br>to working system.</h2></div><div><div class="chips">' + content.chips + '</div><p class="copy">' + content.boundary + '</p><a class="button" href="#top">Return to concept</a></div></div></section>';
  }
  return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title>' + escapeHtml(direction.name) + ' — ' + content.name + '</title><meta name="description" content="Private source-backed FAMtastic Designs concept preview for ' + content.name + '."><style>' + commonCss() + css + '</style></head><body><aside class="boundary">PRIVATE WEBSITE CONCEPT · NOT A LIVE BOOKING, PRICING, OR AVAILABILITY PAGE</aside>' + nav + '<main>' + body + '</main><footer class="footer"><p><strong>' + content.name + '</strong> · ' + escapeHtml(direction.name) + '</p><p>' + content.boundary + '</p></footer></body></html>';
}

function crc32(buffer) {
  let value = 0xffffffff;
  for (let index = 0; index < buffer.length; index += 1) {
    value ^= buffer[index];
    for (let bit = 0; bit < 8; bit += 1) value = (value >>> 1) ^ (0xedb88320 & -(value & 1));
  }
  return (value ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
  const typeBuffer = Buffer.from(type, 'ascii');
  const size = Buffer.alloc(4);
  const checksum = Buffer.alloc(4);
  size.writeUInt32BE(data.length, 0);
  checksum.writeUInt32BE(crc32(Buffer.concat([typeBuffer, data])), 0);
  return Buffer.concat([size, typeBuffer, data, checksum]);
}

function thumbnail(path, colors, direction, seed) {
  const width = 720;
  const height = 405;
  const base = rgb(colors.ink);
  const accent = rgb(colors.accent);
  const soft = rgb(colors.soft);
  const raw = Buffer.alloc((width * 4 + 1) * height);
  const offsetSeed = Number.parseInt(seed.slice(0, 8), 16) / 0xffffffff;
  for (let y = 0; y < height; y += 1) {
    const row = y * (width * 4 + 1);
    raw[row] = 0;
    for (let x = 0; x < width; x += 1) {
      const position = row + 1 + x * 4;
      const horizontal = x / (width - 1);
      const vertical = y / (height - 1);
      const density = direction.id === 'a' ? 6 : direction.id === 'b' ? 14 : 21;
      const wave = Math.max(0, Math.sin((horizontal * density + vertical * 4 + offsetSeed * 7) * Math.PI));
      const glow = Math.max(0, 1 - Math.hypot(horizontal - 0.72, vertical - 0.32) * 1.45);
      const amount = Math.min(1, horizontal * 0.34 + glow * 0.48 + wave * (direction.id === 'a' ? 0.16 : direction.id === 'b' ? 0.34 : 0.52));
      raw[position] = Math.round(base[0] * (1 - amount) + accent[0] * amount + soft[0] * glow * 0.10);
      raw[position + 1] = Math.round(base[1] * (1 - amount) + accent[1] * amount + soft[1] * glow * 0.10);
      raw[position + 2] = Math.round(base[2] * (1 - amount) + accent[2] * amount + soft[2] * glow * 0.10);
      raw[position + 3] = 255;
    }
  }
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8;
  header[9] = 6;
  const image = Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
  writeFileSync(path, image);
}

function qa(bundle, lead) {
  const pages = {};
  const htmlHashes = new Set();
  for (const direction of DIRECTIONS) {
    const htmlPath = join(bundle, direction.id, 'index.html');
    const thumbnailPath = join(bundle, direction.id, 'thumbnail.png');
    const html = readFileSync(htmlPath, 'utf8');
    const anchors = [...html.matchAll(/<a\b[^>]*href="(#[-a-z0-9_]+)"/gi)].map(function (match) { return match[1].slice(1); });
    const checks = {
      one_h1: (html.match(/<h1\b/gi) || []).length === 1,
      noindex: /<meta name="robots" content="noindex,nofollow,noarchive">/i.test(html),
      self_contained: !/(?:src|href)\s*=\s*"https?:\/\/|url\(\s*https?:\/\//i.test(html),
      no_active_content: !/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i.test(html),
      callback_size_limit: Buffer.byteLength(html, 'utf8') <= 500000,
      no_contact_email: !lead.contact_email || !html.toLowerCase().includes(lead.contact_email),
      proof_boundary: /PRIVATE WEBSITE CONCEPT/i.test(html),
      anchors_resolve: anchors.every(function (id) { return html.includes('id="' + id + '"'); }),
      thumbnail_present: existsSync(thumbnailPath) && statSync(thumbnailPath).size > 100 && statSync(thumbnailPath).size <= 1500000,
    };
    pages[direction.id] = {
      passed: Object.values(checks).every(Boolean),
      bytes: Buffer.byteLength(html, 'utf8'),
      sha256: fileHash(htmlPath),
      thumbnail_sha256: fileHash(thumbnailPath),
      checks,
    };
    htmlHashes.add(pages[direction.id].sha256);
  }
  const passed = Object.values(pages).every(function (item) { return item.passed; }) && htmlHashes.size === 3;
  if (!passed) fail('Static QA failed for ' + bundle + '.');
  return {
    schema: 'famtastic.beauty-proof-static-qa.v1',
    classification: 'local-preparation-only',
    static_status: 'passed',
    browser_status: 'not_run',
    independent_visual_review_status: 'not_run',
    customer_delivery_status: 'blocked',
    checks: { exact_three_directions: true, distinct_html: htmlHashes.size === 3, all_static_checks_pass: true },
    pages,
    open_gates: [
      'Gemini Flash Lite Image preflight and receipt-backed original art',
      'Desktop and 390px Playwright screenshots after final art is embedded',
      'Independent visual review and any required repair',
      'Drupal Build DNA registration, owner review, proof publication, and transactional outbox',
    ],
  };
}

function artifacts(paths) {
  return paths.map(function (item) {
    return {
      role: item.role,
      path: repoRelative(item.path),
      sha256: fileHash(item.path),
      retention: item.retention || 'local-artifact',
      rights_status: item.rights_status || 'operator-generated',
    };
  });
}

function dna(lead, bundle, revision, createdAt, sourceHash, paths) {
  const root = repoRelative(bundle);
  return {
    schema: 'famtastic.build-dna.v1',
    build_id: 'beauty-proof-' + sha256(lead.fingerprint + createdAt).slice(0, 16),
    classification: 'local-preparation-only',
    created_at: createdAt,
    repository: { name: 'FAMtastic Designs', revision, worktree_state: 'clean-before-artifact-output' },
    recipe: {
      routine: 'website_proof.generate.v1',
      version: 'beauty-hair-braiding-cohort-prep.v1',
      build_class: 'public-lead-initial-three-proof',
      campaign_id: lead.campaign_id,
      direction_mix: 'a=Safe, b=Medium FAMtastic, c=Ultra FAMtastic',
      creative_controls: {
        research_depth: 'mapped source-backed teaser only',
        typography: 'three distinct editorial layout families',
        texture_and_depth: 'explicit in prompt and page surface',
        original_asset_route: 'Gemini Flash Lite Image planned, not executed',
      },
    },
    correlation: {
      campaign_id: lead.campaign_id,
      source_lead_id: lead.lead_id,
      contact_reference: lead.contact_reference,
      source_input_sha256: sourceHash,
    },
    stages: [
      {
        stage_id: 'input-normalization',
        attempt: 1,
        capability: 'lead-map-normalization',
        execution: { provider: { id: 'deterministic-local' }, model: { id: 'beauty-cohort-schema-v1', status: 'declared-local' }, timing: { status: 'recorded', duration_ms: 0 }, cost: { status: 'not_incurred', currency: 'USD' }, input: { artifact: root + '/intake-redacted.json' } },
        result: { status: 'passed' },
      },
      {
        stage_id: 'research-evidence',
        attempt: 1,
        capability: 'research-evidence-preservation',
        execution: { provider: { id: 'operator-mapped-sources' }, model: { id: null, status: 'not_applicable' }, timing: { status: 'recorded', duration_ms: 0 }, cost: { status: 'not_incurred', currency: 'USD' }, input: { artifact: root + '/research.json' } },
        result: { status: 'passed', note: 'This builder did not browse or infer extra facts.' },
      },
      {
        stage_id: 'creative-direction',
        attempt: 1,
        capability: 'proof-direction-composition',
        execution: { provider: { id: 'deterministic-local' }, model: { id: 'beauty-vertical-system-v1', status: 'declared-local' }, timing: { status: 'recorded', duration_ms: 0 }, cost: { status: 'not_incurred', currency: 'USD' }, output: { artifacts: ['a/design-dna.json', 'b/design-dna.json', 'c/design-dna.json'] } },
        result: { status: 'passed' },
      },
      {
        stage_id: 'preview-art',
        attempt: 1,
        capability: 'original-preview-art',
        execution: {
          provider: { id: 'gemini-developer-api' },
          model: { id: 'gemini-3.1-flash-lite-image', status: 'declared_not_executed' },
          transport: 'Gemini Developer API image-only route',
          timing: { status: 'not_started' },
          cost: { status: 'not_incurred', currency: 'USD', receipt_status: 'not_available' },
          prompt: { artifact: root + '/image-prompts.json' },
          output: { expected_assets: ['a/assets/hero.png', 'b/assets/hero.png', 'c/assets/hero.png'] },
        },
        result: { status: 'gated', reason: 'Local-only builder does not invoke paid image generation.' },
      },
      {
        stage_id: 'prototype-construction',
        attempt: 1,
        capability: 'responsive-proof-pages',
        execution: { provider: { id: 'deterministic-local' }, model: { id: 'self-contained-static-proof-v1', status: 'declared-local' }, timing: { status: 'recorded', duration_ms: 0 }, cost: { status: 'not_incurred', currency: 'USD' }, output: { artifacts: ['a/index.html', 'b/index.html', 'c/index.html'] } },
        result: { status: 'passed', note: 'CSS/SVG visual fallbacks only; final imagery remains gated.' },
      },
      {
        stage_id: 'static-quality-assurance',
        attempt: 1,
        capability: 'proof-static-safety-and-callback-contract',
        execution: { provider: { id: 'deterministic-local' }, model: { id: 'static-qa-v1', status: 'declared-local' }, timing: { status: 'recorded', duration_ms: 0 }, cost: { status: 'not_incurred', currency: 'USD' }, output: { artifact: root + '/quality-report.json' } },
        result: { status: 'passed' },
      },
      {
        stage_id: 'browser-quality-assurance',
        attempt: 1,
        capability: 'desktop-mobile-browser-qa',
        execution: { provider: { id: 'playwright' }, model: { id: 'chromium', status: 'not_executed' }, timing: { status: 'not_started' }, cost: { status: 'not_incurred', currency: 'USD' } },
        result: { status: 'gated', reason: 'Run after final artwork is embedded.' },
      },
      {
        stage_id: 'independent-visual-review',
        attempt: 1,
        capability: 'independent-visual-review',
        execution: { provider: { id: 'independent-review-route' }, model: { id: null, status: 'not_selected' }, timing: { status: 'not_started' }, cost: { status: 'not_incurred', currency: 'USD' } },
        result: { status: 'gated', reason: 'A generator cannot approve its own work.' },
      },
      {
        stage_id: 'owner-send-gate',
        attempt: 1,
        capability: 'owner-review-and-customer-delivery',
        execution: { provider: { id: 'drupal-famtastic-pipeline' }, model: { id: null, status: 'not_applicable' }, timing: { status: 'not_started' }, cost: { status: 'not_incurred', currency: 'USD' } },
        result: { status: 'gated', reason: 'No Drupal record, approval, promotion, or customer email was created.' },
      },
    ],
    artifacts: artifacts(paths),
    retrieval: {
      filesystem: { status: 'prepared', root, build_dna: root + '/build-dna.json' },
      database: { status: 'not_registered', required_operation: 'Run the canonical Drupal Build DNA registration only after a real canonical request exists.' },
      site_studio: { status: 'not_created', required_operation: 'Copy this immutable Build DNA into packet-files only for an eligible handoff.' },
    },
    integrity: { artifact_hash_algorithm: 'sha256', build_dna_status: 'skeleton-with-real-local-artifact-hashes' },
    completion: {
      status: 'gated',
      open_gates: [
        'No paid image generation was executed',
        'No browser screenshots or independent visual approval were executed',
        'No Drupal projection, owner approval, publication, or customer email occurred',
      ],
    },
  };
}

function validateDna(bundle) {
  const result = spawnSync(process.execPath, [
    join(repositoryRoot, 'website-delivery-swarm/scripts/validate-build-dna.mjs'),
    join(bundle, 'build-dna.json'),
    repositoryRoot,
  ], { cwd: repositoryRoot, encoding: 'utf8' });
  if (result.status !== 0) fail('Build DNA validator failed: ' + (result.stderr || result.stdout).trim());
  return result.stdout.trim().split('\n');
}

function hub(lead, summary) {
  const cards = DIRECTIONS.map(function (direction) {
    return '<article><a href="' + direction.id + '/index.html"><img src="' + direction.id + '/thumbnail.png" alt="Abstract concept thumbnail for ' + escapeHtml(direction.name) + '"></a><div><p>' + direction.band.toUpperCase() + ' · LEVEL ' + direction.level + '/10</p><h2>' + escapeHtml(direction.name) + '</h2><a href="' + direction.id + '/index.html">Open complete direction →</a></div></article>';
  }).join('');
  return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title>Three private proof directions — ' + escapeHtml(lead.business_name) + '</title><style>*{box-sizing:border-box}body{margin:0;background:#080a08;color:#fff;font-family:Arial,sans-serif}.hero{padding:78px 5vw 56px;background:radial-gradient(circle at 85% 0,#5c8131,#080a08 54%)}.hero p{max-width:730px;color:#d3d9d3;line-height:1.65}.hero h1{font-size:clamp(56px,10vw,142px);line-height:.74;letter-spacing:-.09em;margin:20px 0}.badges{display:flex;flex-wrap:wrap;gap:9px}.badges span{border:1px solid #ffffff44;border-radius:99px;padding:9px 13px;font-size:11px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;padding:36px 5vw 80px}.grid article{background:#141814;border:1px solid #ffffff27}.grid img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover}.grid div{padding:24px}.grid p{font-size:10px;font-weight:900;letter-spacing:.13em;color:#c7ff31}.grid h2{font-size:29px;line-height:.92;margin:10px 0 30px}.grid a{color:#c7ff31;font-weight:800}.gate{padding:38px 5vw;background:#c7ff31;color:#0c1308;font-weight:700;line-height:1.55}@media(max-width:760px){.grid{grid-template-columns:1fr}.hero{padding-top:54px}}</style></head><body><header class="hero"><p>PRIVATE OWNER REVIEW · SOURCE-BACKED PREPARATION ONLY</p><h1>Three ways<br>to begin.</h1><p>Each card opens a complete responsive concept for ' + escapeHtml(lead.business_name) + '. Compare visual systems, not just colors. Actual business facts, original artwork, browser QA, independent visual review, and owner delivery approval are still required.</p><div class="badges"><span>3 directions</span><span>Safe / Medium / Ultra</span><span>No email sent</span><span>No live booking</span></div></header><main class="grid">' + cards + '</main><footer class="gate">This bundle is structurally compatible with the existing promotion contract. It remains customer-delivery blocked until every open gate in promotion-readiness.json is closed with real evidence.</footer></body></html>';
}

function leadBundle(lead, output, sourceHash, revision, createdAt, packageProfile) {
  const folder = slug(lead.business_name) + '-' + lead.fingerprint.slice(0, 8);
  const bundle = join(output, folder);
  mkdirSync(bundle, { recursive: true });
  const jobId = 'local-' + sha256(lead.campaign_id + ':' + lead.lead_id + ':' + sourceHash).slice(0, 32);
  const eventId = 'beauty-proof:' + lead.campaign_id + ':' + lead.fingerprint.slice(0, 16);
  const paths = [];
  const intakePath = join(bundle, 'intake-redacted.json');
  const researchPath = join(bundle, 'research.json');
  writeJson(intakePath, {
    schema: 'famtastic.beauty-proof-lead-redacted.v1',
    campaign_id: lead.campaign_id,
    source_lead_id: lead.lead_id,
    contact_reference: lead.contact_reference,
    business_name: lead.business_name,
    category: lead.category,
    location: lead.location,
    public_profile_url: lead.public_profile_url || null,
    observed_service_terms: lead.observed_service_terms,
    design_clues: lead.design_clues,
    source_input_fingerprint: lead.fingerprint,
  });
  writeJson(researchPath, {
    schema: 'famtastic.research.v1',
    scope: 'Operator-mapped public evidence only. This builder did not browse or infer extra facts.',
    source_lead_id: lead.lead_id,
    business_name: lead.business_name,
    research_teaser: lead.research_teaser,
    verified_facts: lead.verified_facts,
    source_urls: [...new Set(lead.verified_facts.map(function (fact) { return fact.source_url; }).concat(lead.research_teaser.source_urls))],
    unknowns_requiring_client_confirmation: [
      'Current services, prices, service area, booking availability, policies, credentials, contact channels, and portfolio rights',
      'Brand ownership, approved logo use, original image permissions, testimonials, and integrations',
      'Whether appointment, ecommerce, portal, or other backend capability is required',
    ],
    prohibited_assumptions: [
      'No invented service, price, availability, credential, policy, address, booking system, affiliation, testimonial, or result',
      'No business inference beyond the operator-mapped source evidence',
    ],
  });
  paths.push({ role: 'redacted-intake', path: intakePath, retention: 'restricted-local' });
  paths.push({ role: 'research-evidence', path: researchPath, retention: 'restricted-local' });
  const promptRecords = [];
  const directionSummary = {};
  for (const direction of DIRECTIONS) {
    const directory = join(bundle, direction.id);
    mkdirSync(directory, { recursive: true });
    const colors = palette(lead, direction);
    const htmlPath = join(directory, 'index.html');
    const thumbPath = join(directory, 'thumbnail.png');
    const designPath = join(directory, 'design-dna.json');
    const promptPath = join(directory, 'gemini-flash-lite-image-prompt.txt');
    const prompt = [
      'Create one original 16:9 hero artwork for a private FAMtastic Designs website proof.',
      'Business context: ' + lead.business_name + '. Category supplied by mapped source: ' + lead.category + '.',
      'Creative direction: ' + direction.name + '. ' + direction.art + '.',
      'Use ' + motif(lead) + ' as abstract visual language, not a copied logo or trademark. Palette family: ' + colors.ink + ', ' + colors.accent + ', ' + colors.soft + '.',
      'Reserve readable negative space for website copy. Create one coherent editorial scene, not a collage or UI mockup.',
      'Do not include text, letters, watermarks, logos, copyrighted characters, official marks, copied social screenshots, or recognizable real people unless rights-cleared reference is supplied in a later approved stage.',
      'Avoid anatomy errors, duplicate fingers, distorted hands, price boards, availability claims, before-and-after claims, and generic stock-photo poses.',
      'Output: one original 1K landscape PNG or JPEG. Preview only; final production use requires rights and quality review.',
    ].join('\n');
    writeFileSync(htmlPath, page(lead, direction, colors));
    thumbnail(thumbPath, colors, direction, lead.fingerprint);
    writeFileSync(promptPath, prompt + '\n');
    writeJson(designPath, {
      schema: 'famtastic.direction-dna.v1',
      direction_id: direction.id,
      direction_name: direction.name,
      famtastic_level: direction.level,
      creative_band: direction.band,
      layout_family: direction.family,
      palette: colors,
      motif: motif(lead),
      business_specificity: {
        source_lead_id: lead.lead_id,
        verified_fact_count: lead.verified_facts.length,
        research_teaser_source_count: lead.research_teaser.source_urls.length,
        contact_data_excluded_from_page: true,
      },
      visual_asset: {
        provider_route: 'gemini-developer-api',
        model: 'gemini-3.1-flash-lite-image',
        status: 'planned_not_executed',
        prompt_artifact: direction.id + '/gemini-flash-lite-image-prompt.txt',
        expected_asset_path: direction.id + '/assets/hero.png',
      },
      proof_boundary: 'No service, booking, pricing, policy, credential, or availability claim was generated by the template.',
    });
    paths.push({ role: 'proof-page-' + direction.id, path: htmlPath });
    paths.push({ role: 'proof-thumbnail-' + direction.id, path: thumbPath });
    paths.push({ role: 'direction-dna-' + direction.id, path: designPath });
    paths.push({ role: 'image-prompt-' + direction.id, path: promptPath, retention: 'restricted-local' });
    directionSummary[direction.id] = {
      name: direction.name,
      creative_band: direction.band,
      famtastic_level: direction.level,
      index: direction.id + '/index.html',
      thumbnail: direction.id + '/thumbnail.png',
      design_dna: direction.id + '/design-dna.json',
      prompt: direction.id + '/gemini-flash-lite-image-prompt.txt',
    };
    promptRecords.push({
      direction_id: direction.id,
      direction_name: direction.name,
      capability: 'original-preview-art',
      provider: 'gemini-developer-api',
      model: 'gemini-3.1-flash-lite-image',
      execution_status: 'planned_not_executed',
      output_path: direction.id + '/assets/hero.png',
      prompt_path: direction.id + '/gemini-flash-lite-image-prompt.txt',
      prompt,
      cost_status: 'not_incurred',
    });
  }
  const promptManifestPath = join(bundle, 'image-prompts.json');
  writeJson(promptManifestPath, {
    schema: 'famtastic.image-prompt-manifest.v1',
    provider_route: 'gemini-developer-api',
    model: 'gemini-3.1-flash-lite-image',
    execution_status: 'planned_not_executed',
    prompts: promptRecords,
  });
  paths.push({ role: 'image-prompt-manifest', path: promptManifestPath, retention: 'restricted-local' });
  const manifestPath = join(bundle, 'manifest.json');
  writeJson(manifestPath, {
    campaign_id: lead.campaign_id,
    job_id: jobId,
    event_id: eventId,
    provider: 'famtastic-local-beauty-proof-cohort',
    agent_name: 'beauty-hair-braiding-proof-builder',
    flow_key: 'website_proof.generate.v1',
    task_key: 'proof.generate',
    prompt_snapshot: 'Exact unexecuted Gemini Flash Lite prompts live in image-prompts.json. This local preparation manifest is not a customer-delivery receipt.',
    input_snapshot: {
      source_lead_id: lead.lead_id,
      contact_reference: lead.contact_reference,
      input_fingerprint: lead.fingerprint,
      direction_ids: ['a', 'b', 'c'],
      package_profile: packageProfile,
      public_profile_url_recorded: Boolean(lead.public_profile_url),
    },
    source_sha: revision === 'unavailable' ? '' : revision,
  });
  paths.push({ role: 'promotion-manifest', path: manifestPath, retention: 'restricted-local' });
  const report = qa(bundle, lead);
  const reportPath = join(bundle, 'quality-report.json');
  writeJson(reportPath, report);
  paths.push({ role: 'static-qa-report', path: reportPath, retention: 'restricted-local' });
  const readinessPath = join(bundle, 'promotion-readiness.json');
  writeJson(readinessPath, {
    schema: 'famtastic.proof-promotion-readiness.v1',
    structural_callback_contract: 'passed',
    existing_promotion_script: 'scripts/promote-local-proof-godaddy.sh',
    eligible_for_promotion_command_dry_run: true,
    customer_delivery_ready: false,
    current_artwork: 'self-contained CSS/SVG fallback only',
    required_before_customer_delivery: [
      'Gemini Flash Lite Image preflight, execution receipt, cost status, and original art embedding',
      'Playwright desktop and 390px browser QA with screenshots',
      'Independent visual review plus any required repair',
      'Canonical Drupal campaign/prospect/job mapping, Build DNA registration, owner approval, and transactional outbox record',
    ],
    safe_promotion_command: './scripts/promote-local-proof-godaddy.sh ' + repoRelative(bundle) + ' --directions=a,b,c',
    forbidden_actions_performed: [],
  });
  paths.push({ role: 'promotion-readiness', path: readinessPath, retention: 'restricted-local' });
  const hubPath = join(bundle, 'index.html');
  writeFileSync(hubPath, hub(lead, directionSummary));
  paths.push({ role: 'owner-review-hub', path: hubPath });
  const runReportPath = join(bundle, 'run-report.md');
  writeFileSync(runReportPath, '# Local Beauty / Hair / Braiding proof preparation\n\n- Business: ' + lead.business_name + '\n- Lead reference: ' + lead.lead_id + '\n- Contact reference: ' + lead.contact_reference + '\n- Campaign: ' + lead.campaign_id + '\n- Proof mix: Safe (a), Medium FAMtastic (b), Ultra FAMtastic (c)\n- Status: local preparation only; no provider call, Drupal write, publication, or email.\n- Review hub: index.html\n');
  paths.push({ role: 'run-report', path: runReportPath, retention: 'restricted-local' });
  const dnaPath = join(bundle, 'build-dna.json');
  writeJson(dnaPath, dna(lead, bundle, revision, createdAt, sourceHash, paths));
  return {
    slug: folder,
    business_name: lead.business_name,
    source_lead_id: lead.lead_id,
    contact_reference: lead.contact_reference,
    bundle: repoRelative(bundle),
    manifest: repoRelative(manifestPath),
    build_dna: repoRelative(dnaPath),
    review_hub: repoRelative(hubPath),
    quality_report: repoRelative(reportPath),
    promotion_readiness: repoRelative(readinessPath),
    build_dna_validation: validateDna(bundle),
  };
}

function main() {
  const args = options(process.argv.slice(2));
  const inputPath = resolve(args.input);
  const output = resolve(args.output);
  if (!existsSync(inputPath)) fail('Input file does not exist.');
  repoRelative(output);
  if (existsSync(output)) fail('Output directory exists. Choose a fresh run directory; never overwrite proof artifacts.');
  const loaded = inputFile(inputPath);
  const input = normalizeInput(loaded.value);
  if (input.leads.length < args.limit) fail('Input has ' + input.leads.length + ' lead(s), but --limit ' + args.limit + ' was requested.');
  const selected = input.leads.slice(0, args.limit);
  const sourceHash = sha256(loaded.raw);
  const revision = gitRevision();
  const createdAt = new Date().toISOString();
  mkdirSync(output, { recursive: true });
  const bundles = selected.map(function (lead) {
    return leadBundle({ ...lead, campaign_id: input.campaign_id }, output, sourceHash, revision, createdAt, input.package_profile);
  });
  const cohortPath = join(output, 'cohort-manifest.json');
  writeJson(cohortPath, {
    schema: OUTPUT_SCHEMA,
    classification: 'local-preparation-only',
    generated_at: createdAt,
    campaign_id: input.campaign_id,
    cohort_label: input.cohort_label,
    source: input.source,
    package_profile: input.package_profile,
    selected_count: bundles.length,
    source_input_sha256: sourceHash,
    contact_data_policy: 'Raw contact email is accepted only to compute a one-way contact_reference. It is not written to proof pages, manifests, Build DNA, or reports.',
    no_external_actions: ['no spreadsheet mutation', 'no provider/model call', 'no Drupal write', 'no proof promotion', 'no email send'],
    bundles,
  });
  console.log('PASS: prepared ' + bundles.length + ' Beauty / Hair / Braiding proof bundle(s)');
  console.log('Output: ' + repoRelative(output));
  console.log('Cohort manifest: ' + repoRelative(cohortPath));
  console.log('Entries: ' + readdirSync(output).sort().join(', '));
  console.log('Status: local preparation only; customer delivery remains blocked by recorded gates.');
}

try {
  main();
} catch (error) {
  console.error('FAIL: ' + error.message);
  process.exit(1);
}
