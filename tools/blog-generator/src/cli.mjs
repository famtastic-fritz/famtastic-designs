#!/usr/bin/env node
/**
 * famtastic-blog-generator
 *
 *   sources   pull services/packages/FAQs/case studies from Drupal → sources.json
 *   plan      propose grounded topics                              → plan.json
 *   draft     write one Markdown draft per planned post            → drafts/
 *
 * Drafts are files. Nothing here publishes to the live site.
 */

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { createClient, MODEL } from './claude.mjs';
import { collectSources, describeSources, loadSources, renderSources, saveSources } from './sources.mjs';
import { generatePlan } from './plan.mjs';
import { generateDraft } from './draft.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SOURCES_PATH = join(ROOT, 'sources.json');
const PLAN_PATH = join(ROOT, 'plan.json');
const DRAFTS_DIR = join(ROOT, 'drafts');
const STYLE_PATH = join(ROOT, 'house-style.md');

function flag(name, fallback = null) {
  const hit = process.argv.find((arg) => arg.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
}

/**
 * The cached prefix: house style first (rarely changes), corpus second.
 * Both are stable within a run, so every call after the first reads them back
 * from cache instead of re-processing them.
 */
async function buildCachedSystem() {
  const [style, sources] = await Promise.all([
    readFile(STYLE_PATH, 'utf8'),
    loadSources(SOURCES_PATH).catch(() => {
      throw new Error(`No sources.json — run \`npm run sources\` first.`);
    }),
  ]);

  const system = [
    'You write for FAMtastic Designs, a web design studio serving small businesses.',
    'Everything you assert about the studio must come from the reference material below.',
    '',
    '# House style',
    '',
    style.trim(),
    '',
    '# Reference material',
    '',
    renderSources(sources),
  ].join('\n');

  return { system, sources };
}

function reportUsage(label, usage) {
  const cached = usage.cacheRead > 0 ? `, ${usage.cacheRead} cached` : '';
  console.log(`  ${label}: ${usage.input} in${cached}, ${usage.output} out`);
}

async function cmdSources() {
  const base = flag('drupal', process.env.DRUPAL_BASE_URL ?? 'http://localhost:8080');
  console.log(`Reading content from ${base} …`);

  const sources = await collectSources(base);
  await saveSources(SOURCES_PATH, sources);

  console.log(`Wrote sources.json — ${describeSources(sources)}`);
  if (sources.faqs.length === 0 && sources.services.length === 0) {
    console.warn('\nWarning: the corpus is empty. Posts grounded in nothing will read like');
    console.warn('every other web-design blog. Seed the site content first.');
  }
}

async function cmdPlan() {
  const count = Number(flag('count', '6'));
  const { system, sources } = await buildCachedSystem();
  console.log(`Planning ${count} posts from ${describeSources(sources)} (${MODEL}) …`);

  const client = createClient();
  const { posts, usage } = await generatePlan(client, { cachedSystem: system, count });
  await writeFile(PLAN_PATH, `${JSON.stringify({ posts }, null, 2)}\n`);

  console.log(`\nWrote plan.json — ${posts.length} posts:\n`);
  posts.forEach((post, i) => {
    console.log(`  ${i + 1}. ${post.title}`);
    console.log(`     query: ${post.targetQuery}`);
    console.log(`     links: ${post.internalLinks.join(', ')}\n`);
  });
  reportUsage('tokens', usage);
  console.log('\nEdit or delete entries in plan.json, then run `npm run draft`.');
}

async function cmdDraft() {
  const only = flag('slug');
  const { system } = await buildCachedSystem();
  const plan = JSON.parse(
    await readFile(PLAN_PATH, 'utf8').catch(() => {
      throw new Error('No plan.json — run `npm run plan` first.');
    }),
  );

  const queue = only ? plan.posts.filter((post) => post.slug === only) : plan.posts;
  if (queue.length === 0) throw new Error(only ? `No planned post with slug "${only}".` : 'plan.json has no posts.');

  await mkdir(DRAFTS_DIR, { recursive: true });
  const client = createClient();
  const flagged = [];

  for (const [i, post] of queue.entries()) {
    console.log(`\n[${i + 1}/${queue.length}] ${post.title}`);
    const { review, usage, file } = await generateDraft(client, { cachedSystem: system, post });
    const path = join(DRAFTS_DIR, `${post.slug}.md`);
    await writeFile(path, file);

    console.log(`  wrote drafts/${post.slug}.md (${review.words} words)`);
    reportUsage('tokens', usage);
    if (review.problems.length) {
      flagged.push({ slug: post.slug, problems: review.problems });
      review.problems.forEach((problem) => console.log(`  ⚠ ${problem}`));
    }
  }

  console.log(`\n${queue.length} draft(s) in tools/blog-generator/drafts/.`);
  if (flagged.length) {
    console.log(`${flagged.length} need attention before publishing:`);
    flagged.forEach((entry) => console.log(`  ${entry.slug}: ${entry.problems.join('; ')}`));
  }
  console.log('\nRead every draft before it goes near the site.');
}

const COMMANDS = { sources: cmdSources, plan: cmdPlan, draft: cmdDraft };

const command = process.argv[2];
if (!COMMANDS[command]) {
  console.error(`Usage: node src/cli.mjs <sources|plan|draft> [options]

  sources [--drupal=URL]     read site content into sources.json
  plan    [--count=N]        propose N topics into plan.json (default 6)
  draft   [--slug=NAME]      draft every planned post, or just one
`);
  process.exit(1);
}

COMMANDS[command]().catch((err) => {
  console.error(`\nError: ${err.message}`);
  process.exit(1);
});
