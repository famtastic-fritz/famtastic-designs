#!/usr/bin/env node
import { createHash } from 'node:crypto';
import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const sha256 = (value) => createHash('sha256').update(value).digest('hex');
const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'));
const posix = (path) => path.split(sep).join('/');

function walk(root, predicate, files = []) {
  if (!existsSync(root)) return files;
  for (const name of readdirSync(root).sort()) {
    const path = join(root, name);
    const stat = lstatSync(path);
    if (stat.isSymbolicLink()) continue;
    if (stat.isDirectory()) {
      if (!['node_modules', '.git', 'template-library'].includes(name)) walk(path, predicate, files);
    }
    else if (predicate(path)) files.push(path);
  }
  return files;
}

function artifactLedger(packageDirectory) {
  return walk(packageDirectory, (path) => !path.endsWith('.DS_Store')).map((path) => {
    const bytes = readFileSync(path);
    return { file: posix(relative(packageDirectory, path)), size_bytes: bytes.length, sha256: sha256(bytes) };
  });
}

function creativeBand(level, mode) {
  const numeric = Number(level);
  if (Number.isFinite(numeric)) {
    if (numeric <= 3) return 'restrained';
    if (numeric <= 7) return 'medium';
    return 'ultra';
  }
  return mode === 'normal' ? 'restrained' : 'unspecified';
}

function legacyDirections(manifest) {
  const artifactNames = Object.keys(manifest.artifacts || {}).filter((file) => /(^|\/)(safe|wild|omg)\.html$/.test(file));
  return artifactNames.map((entry) => {
    const slug = basename(entry, '.html');
    const levels = { safe: 2, wild: 7, omg: 10 };
    return {
      id: `direction-${slug}`,
      slug,
      name: slug.toUpperCase(),
      mode: slug === 'safe' ? 'normal' : 'famtastic',
      famtastic_level: levels[slug],
      strategy: 'Legacy proof preserved before the structured direction contract.',
      palette: 'legacy metadata unavailable',
      information_architecture: 'legacy metadata unavailable',
      entry,
      html_sha256: manifest.artifacts[entry],
      hero_sha256: '',
    };
  });
}

function loadPackage(manifestPath, repositoryRoot) {
  const manifest = readJson(manifestPath);
  const packageDirectory = dirname(manifestPath);
  const manifestDirections = Array.isArray(manifest.directions) ? manifest.directions : legacyDirections(manifest);
  if (!manifestDirections.length) return null;
  const sourceId = manifest.request_id || manifest.source_id || posix(relative(repositoryRoot, packageDirectory));
  const sourceFingerprint = sha256(String(sourceId));
  const fictional = manifest.fictional_business === true;
  const directionSelection = manifest.selected_direction || manifest.selected_variant || '';
  return {
    packageDirectory,
    package: {
      package_id: `proof-package-${sourceFingerprint.slice(0, 16)}`,
      source_path: posix(relative(repositoryRoot, packageDirectory)),
      source_request_fingerprint: sourceFingerprint,
      classification: manifest.classification || 'locally proven',
      fictional_business: fictional,
      direction_count: manifestDirections.length,
      retained_files: artifactLedger(packageDirectory),
    },
    entries: manifestDirections.map((direction) => {
      const selected = direction.id === directionSelection || direction.slug === directionSelection;
      const htmlHash = String(direction.html_sha256 || manifest.artifacts?.[direction.entry] || '');
      const identity = htmlHash || sha256(JSON.stringify(direction));
      return {
        template_id: `template-${identity.slice(0, 18)}`,
        source_package_id: `proof-package-${sourceFingerprint.slice(0, 16)}`,
        source_request_fingerprint: sourceFingerprint,
        source_direction: String(direction.id || direction.slug || ''),
        concept_name: String(direction.name || direction.slug || 'Untitled direction'),
        selection_state: selected ? 'selected_client_direction' : 'unselected_candidate',
        reuse_state: selected ? 'client_work_only' : 'internal_only',
        portfolio_status: 'owner_review_required',
        creative_band: creativeBand(direction.famtastic_level, direction.mode),
        famtastic_level: Number.isFinite(Number(direction.famtastic_level)) ? Number(direction.famtastic_level) : null,
        strategy: String(direction.strategy || 'Structured rationale unavailable.'),
        palette_strategy: String(direction.palette || 'Palette metadata unavailable.'),
        information_architecture: String(direction.information_architecture || 'Architecture metadata unavailable.'),
        artifact_entry: String(direction.entry || ''),
        artifact_integrity: { html_sha256: htmlHash, hero_sha256: String(direction.hero_sha256 || '') },
        rights: {
          customer_identity_included: false,
          customer_copy_reusable: false,
          customer_assets_reusable: false,
          generated_art_requires_review: true,
          client_consent_required_for_public_portfolio: !fictional,
          template_reuse_scope: 'structure and design rationale only; rewrite copy and replace every asset',
        },
      };
    }),
  };
}

