#!/usr/bin/env node

import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const baseJob = {
  schema_version: 'famtastic.creative-job.v1',
  job_id: 'video-route-comparison',
  status: 'planned',
  human_brief: 'Human-provided test brief: explain a topic with a clear visual outcome.',
  input_sources: [
    { id: 'brief', kind: 'brief', location_or_hash: 'test://brief' },
    { id: 'guide', kind: 'brand_guide', location_or_hash: 'test://brand-guide' },
    { id: 'prior-still', kind: 'asset_node', location_or_hash: 'sha256:test-asset' }
  ],
  requested_output: { family: 'video', purpose: 'Test provider-neutral video-story comparison.', destination: '9:16' },
  experiment: {
    mode: 'evidence_first',
    budget_usd: 25,
    candidates: [
      { id: 'premium', class: 'premium', capability: 'premium_video' },
      { id: 'cheap-a', class: 'cheap', capability: 'low_cost_video' },
      { id: 'cheap-b', class: 'local', capability: 'deterministic_composition' }
    ],
    qa_dimensions: ['topic illustration', 'continuity', 'claim safety', 'cost']
  },
  authority: { spend_authorized: true, upload_authorized: false, publish_authorized: false },
  visual_treatment: { origin: 'human_brief', status: 'not_requested' }
};

const root = await mkdtemp(join(tmpdir(), 'famtastic-asset-graph-'));
const validPath = join(root, 'valid.json');
const invalidPath = join(root, 'invalid.json');
const overBudgetPath = join(root, 'over-budget.json');
const nodePaths = ['premium', 'cheap-a', 'cheap-b', 'composition'].map((id) => join(root, `${id}.json`));
const reportPath = join(root, 'report.json');
await writeFile(validPath, `${JSON.stringify(baseJob)}\n`);
await writeFile(invalidPath, `${JSON.stringify({ ...baseJob, human_brief: '', experiment: { ...baseJob.experiment, candidates: baseJob.experiment.candidates.slice(0, 2) } })}\n`);
await writeFile(overBudgetPath, `${JSON.stringify({ ...baseJob, experiment: { ...baseJob.experiment, budget_usd: 25.01 } })}\n`);

const hash = (letter) => letter.repeat(64);
const node = (nodeId, inputNodeIds, status = 'accepted') => ({
  schema_version: 'famtastic.asset-node.v1', job_id: baseJob.job_id, node_id: nodeId,
  kind: nodeId === 'composition' ? 'composition' : 'motion_clip', status,
  input_source_ids: inputNodeIds.length ? [] : ['brief'], input_node_ids: inputNodeIds,
  execution: { kind: nodeId === 'composition' ? 'deterministic_assembly' : 'provider_generation', provider: nodeId === 'composition' ? 'hyperframes' : 'candidate-provider', model_or_tool: 'test-tool', input_or_prompt_hash: hash('a'), cost_usd: ({ premium: 15, 'cheap-a': 2, 'cheap-b': 2, composition: 0 })[nodeId], cost_or_credits: 'test' },
  output: { location: `test://${nodeId}`, sha256: hash({ premium: 'a', 'cheap-a': 'b', 'cheap-b': 'c', composition: 'd' }[nodeId]) }, qa: { decision: status === 'accepted' ? 'accepted' : 'rejected', notes: 'test' }
});
const nodes = [node('premium', []), node('cheap-a', []), node('cheap-b', []), node('composition', ['premium', 'cheap-a'])];
for (const [index, path] of nodePaths.entries()) await writeFile(path, `${JSON.stringify(nodes[index])}\n`);
await writeFile(reportPath, `${JSON.stringify({ schema_version: 'famtastic.creative-experiment-report.v1', job_id: baseJob.job_id, premium_baseline_node_id: 'premium', cheap_candidate_node_ids: ['cheap-a', 'cheap-b'], qa_results: nodes.slice(0, 3).map((item) => ({ node_id: item.node_id, dimensions: { topic_illustration: 'pass' }, decision: 'accepted' })), human_decision: 'keep_experimental' })}\n`);

const validator = new URL('./validate-creative-job.mjs', import.meta.url);
const graphValidator = new URL('./validate-asset-graph.mjs', import.meta.url);
const spendPreflight = new URL('./preflight-asset-spend.mjs', import.meta.url);
const valid = spawnSync(process.execPath, [validator.pathname, validPath, '--require-run-ready'], { encoding: 'utf8' });
const invalid = spawnSync(process.execPath, [validator.pathname, invalidPath], { encoding: 'utf8' });
const overBudget = spawnSync(process.execPath, [validator.pathname, overBudgetPath, '--require-run-ready'], { encoding: 'utf8' });
const graph = spawnSync(process.execPath, [graphValidator.pathname, validPath, ...nodePaths, '--report', reportPath], { encoding: 'utf8' });
const spendClear = spawnSync(process.execPath, [spendPreflight.pathname, validPath, ...nodePaths, '--next-cost-usd', '6'], { encoding: 'utf8' });
const spendBlocked = spawnSync(process.execPath, [spendPreflight.pathname, validPath, ...nodePaths, '--next-cost-usd', '7'], { encoding: 'utf8' });
await rm(root, { recursive: true, force: true });

if (valid.status !== 0 || invalid.status === 0 || overBudget.status === 0 || graph.status !== 0 || spendClear.status !== 0 || spendBlocked.status === 0 || !invalid.stderr.includes('human_brief') || !invalid.stderr.includes('at least two cheap') || !overBudget.stderr.includes('at or below USD 25') || !spendBlocked.stderr.includes('BLOCKED')) {
  console.error(valid.stdout + valid.stderr + invalid.stdout + invalid.stderr + overBudget.stdout + overBudget.stderr + graph.stdout + graph.stderr + spendClear.stdout + spendClear.stderr + spendBlocked.stdout + spendBlocked.stderr);
  process.exit(1);
}
console.log('asset graph tests: comparison, budget preflight, source-only rejection, and multi-node lineage all passed');
