#!/usr/bin/env node
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const defaultRepository = path.resolve(here, "../../..");
const sha256 = (value) => createHash("sha256").update(value).digest("hex");
const hashFile = async (file) => sha256(await readFile(file));

function git(repository, ...args) {
  return execFileSync("git", ["-C", repository, ...args], { encoding: "utf8" }).trim();
}

export async function writeBuildDna({ output, repository = defaultRepository }) {
  const receiptPath = path.join(output, "receipt.json");
  const receipt = JSON.parse(await readFile(receiptPath, "utf8"));
  const revision = git(repository, "rev-parse", "HEAD");
  const worktreeState = git(repository, "status", "--porcelain") === "" ? "clean" : "dirty_at_generation";
  const promptFor = (interaction) => interaction.label.startsWith("01-") ? receipt.prompts.first : receipt.prompts.revision;
  const stages = receipt.interactions.map((interaction, index) => ({
    stage_id: interaction.label,
    attempt: 1,
    capability: index === 0 ? "reference_led_image_generation" : "stateful_image_revision",
    execution: {
      provider: { id: receipt.provider, api: receipt.api, interaction_id: interaction.interaction_id },
      model: { id: receipt.model, status: "provider_executed" },
      timing: { status: "reported", duration_ms: interaction.duration_ms },
      cost: receipt.expected_cost,
      prompt: { verbatim: promptFor(interaction), sha256: sha256(promptFor(interaction)) },
      input: { reference_sha256: receipt.reference.sha256 },
      output: interaction.image ?? { status: "no_image_output", response_sha256: interaction.response_sha256 },
    },
    result: { status: interaction.image ? "passed" : "failed", error: interaction.error },
  }));
  const artifacts = [
    {
      role: "reference_visual_canon",
      path: receipt.reference.file,
      sha256: receipt.reference.sha256,
    },
    {
      role: "provider_receipt",
      path: path.relative(repository, receiptPath),
      sha256: await hashFile(receiptPath),
    },
  ];
  for (const interaction of receipt.interactions) {
    if (interaction.image?.file) {
      artifacts.push({
        role: interaction.label,
        path: interaction.image.file,
        sha256: interaction.image.sha256,
      });
    }
  }
  const dna = {
    schema: "famtastic.build-dna.v1",
    build_id: receipt.run_id,
    classification: receipt.classification,
    created_at: receipt.started_at,
    repository: {
      name: "famtastic-designs",
      revision,
      worktree_state: worktreeState,
    },
    recipe: {
      routine: "gemini_interactions.famu_reference_benchmark.v1",
      version: "1.0.0",
      build_class: "low",
      mutation_boundary: "no customer, Drupal, Site Studio, or production mutation",
    },
    stages,
    artifacts,
    retrieval: {
      filesystem: { path: path.relative(repository, output) },
      database: { status: "not_registered_provider_route_test" },
      site_studio: { status: "not_dispatched_provider_route_test" },
    },
    integrity: { artifact_hash_algorithm: "sha256" },
  };
  const dnaPath = path.join(output, "build-dna.json");
  await writeFile(dnaPath, `${JSON.stringify(dna, null, 2)}\n`);
  return dnaPath;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const output = path.resolve(process.argv[2] ?? path.join(defaultRepository, "artifacts", "gemini-interactions-famu-reference-20260822"));
  console.log(await writeBuildDna({ output }));
}
