#!/usr/bin/env node

import { createHash } from "node:crypto";
import { existsSync, readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";

const [dnaPath, repoRoot = process.cwd()] = process.argv.slice(2);

if (!dnaPath) {
  console.error("Usage: node website-delivery-swarm/scripts/validate-build-dna.mjs <build-dna.json> [repo-root]");
  process.exit(2);
}

const fail = (message) => {
  throw new Error(message);
};

const readJson = (path) => JSON.parse(readFileSync(path, "utf8"));
const sha256 = (path) => createHash("sha256").update(readFileSync(path)).digest("hex");
const requireString = (value, label) => {
  if (typeof value !== "string" || value.trim() === "") fail(`${label} must be a non-empty string`);
};
const requireObject = (value, label) => {
  if (!value || typeof value !== "object" || Array.isArray(value)) fail(`${label} must be an object`);
};

try {
  const absoluteDna = resolve(dnaPath);
  const dna = readJson(absoluteDna);
  if (dna.schema !== "famtastic.build-dna.v1") fail("Unsupported Build DNA schema");
  for (const field of ["build_id", "classification", "created_at"]) requireString(dna[field], field);
  if (Number.isNaN(Date.parse(dna.created_at))) fail("created_at must be an ISO date-time");
  requireObject(dna.repository, "repository");
  requireString(dna.repository.name, "repository.name");
  requireString(dna.repository.revision, "repository.revision");
  requireObject(dna.recipe, "recipe");
  for (const field of ["routine", "version", "build_class"]) requireString(dna.recipe[field], `recipe.${field}`);
  if (!Array.isArray(dna.stages) || dna.stages.length === 0) fail("stages must be a non-empty array");
  const seenStageAttempts = new Set();
  for (const stage of dna.stages) {
    requireObject(stage, "stage");
    requireString(stage.stage_id, "stage.stage_id");
    requireString(stage.capability, "stage.capability");
    if (!Number.isInteger(stage.attempt) || stage.attempt < 1) fail(`stage ${stage.stage_id} has invalid attempt`);
    const stageKey = `${stage.stage_id}:${stage.attempt}`;
    if (seenStageAttempts.has(stageKey)) fail(`duplicate stage attempt: ${stageKey}`);
    seenStageAttempts.add(stageKey);
    requireObject(stage.execution, `stage ${stageKey}.execution`);
    requireObject(stage.execution.provider, `stage ${stageKey}.execution.provider`);
    requireString(stage.execution.provider.id, `stage ${stageKey}.execution.provider.id`);
    requireObject(stage.execution.model, `stage ${stageKey}.execution.model`);
    requireString(stage.execution.model.status, `stage ${stageKey}.execution.model.status`);
    requireObject(stage.execution.timing, `stage ${stageKey}.execution.timing`);
    requireString(stage.execution.timing.status, `stage ${stageKey}.execution.timing.status`);
    requireObject(stage.execution.cost, `stage ${stageKey}.execution.cost`);
    requireString(stage.execution.cost.status, `stage ${stageKey}.execution.cost.status`);
    requireObject(stage.result, `stage ${stageKey}.result`);
    requireString(stage.result.status, `stage ${stageKey}.result.status`);
  }
  if (!Array.isArray(dna.artifacts) || dna.artifacts.length === 0) fail("artifacts must be a non-empty array");
  const root = resolve(repoRoot);
  for (const artifact of dna.artifacts) {
    requireObject(artifact, "artifact");
    requireString(artifact.role, "artifact.role");
    requireString(artifact.path, "artifact.path");
    if (!/^[a-f0-9]{64}$/.test(artifact.sha256 || "")) fail(`artifact ${artifact.path} has invalid SHA-256`);
    const absoluteArtifact = resolve(root, artifact.path);
    if (!absoluteArtifact.startsWith(root)) fail(`artifact escapes repository: ${artifact.path}`);
    if (!existsSync(absoluteArtifact)) fail(`missing artifact: ${artifact.path}`);
    const actual = sha256(absoluteArtifact);
    if (actual !== artifact.sha256) fail(`artifact checksum mismatch: ${artifact.path}`);
  }
  requireObject(dna.retrieval, "retrieval");
  for (const field of ["filesystem", "database", "site_studio"]) requireObject(dna.retrieval[field], `retrieval.${field}`);
  requireObject(dna.integrity, "integrity");
  if (dna.integrity.artifact_hash_algorithm !== "sha256") fail("integrity.artifact_hash_algorithm must be sha256");
  const relative = absoluteDna.startsWith(root) ? absoluteDna.slice(root.length + 1) : absoluteDna;
  console.log(`PASS: Build DNA ${dna.build_id}`);
  console.log(`PASS: ${dna.stages.length} stage records and ${dna.artifacts.length} artifact checksums`);
  console.log(`Evidence: ${relative}`);
}
catch (error) {
  console.error(`FAIL: ${error.message}`);
  process.exit(1);
}
