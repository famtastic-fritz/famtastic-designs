#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import process from 'node:process';

const jobPath = process.argv.find((value) => !value.startsWith('--') && value !== process.argv[0] && value !== process.argv[1]);
const requireRunReady = process.argv.includes('--require-run-ready');

if (!jobPath) {
  console.error('Usage: node validate-creative-job.mjs <job.json> [--require-run-ready]');
  process.exit(2);
}

let job;
try {
  job = JSON.parse(await readFile(jobPath, 'utf8'));
} catch (error) {
  console.error(`INVALID: could not read valid JSON from ${jobPath}: ${error.message}`);
  process.exit(1);
}

const problems = [];
for (const field of ['schema_version', 'job_id', 'status', 'human_brief', 'input_sources', 'requested_output', 'experiment', 'authority']) {
  if (!(field in job)) problems.push(`missing top-level field: ${field}`);
}
if (job.schema_version !== 'famtastic.creative-job.v1') problems.push('schema_version must be famtastic.creative-job.v1');
if (!Array.isArray(job.input_sources) || job.input_sources.length === 0) problems.push('at least one declared input source is required');
if (!job.human_brief || typeof job.human_brief !== 'string') problems.push('human_brief is required; source material alone may not prescribe treatment');

const experiment = job.experiment ?? {};
const candidates = experiment.candidates ?? [];
const premium = candidates.filter((candidate) => candidate.class === 'premium');
const cheap = candidates.filter((candidate) => candidate.class === 'cheap' || candidate.class === 'local');
if (premium.length !== 1) problems.push('an evidence-first comparison needs exactly one premium baseline');
if (cheap.length < 2) problems.push('an evidence-first comparison needs at least two cheap or local candidates');
if (!Array.isArray(experiment.qa_dimensions) || experiment.qa_dimensions.length === 0) problems.push('at least one task-specific QA dimension is required');

if (job.visual_treatment && !['human_brief', 'approved_experiment'].includes(job.visual_treatment.origin)) {
  problems.push('visual_treatment must originate in a human brief or approved experiment');
}

if (requireRunReady) {
  const family = job.requested_output?.family;
  const cap = family === 'video' ? 25 : 5;
  if (job.status !== 'planned') problems.push('run requires status planned');
  if (job.authority?.spend_authorized !== true) problems.push('run requires spend_authorized: true');
  if (job.authority?.publish_authorized !== false) problems.push('publish_authorized must remain false for creative experiments');
  if (typeof experiment.budget_usd !== 'number' || experiment.budget_usd > cap) problems.push(`${family ?? 'unknown'} experiment budget must be at or below USD ${cap}`);
}

if (problems.length) {
  console.error(`INVALID: ${jobPath}`);
  for (const problem of problems) console.error(`- ${problem}`);
  process.exit(1);
}

console.log(`VALID: ${jobPath}${requireRunReady ? ' is run-ready' : ' has a complete evidence-first plan'}`);
