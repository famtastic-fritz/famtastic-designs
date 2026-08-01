/**
 * Topic planning.
 *
 * Topics are chosen from what the business can uniquely answer — its real
 * prices, real services, and the questions its own FAQ says customers ask —
 * rather than from generic web-design subject matter. The plan is a separate,
 * cheap, reviewable step so bad topics get killed before they cost a draft.
 */

import { callModel } from './claude.mjs';

const PLAN_SCHEMA = {
  type: 'object',
  properties: {
    posts: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          slug: {
            type: 'string',
            description: 'URL slug, lowercase, hyphenated, no leading slash.',
          },
          title: { type: 'string', description: 'Post title as a reader would see it.' },
          targetQuery: {
            type: 'string',
            description: 'The single search phrase a real person would type.',
          },
          readerSituation: {
            type: 'string',
            description: 'The concrete situation the reader is in when they search this.',
          },
          angle: {
            type: 'string',
            description:
              'What this business can say that a generic article cannot, citing the specific reference material it draws on.',
          },
          mustCover: {
            type: 'array',
            items: { type: 'string' },
            description: 'Points the post has to address to be worth publishing.',
          },
          internalLinks: {
            type: 'array',
            items: { type: 'string' },
            description: 'Real site paths from the reference material, e.g. /199 or /services/seo.',
          },
        },
        required: ['slug', 'title', 'targetQuery', 'readerSituation', 'angle', 'mustCover', 'internalLinks'],
        additionalProperties: false,
      },
    },
  },
  required: ['posts'],
  additionalProperties: false,
};

const INSTRUCTION = `Propose {{COUNT}} blog posts for this studio's empty blog.

Pick topics this business can answer better than a generic article could, using
the reference material above. Strong sources of topics, roughly in order:

1. Questions the FAQ shows customers actually ask. These are proven demand — a
   question someone already asked out loud is worth a full post.
2. The pricing question, in the specific forms people search it. This studio has
   an unusually concrete answer, which most articles on the topic do not.
3. The decision a prospect is actually weighing: whether to get a site at all,
   what happens if they only have a social page, what they need to supply.
4. Services in the reference material, written as the problem they solve rather
   than as a description of the service.

Rules for the plan:

- One post per distinct search intent. Do not propose two posts that would
  answer the same question, and do not split one topic into a series.
- Every targetQuery must be something a small-business owner would plausibly
  type, in their words — not marketing vocabulary.
- Every angle must name the specific reference material it relies on. If you
  cannot ground a topic in the material above, do not propose it.
- internalLinks must use paths that appear in the reference material, plus /199.
- Order the list by how likely it is to produce an enquiry, best first.`;

export async function generatePlan(client, { cachedSystem, count }) {
  const { data, usage } = await callModel(client, {
    cachedSystem,
    instruction: INSTRUCTION.replace('{{COUNT}}', String(count)),
    schema: PLAN_SCHEMA,
    maxTokens: 16000,
  });

  if (!Array.isArray(data.posts) || data.posts.length === 0) {
    throw new Error('Planner returned no posts.');
  }

  return { posts: data.posts, usage };
}

export { PLAN_SCHEMA };
