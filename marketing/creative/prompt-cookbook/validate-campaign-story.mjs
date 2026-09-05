#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import process from 'node:process';

const storyPath = process.argv.find((argument) => !argument.startsWith('--') && argument !== process.argv[0] && argument !== process.argv[1]);
const requireRenderReady = process.argv.includes('--require-render-ready');

if (!storyPath) {
  console.error('Usage: node validate-campaign-story.mjs <story.json> [--require-render-ready]');
  process.exit(2);
}

let story;
try {
  story = JSON.parse(await readFile(storyPath, 'utf8'));
} catch (error) {
  console.error(`INVALID: could not read valid JSON from ${storyPath}: ${error.message}`);
  process.exit(1);
}

const problems = [];
const requiredTopLevel = ['schema_version', 'status', 'source', 'creative_brief', 'claim_ledger', 'scenes', 'asset_policy', 'render_gate'];
for (const field of requiredTopLevel) {
  if (!(field in story)) problems.push(`missing top-level field: ${field}`);
}
if (story.schema_version !== 'campaign-story.v1') problems.push('schema_version must be campaign-story.v1');
if (!Array.isArray(story.claim_ledger) || story.claim_ledger.length === 0) problems.push('claim_ledger must contain at least one sourced claim');
if (!Array.isArray(story.scenes) || story.scenes.length < 5) problems.push('scenes must contain at least five visual beats');

const requiredBeats = ['hook', 'friction', 'mechanism', 'turn'];
const beats = new Set(Array.isArray(story.scenes) ? story.scenes.map((scene) => scene.story_beat) : []);
for (const beat of requiredBeats) {
  if (!beats.has(beat)) problems.push(`missing required narrative beat: ${beat}`);
}

for (const [index, scene] of (story.scenes ?? []).entries()) {
  for (const field of ['id', 'range_seconds', 'story_beat', 'visual_purpose', 'visual', 'copy_or_narration', 'asset_needed', 'continuity_locks']) {
    if (!(field in scene)) problems.push(`scene ${index + 1} missing ${field}`);
  }
}

if (requireRenderReady) {
  const gate = story.render_gate ?? {};
  if (story.status !== 'approved_for_render') problems.push('render requires status approved_for_render');
  if (gate.storyboard_reviewed !== true) problems.push('render requires storyboard_reviewed: true');
  if (gate.claim_ledger_reviewed !== true) problems.push('render requires claim_ledger_reviewed: true');
  if (gate.provider_authorized !== true) problems.push('render requires provider_authorized: true');
  if (gate.status !== 'ready') problems.push('render requires render_gate.status: ready');
}

if (problems.length) {
  console.error(`INVALID: ${storyPath}`);
  for (const problem of problems) console.error(`- ${problem}`);
  process.exit(1);
}

console.log(`VALID: ${storyPath}${requireRenderReady ? ' is render-ready' : ' has a complete story structure'}`);
