#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import process from 'node:process';

const args = process.argv.slice(2);
const nextIndex = args.indexOf('--next-cost-usd');
const jobPath = args[0];
const nodePaths = (nextIndex === -1 ? args.slice(1) : args.slice(1, nextIndex)).filter((value) => !value.startsWith('--'));
const nextCostUsd = nextIndex === -1 ? NaN : Number(args[nextIndex + 1]);

if (!jobPath || nextIndex === -1 || !Number.isFinite(nextCostUsd) || nextCostUsd < 0) {
  console.error('Usage: node preflight-asset-spend.mjs <job.json> [node.json ...] --next-cost-usd <non-negative number>');
  process.exit(2);
}

try {
  const job = JSON.parse(await readFile(jobPath, 'utf8'));
  const nodes = await Promise.all(nodePaths.map(async (path) => JSON.parse(await readFile(path, 'utf8'))));
  const spent = nodes.reduce((sum, node) => sum + (typeof node.execution?.cost_usd === 'number' ? node.execution.cost_usd : 0), 0);
  const budget = job.experiment?.budget_usd;
  if (typeof budget !== 'number') throw new Error('job has no numeric experiment budget');
  const projected = spent + nextCostUsd;
  if (projected > budget) {
    console.error(`BLOCKED: projected USD ${projected.toFixed(2)} exceeds job budget USD ${budget.toFixed(2)}`);
    process.exit(1);
  }
  console.log(`CLEAR: recorded USD ${spent.toFixed(2)} + next USD ${nextCostUsd.toFixed(2)} = USD ${projected.toFixed(2)} within USD ${budget.toFixed(2)}`);
} catch (error) {
  console.error(`INVALID: ${error.message}`);
  process.exit(1);
}
