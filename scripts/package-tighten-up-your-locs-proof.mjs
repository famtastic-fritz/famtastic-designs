import { readFile, writeFile } from 'node:fs/promises';
import { basename, resolve } from 'node:path';
import { createHash } from 'node:crypto';

const root = resolve(import.meta.dirname, '..');
const proofRoot = resolve(root, 'docs/design/proofs/tighten-up-your-locs-v2');
const maxAssetBytes = 2_000_000;
const config = [
  {
    direction_id: 'a',
    direction_name: 'Care Rhythm',
    proof: 'proofs/care-rhythm/index.html',
    rationale: 'A relationship-led direction for care continuity, rebooking, and a personal return path.',
    assets: [{ asset_id: 'journey-character', source: 'assets/story/journey-character.png', output: 'journey-character.png' }],
  },
  {
    direction_id: 'b',
    direction_name: 'Appointment Desk',
    proof: 'proofs/appointment-desk/index.html',
    rationale: 'An availability-led direction that gives Shay control of request windows without calendar theater.',
    assets: [],
  },
  {
    direction_id: 'c',
    direction_name: 'Established Archive',
    proof: 'proofs/established-archive/index.html',
    rationale: 'An editorial trust direction that creates a durable home for authorized work, guidance, and client return.',
    assets: [{ asset_id: 'owner-phone-character', source: 'assets/story/owner-phone-character.png', output: 'owner-phone-character.png' }],
  },
];

const option = (name) => {
  const index = process.argv.indexOf(name);
  if (index !== -1) return String(process.argv[index + 1] || '').trim();
  const inline = process.argv.find((value) => value.startsWith(`${name}=`));
  return inline ? inline.slice(name.length + 1).trim() : '';
};

const normalizeHtml = (html, direction) => {
  let result = html;
  for (const asset of direction.assets) {
    result = result.replaceAll(`../../${asset.source}`, `assets/${asset.output}`);
  }
  result = result.replaceAll('href="../../#contact"', 'href="#proof-review"')
    .replaceAll('href="../../owner/"', 'href="#proof-review"')
    .replaceAll('href="../"', 'href="#proof-review"')
    .replaceAll('TEMPORARY REVIEW ONLY', 'PRIVATE STUDIO REVIEW')
    .replaceAll('NO DOMAIN, CALENDAR, PAYMENT, OR LIVE REQUEST ROUTE IS ACTIVE', 'CHOOSE OR REQUEST CHANGES IN YOUR FAMTASTIC WORKSPACE')
    .replaceAll('NO CALENDAR, PAYMENT, OR LIVE REQUEST ROUTE IS ACTIVE', 'CHOOSE OR REQUEST CHANGES IN YOUR FAMTASTIC WORKSPACE');
  if (/<(script|iframe|object|embed|base)\b|\son[a-z]+\s*=|javascript\s*:/i.test(result)) {
    throw new Error(`${direction.direction_name} contains active HTML that the protected proof callback will reject.`);
  }
  if (Buffer.byteLength(result) > 500_000) throw new Error(`${direction.direction_name} HTML exceeds the callback limit.`);
  return result;
};

const buildVariant = async (direction) => {
  const html = normalizeHtml(await readFile(resolve(proofRoot, direction.proof), 'utf8'), direction);
  const assets = await Promise.all(direction.assets.map(async (asset) => {
    const bytes = await readFile(resolve(proofRoot, asset.source));
    if (bytes.length > maxAssetBytes) throw new Error(`${asset.source} exceeds the 2 MB proof asset limit.`);
    return {
      asset_id: asset.asset_id,
      relative_path: asset.output,
      media_type: 'image/png',
      base64: bytes.toString('base64'),
      sha256: createHash('sha256').update(bytes).digest('hex'),
    };
  }));
  return {
    direction_id: direction.direction_id,
    html,
    assets,
    design_dna: {
      source: 'tighten-up-your-locs-private-proof-v2',
      direction_name: direction.direction_name,
      direction_rationale: direction.rationale,
      artifact_role: 'account_owned_studio_review_candidate',
    },
  };
};

const variants = await Promise.all(config.map(buildVariant));
if (process.argv.includes('--check')) {
  console.log(`PASS: ${variants.length} protected callback variants are packageable (${variants.map((variant) => variant.direction_id).join(', ')}).`);
  process.exit(0);
}

const campaignId = option('--campaign');
const jobId = option('--job');
const eventId = option('--event');
const output = option('--output');
if (!/^pc-[a-z0-9-]+$/.test(campaignId) || jobId === '' || eventId === '' || output === '') {
  throw new Error('Usage: node scripts/package-tighten-up-your-locs-proof.mjs --campaign=<exact campaign id> --job=<exact job id> --event=<unique callback event> --output=<private callback json>; use --check for read-only validation.');
}
const payload = JSON.stringify({ schema_version: 1, event_id: eventId, campaign_id: campaignId, job_id: jobId, variants }, null, 2);
if (Buffer.byteLength(payload) > 24 * 1024 * 1024) throw new Error('Callback payload exceeds the protected import limit.');
await writeFile(resolve(output), payload);
console.log(`Wrote ${basename(output)}. SHA-256: ${createHash('sha256').update(payload).digest('hex')}`);
