/**
 * Draft generation.
 *
 * Produces one post per plan entry as a Markdown file with front matter that
 * maps onto the blog_post content type (title, field_summary, body). Drafts are
 * written to disk and never published — a human edits and approves before
 * anything reaches the site.
 */

import { callModel } from './claude.mjs';

const DRAFT_SCHEMA = {
  type: 'object',
  properties: {
    title: { type: 'string', description: 'Final title; may refine the planned one.' },
    summary: {
      type: 'string',
      description:
        'One or two sentences for the blog card and meta description. Plain text, no markdown, under 200 characters.',
    },
    markdown: {
      type: 'string',
      description:
        'The post body in Markdown. No H1 — the title is rendered separately. Use ## for section headings.',
    },
  },
  required: ['title', 'summary', 'markdown'],
  additionalProperties: false,
};

function instructionFor(post) {
  return `Write this post.

Title (may refine): ${post.title}
Search phrase to answer: ${post.targetQuery}
Reader's situation: ${post.readerSituation}
Angle only this studio can take: ${post.angle}

Must cover:
${post.mustCover.map((point) => `- ${point}`).join('\n')}

Link naturally to these real pages, in sentences where they follow from the point
being made: ${post.internalLinks.join(', ')}

Follow the house style above exactly. Every factual claim — prices, timelines,
inclusions, services, results — must trace back to the reference material. If the
material does not support something, leave it out rather than approximating it.

Return the body only. Do not include an H1 heading, a "Conclusion" heading, or a
sign-off.`;
}

/** Quality gate — a draft that fails these needs a human before it is worth reading. */
export function checkDraft(post, draft) {
  const problems = [];
  const words = draft.markdown.split(/\s+/).filter(Boolean).length;

  if (words < 500) problems.push(`only ${words} words (expected 700–1100)`);
  if (words > 1500) problems.push(`${words} words (expected 700–1100)`);
  if (!draft.markdown.includes('/199')) problems.push('no link to the /199 offer page');
  if (/^#\s/m.test(draft.markdown)) problems.push('contains an H1 (title is rendered separately)');
  if (draft.summary.length > 200) problems.push(`summary is ${draft.summary.length} chars (max 200)`);

  const otherLinks = post.internalLinks.filter((href) => href !== '/199');
  if (otherLinks.length && !otherLinks.some((href) => draft.markdown.includes(href))) {
    problems.push('no link to a service or package page');
  }

  return { words, problems };
}

export function toMarkdownFile(post, draft, { words }) {
  const frontMatter = [
    '---',
    `title: ${JSON.stringify(draft.title)}`,
    `slug: ${JSON.stringify(post.slug)}`,
    `summary: ${JSON.stringify(draft.summary)}`,
    `target_query: ${JSON.stringify(post.targetQuery)}`,
    `internal_links: ${JSON.stringify(post.internalLinks)}`,
    `word_count: ${words}`,
    'status: draft',
    'reviewed_by: ""',
    '---',
    '',
  ].join('\n');

  return `${frontMatter}${draft.markdown.trim()}\n`;
}

export async function generateDraft(client, { cachedSystem, post }) {
  const { data, usage } = await callModel(client, {
    cachedSystem,
    instruction: instructionFor(post),
    schema: DRAFT_SCHEMA,
    maxTokens: 16000,
  });

  const review = checkDraft(post, data);
  return { draft: data, review, usage, file: toMarkdownFile(post, data, review) };
}

export { DRAFT_SCHEMA };
