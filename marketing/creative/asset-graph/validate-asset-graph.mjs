#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import process from 'node:process';

const [jobPath, ...rest] = process.argv.slice(2);
const reportIndex = rest.indexOf('--report');
const nodePaths = (reportIndex === -1 ? rest : rest.slice(0, reportIndex)).filter((value) => !value.startsWith('--'));
const reportPath = reportIndex === -1 ? null : rest[reportIndex + 1];

if (!jobPath || nodePaths.length === 0 || (reportIndex !== -1 && !reportPath)) {
  console.error('Usage: node validate-asset-graph.mjs <job.json> <node.json>... [--report <report.json>]');
  process.exit(2);
}

const load = async (path) => JSON.parse(await readFile(path, 'utf8'));
let job;
let nodes;
let report = null;
try {
  job = await load(jobPath);
  nodes = await Promise.all(nodePaths.map(load));
  if (reportPath) report = await load(reportPath);
} catch (error) {
  console.error(`INVALID: could not read valid JSON: ${error.message}`);
  process.exit(1);
}

const problems = [];
const sourceIds = new Set((job.input_sources ?? []).map((source) => source.id));
const ids = new Set();
let recordedCostUsd = 0;
for (const node of nodes) {
  if (node.schema_version !== 'famtastic.asset-node.v1') problems.push(`node ${node.node_id ?? '<unknown>'} has wrong schema_version`);
  if (node.job_id !== job.job_id) problems.push(`node ${node.node_id ?? '<unknown>'} belongs to another job`);
  if (!node.node_id || ids.has(node.node_id)) problems.push(`node id ${node.node_id ?? '<unknown>'} is missing or duplicated`);
  ids.add(node.node_id);
  if (!node.output?.sha256 || !/^[a-f0-9]{64}$/.test(node.output.sha256)) problems.push(`node ${node.node_id ?? '<unknown>'} needs a sha256 output hash`);
  if (typeof node.execution?.cost_usd !== 'number' || node.execution.cost_usd < 0) problems.push(`node ${node.node_id ?? '<unknown>'} needs a non-negative cost_usd`);
  else recordedCostUsd += node.execution.cost_usd;
  for (const inputSourceId of node.input_source_ids ?? []) {
    if (!sourceIds.has(inputSourceId)) problems.push(`node ${node.node_id ?? '<unknown>'} references unknown source ${inputSourceId}`);
  }
}

if (typeof job.experiment?.budget_usd === 'number' && recordedCostUsd > job.experiment.budget_usd) {
  problems.push(`recorded node cost USD ${recordedCostUsd.toFixed(2)} exceeds job budget USD ${job.experiment.budget_usd.toFixed(2)}`);
}
for (const node of nodes) {
  for (const inputNodeId of node.input_node_ids ?? []) {
    if (!ids.has(inputNodeId)) problems.push(`node ${node.node_id} references unknown input node ${inputNodeId}`);
    if (inputNodeId === node.node_id) problems.push(`node ${node.node_id} may not consume itself`);
  }
  if ((node.input_source_ids ?? []).length === 0 && (node.input_node_ids ?? []).length === 0) {
    problems.push(`node ${node.node_id} has no declared input lineage`);
  }
}

if (report) {
  if (report.schema_version !== 'famtastic.creative-experiment-report.v1') problems.push('experiment report has wrong schema_version');
  if (report.job_id !== job.job_id) problems.push('experiment report belongs to another job');
  if (!ids.has(report.premium_baseline_node_id)) problems.push('experiment report references an unknown premium baseline');
  if (!Array.isArray(report.cheap_candidate_node_ids) || report.cheap_candidate_node_ids.length < 2) problems.push('experiment report needs at least two cheap candidates');
  for (const id of report.cheap_candidate_node_ids ?? []) if (!ids.has(id)) problems.push(`experiment report references unknown cheap candidate ${id}`);
  for (const result of report.qa_results ?? []) if (!ids.has(result.node_id)) problems.push(`experiment report references unknown QA node ${result.node_id}`);
}

if (problems.length) {
  console.error('INVALID ASSET GRAPH');
  for (const problem of problems) console.error(`- ${problem}`);
  process.exit(1);
}

console.log(`VALID ASSET GRAPH: ${job.job_id}; ${nodes.length} nodes${report ? ' and comparison report' : ''}`);
