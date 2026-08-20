import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const readJson = async (name) => JSON.parse(await readFile(path.join(here, name), 'utf8'));
const manifest = await readJson('manifest.json');
const formula = await readJson('formula.json');
const prompts = await readJson('prompts.json');
const imageRouting = await readJson('image-routing.json');
const publication = await readJson('evidence/live-publication.json');

const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};

const allowedStates = new Set([
  'idea', 'briefed', 'drafted', 'content_qa', 'seo_qa', 'media_ready',
  'approved', 'scheduled', 'published', 'verified', 'measured', 'learned',
  'revision_required', 'delivery_failed', 'blocked',
]);

assert(manifest.schema_version === 1, 'manifest schema_version must be 1');
assert(manifest.campaign === 'and_if_it_is_rattler_lifers', 'manifest campaign mismatch');
assert(manifest.public_publish_enabled === false, 'public publishing must default off');
assert(manifest.record_count === manifest.records.length, 'manifest record_count mismatch');
assert(manifest.records.length === 6, 'expected six launch records');

const ids = new Set();
for (const record of manifest.records) {
  assert(/^[a-z0-9][a-z0-9_-]+$/.test(record.content_id), `invalid content_id: ${record.content_id}`);
  assert(!ids.has(record.content_id), `duplicate content_id: ${record.content_id}`);
  ids.add(record.content_id);
  assert(record.campaign === manifest.campaign, `campaign mismatch: ${record.content_id}`);
  assert(allowedStates.has(record.state), `invalid state: ${record.content_id}`);
  assert(Array.isArray(record.channels), `channels missing: ${record.content_id}`);
  assert(Array.isArray(record.asset_variants), `asset variants missing: ${record.content_id}`);
  assert(record.utm?.content === record.content_id, `UTM content mismatch: ${record.content_id}`);
  assert(record.approval?.content === false, `content gate unexpectedly open: ${record.content_id}`);
  assert(record.approval?.media === false, `media gate unexpectedly open: ${record.content_id}`);
  assert(record.approval?.publish === false, `publish gate unexpectedly open: ${record.content_id}`);
  assert(record.provider_ids && typeof record.provider_ids === 'object', `provider_ids missing: ${record.content_id}`);
  assert(Array.isArray(record.evidence), `evidence missing: ${record.content_id}`);
}

assert(formula.formula_version === '1.0.0', 'formula version mismatch');
assert(formula.stages.length === 6, 'expected six lean formula stages');
assert(formula.agent_policy.new_agent_created === false, 'unexpected new agent');
assert(prompts.prompts.length === 2, 'expected exactly two image prompts');
assert(new Set(prompts.prompts.map((prompt) => prompt.prompt_id)).size === 2, 'prompt IDs must be unique');
assert(prompts.prompts.every((prompt) => prompt.model === 'gpt-image-2'), 'unexpected image model');
assert(imageRouting.proven_route.model === 'gpt-image-2', 'proven image route model mismatch');
assert(imageRouting.proven_route.transport === 'OpenArt MCP', 'proven image route transport mismatch');
assert(imageRouting.same_model_alternatives.some((route) => route.route_id === 'openai-image-api-gpt-image-2'), 'direct OpenAI Image API alternative missing');
assert(publication.access === 'public_unlisted_anyone_with_link', 'unexpected publication access');
assert(publication.boundaries.social_post_published === false, 'social post boundary unexpectedly open');

console.log('PASS campaign manifest, closed approvals, lean formula, exact-two prompts, image routing, and unlisted-publication boundaries');
