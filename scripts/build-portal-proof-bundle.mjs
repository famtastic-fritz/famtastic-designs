#!/usr/bin/env node
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';

const [artifactArg, outputArg, campaignId, jobId, eventId, directionArg] = process.argv.slice(2);
if (!artifactArg || !outputArg || !campaignId || !jobId || !eventId || !directionArg) {
  console.error('Usage: build-portal-proof-bundle.mjs <artifact-dir> <output-dir> <campaign-id> <job-id> <event-id> <a,b,c|d,e,f>');
  process.exit(2);
}
const artifact = resolve(artifactArg);
const output = resolve(outputArg);
const manifest = JSON.parse(readFileSync(join(artifact, 'manifest.json'), 'utf8'));
const requested = directionArg.split(',');
if (requested.join(',') !== 'a,b,c' && requested.join(',') !== 'd,e,f') throw new Error('Directions must be a,b,c or d,e,f.');
if (manifest.directions?.length !== 6) throw new Error('Six-direction source manifest required.');
if (existsSync(output)) throw new Error(`Output already exists: ${output}`);
mkdirSync(output, { recursive: true });
const scratch = mkdtempSync(join(tmpdir(), 'famtastic-portal-bundle-'));

function convert(source, destination, width, quality) {
  const result = spawnSync('sips', ['--resampleWidth', String(width), '-s', 'format', 'jpeg', '-s', 'formatOptions', String(quality), source, '--out', destination], { encoding: 'utf8' });
  if (result.status !== 0) throw new Error(result.stderr || `Could not convert ${source}`);
}

try {
  for (const letter of requested) {
    const sourceDirection = manifest.directions.find((item) => item.id === `direction-${letter}`);
    if (!sourceDirection) throw new Error(`Missing direction-${letter}`);
    const sourceHtmlPath = join(artifact, sourceDirection.entry);
    const sourceHeroPath = join(artifact, sourceDirection.entry.replace(/index\.html$/, 'assets/hero.png'));
    const sourceScreenshot = join(artifact, 'screenshots', `direction-${letter}-desktop.png`);
    for (const path of [sourceHtmlPath, sourceHeroPath, sourceScreenshot]) if (!existsSync(path)) throw new Error(`Missing ${path}`);
    const directionDirectory = join(output, letter);
    mkdirSync(directionDirectory);
    let html = readFileSync(sourceHtmlPath, 'utf8');
    let finalHtml = '';
    for (const [width, quality] of [[1400, 72], [1200, 68], [1000, 62], [900, 56]]) {
      const compressed = join(scratch, `${letter}-${width}-${quality}.jpg`);
      convert(sourceHeroPath, compressed, width, quality);
      const dataUri = `data:image/jpeg;base64,${readFileSync(compressed).toString('base64')}`;
      const candidate = html.replaceAll('assets/hero.png', dataUri);
      if (Buffer.byteLength(candidate) <= 490000) { finalHtml = candidate; break; }
    }
    if (!finalHtml) throw new Error(`Could not fit direction ${letter} under callback HTML limit.`);
    if (/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i.test(finalHtml)) throw new Error(`Active content found in direction ${letter}.`);
    writeFileSync(join(directionDirectory, 'index.html'), finalHtml);
    convert(sourceScreenshot, join(directionDirectory, 'thumbnail.jpg'), 900, 72);
    writeFileSync(join(directionDirectory, 'design-dna.json'), JSON.stringify({
      direction_name: sourceDirection.name,
      concept_name: sourceDirection.name,
      source_direction: sourceDirection.id,
      famtastic_level: sourceDirection.famtastic_level,
      creative_band: sourceDirection.mode,
      strategy: sourceDirection.strategy,
      palette: sourceDirection.palette,
      information_architecture: sourceDirection.information_architecture,
      source_manifest: basename(artifact),
    }, null, 2) + '\n');
  }
  writeFileSync(join(output, 'manifest.json'), JSON.stringify({
    campaign_id: campaignId,
    job_id: jobId,
    event_id: eventId,
    provider: 'openai-codex-swarm',
    agent_name: 'famtastic-six-direction-benchmark',
    flow_key: 'website_proof.generate.v1',
    proof_phase: requested[0] === 'a' ? 'initial' : 'showcase',
    task_key: requested[0] === 'a' ? 'proof.generate' : 'proof.showcase.generate',
    input_snapshot: { source_request_id: manifest.request_id, directions: requested },
    source_sha: '',
  }, null, 2) + '\n');
  console.log(`PASS: portal callback bundle ${requested.join(',')} built at ${output}`);
}
finally {
  rmSync(scratch, { recursive: true, force: true });
}
