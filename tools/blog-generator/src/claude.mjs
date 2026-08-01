/**
 * Claude API layer.
 *
 * One model call shape is used for both commands. The grounding corpus and
 * house style go in the system prompt behind a cache breakpoint, so a run that
 * drafts six posts pays for that prefix once and reads it back on the other
 * five. The per-post instruction is the only part that varies.
 */

import Anthropic from '@anthropic-ai/sdk';

const MODEL = 'claude-opus-5';

/**
 * Server-side refusal fallback. A policy decline on a blog draft is unlikely,
 * but without this the request simply stops and the run loses a post; with it
 * the API re-serves the same request on Anthropic's recommended fallback.
 */
const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

export function createClient() {
  if (!process.env.ANTHROPIC_API_KEY && !process.env.ANTHROPIC_AUTH_TOKEN) {
    throw new Error(
      'No Claude credentials found. Set ANTHROPIC_API_KEY, or run `ant auth login` ' +
        'so the SDK can pick up a stored profile.',
    );
  }
  return new Anthropic();
}

/**
 * @param {object}   opts
 * @param {string}   opts.cachedSystem  Stable prefix (house style + corpus). Cached.
 * @param {string}   opts.instruction   The varying per-call request.
 * @param {object}   [opts.schema]      JSON Schema; when present the reply is parsed JSON.
 * @param {number}   [opts.maxTokens]
 */
export async function callModel(client, { cachedSystem, instruction, schema, maxTokens = 16000 }) {
  const request = {
    model: MODEL,
    max_tokens: maxTokens,
    betas: [FALLBACK_BETA],
    fallbacks: 'default',
    output_config: { effort: 'high' },
    system: [
      // cache_control sits on the last stable block, so tools+system cache
      // together and only `instruction` differs between calls.
      { type: 'text', text: cachedSystem, cache_control: { type: 'ephemeral' } },
    ],
    messages: [{ role: 'user', content: instruction }],
  };

  if (schema) {
    request.output_config = { ...request.output_config, format: { type: 'json_schema', schema } };
  }

  const response = await client.beta.messages.create(request);

  // Check the stop reason before touching content: a refusal returns HTTP 200
  // with content empty or partial, so indexing content[0] would throw or lie.
  if (response.stop_reason === 'refusal') {
    const category = response.stop_details?.category ?? 'unspecified';
    throw new Error(`Model declined this request (category: ${category}).`);
  }

  const text = response.content
    .filter((block) => block.type === 'text')
    .map((block) => block.text)
    .join('')
    .trim();

  if (!text) throw new Error('Model returned no text content.');

  const usage = {
    input: response.usage?.input_tokens ?? 0,
    output: response.usage?.output_tokens ?? 0,
    cacheWrite: response.usage?.cache_creation_input_tokens ?? 0,
    cacheRead: response.usage?.cache_read_input_tokens ?? 0,
  };

  if (!schema) return { text, usage };

  try {
    return { data: JSON.parse(text), usage };
  } catch (err) {
    throw new Error(`Expected JSON matching the schema, got: ${text.slice(0, 200)}…`);
  }
}

export { MODEL };