export function archiveTemplateIdeas({ outputDirectory, sourceRoots, repositoryRoot = process.cwd() }) {
  const resolvedOutput = resolve(outputDirectory);
  const resolvedSources = sourceRoots.map((root) => resolve(root));
  const manifests = [...new Set(resolvedSources.flatMap((root) => walk(root, (path) => basename(path) === 'manifest.json')))].sort();
  const packages = [];
  const candidatesByIntegrity = new Map();
  const skipped = [];
  for (const manifestPath of manifests) {
    try {
      const loaded = loadPackage(manifestPath, repositoryRoot);
      if (!loaded) {
        skipped.push({ manifest: posix(relative(repositoryRoot, manifestPath)), reason: 'no recognizable built directions' });
        continue;
      }
      packages.push(loaded.package);
      for (const entry of loaded.entries) {
        const key = entry.artifact_integrity.html_sha256 || sha256(JSON.stringify(entry));
        if (!candidatesByIntegrity.has(key)) candidatesByIntegrity.set(key, entry);
      }
    }
    catch (error) {
      skipped.push({ manifest: posix(relative(repositoryRoot, manifestPath)), reason: error.message });
    }
  }
  const candidates = [...candidatesByIntegrity.values()].sort((a, b) => a.template_id.localeCompare(b.template_id));
  const now = new Date().toISOString();
  const preservation = {
    schema: 'famtastic.proof-preservation-index.v1',
    generated_at: now,
    policy: 'retain full proof packages; never infer public reuse rights',
    package_count: packages.length,
    packages,
    skipped,
  };
  const library = {
    schema: 'famtastic.template-candidate-library.v1',
    generated_at: now,
    publication_default: 'internal_only',
    candidate_count: candidates.length,
    candidates,
  };
  const summary = {
    schema: 'famtastic.template-library-summary.v1',
    generated_at: now,
    packages_preserved: packages.length,
    files_hashed: packages.reduce((sum, item) => sum + item.retained_files.length, 0),
    template_candidates: candidates.length,
    unselected_candidates: candidates.filter((item) => item.selection_state === 'unselected_candidate').length,
    selected_client_directions: candidates.filter((item) => item.selection_state === 'selected_client_direction').length,
    creative_bands: Object.fromEntries(['restrained', 'medium', 'ultra', 'unspecified'].map((band) => [band, candidates.filter((item) => item.creative_band === band).length])),
    skipped_manifests: skipped.length,
    all_candidates_private: candidates.every((item) => item.reuse_state !== 'public' && item.portfolio_status === 'owner_review_required'),
    all_customer_assets_blocked: candidates.every((item) => item.rights.customer_assets_reusable === false && item.rights.customer_copy_reusable === false),
  };
  mkdirSync(resolvedOutput, { recursive: true });
  writeFileSync(join(resolvedOutput, 'preservation-index.json'), JSON.stringify(preservation, null, 2) + '\n');
  writeFileSync(join(resolvedOutput, 'template-candidates.json'), JSON.stringify(library, null, 2) + '\n');
  writeFileSync(join(resolvedOutput, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
  return { preservation, library, summary, outputDirectory: resolvedOutput };
}

const invokedPath = process.argv[1] ? resolve(process.argv[1]) : '';
if (invokedPath === fileURLToPath(import.meta.url)) {
  const [outputDirectory, ...sourceRoots] = process.argv.slice(2);
  if (!outputDirectory || !sourceRoots.length) {
    console.error('Usage: archive-template-ideas.mjs <output-directory> <source-root> [source-root ...]');
    process.exit(2);
  }
  const result = archiveTemplateIdeas({ outputDirectory, sourceRoots });
  if (!result.summary.all_candidates_private || !result.summary.all_customer_assets_blocked) {
    console.error('FAIL: template privacy or rights gate failed');
    process.exit(1);
  }
  console.log(`PASS: preserved ${result.summary.packages_preserved} proof packages and hashed ${result.summary.files_hashed} files`);
  console.log(`PASS: cataloged ${result.summary.template_candidates} private template candidates (${result.summary.unselected_candidates} unselected)`);
  console.log('PASS: customer copy/assets remain blocked and public portfolio publication remains gated');
  console.log(`Evidence: ${join(result.outputDirectory, 'summary.json')}`);
}
