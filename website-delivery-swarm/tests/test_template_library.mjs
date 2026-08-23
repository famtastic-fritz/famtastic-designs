#!/usr/bin/env node
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { archiveTemplateIdeas } from '../library/archive-template-ideas.mjs';

const root = mkdtempSync(join(tmpdir(), 'famtastic-template-library-'));
try {
  const source = join(root, 'source');
  const output = join(root, 'output');
  mkdirSync(join(source, 'safe'), { recursive: true });
  mkdirSync(join(source, 'ultra'), { recursive: true });
  writeFileSync(join(source, 'safe', 'index.html'), '<h1>Safe</h1>');
  writeFileSync(join(source, 'ultra', 'index.html'), '<h1>Ultra</h1>');
  writeFileSync(join(source, 'manifest.json'), JSON.stringify({
    schema: 'famtastic.website-showcase-manifest.v1',
    request_id: 'private-request-123',
    customer_email: 'customer@example.test',
    fictional_business: false,
    classification: 'locally proven',
    direction_count: 2,
    selected_direction: 'direction-a',
    directions: [
      { id: 'direction-a', slug: 'safe', name: 'Safe', mode: 'normal', famtastic_level: 2, strategy: 'Clear', palette: 'neutral', information_architecture: 'hero -> services', entry: 'safe/index.html', html_sha256: 'aaa', hero_sha256: 'bbb' },
      { id: 'direction-b', slug: 'ultra', name: 'Ultra', mode: 'famtastic', famtastic_level: 10, strategy: 'Bold', palette: 'electric', information_architecture: 'portal -> worlds', entry: 'ultra/index.html', html_sha256: 'ccc', hero_sha256: 'ddd' },
    ],
  }));
  const result = archiveTemplateIdeas({ outputDirectory: output, sourceRoots: [source], repositoryRoot: root });
  assert.equal(result.summary.packages_preserved, 1);
  assert.equal(result.summary.template_candidates, 2);
  assert.equal(result.summary.unselected_candidates, 1);
  assert.equal(result.summary.selected_client_directions, 1);
  assert.equal(result.summary.all_candidates_private, true);
  assert.equal(result.summary.all_customer_assets_blocked, true);
  assert.equal(result.library.candidates.find((item) => item.source_direction === 'direction-a').reuse_state, 'client_work_only');
  assert.equal(result.library.candidates.find((item) => item.source_direction === 'direction-b').reuse_state, 'internal_only');
  const serialized = readFileSync(join(output, 'template-candidates.json'), 'utf8');
  assert.equal(serialized.includes('customer@example.test'), false);
  assert.equal(serialized.includes('private-request-123'), false);
  console.log('PASS: proof preservation and de-identified template library policy');
}
finally {
  rmSync(root, { recursive: true, force: true });
}
