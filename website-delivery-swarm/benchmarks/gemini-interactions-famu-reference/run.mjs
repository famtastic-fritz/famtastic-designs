#!/usr/bin/env node
/**
 * Verify a reference-led, stateful Gemini Image interaction without touching
 * customer, Drupal, Site Studio, production, or a paid project record.
 *
 * This targets the Gemini Developer API's Interactions endpoint. It is not a
 * Gemini Enterprise Agent Platform (GEAP) test and does not infer that a
 * desktop Antigravity subscription authorizes unattended API execution.
 */
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { writeBuildDna } from "./write-build-dna.mjs";

const root = path.dirname(fileURLToPath(import.meta.url));
const repository = path.resolve(root, "../../..");
const output = path.join(repository, "artifacts", "gemini-interactions-famu-reference-20260822");
const reference = path.join(repository, "marketing", "campaigns", "and-if-it-is-rattler-lifers", "experiments", "lite-image-story-20260820", "assets", "00-lite-image-canon.jpg");
const model = "gemini-3.1-flash-lite-image";
const sha256 = (value) => createHash("sha256").update(value).digest("hex");
const now = () => new Date().toISOString();

const key = execFileSync("/usr/bin/security", [
  "find-generic-password",
  "-s", "FAMtastic.Gemini.Image",
  "-a", "famtastic-gemini-image-worker",
  "-w",
], { encoding: "utf8" }).trim();

const visualContract = [
  "The attached image is an owned visual canon only. Preserve its cinematic editorial grammar: warm amber and forest-green light, wet concrete, brushed stadium metal, weathered leather, denim, subtle 35mm grain, lifelike Black skin/hair/fabric detail, and believable candid emotion.",
  "Create a wholly new photograph. Do not reuse the reference's people, exact pose, crop, stadium view, pixels, school name, logo, seal, mascot, uniform mark, text, numbers, watermark, celebrity, real identifiable person, worship/cult imagery, hazing, coercion, propaganda, aggressive animal imagery, generic stock-photo posing, cloned faces, or malformed hands.",
].join(" ");

const firstPrompt = [
  "Use case: photorealistic natural editorial campaign image for an unofficial lifelong college-fan story.",
  visualContract,
  "Create a new 16:9 horizontal twilight scene at a completely unbranded Southern college-stadium approach. A multigenerational Black family and two longtime friends walk toward distant amber floodlights after a rain. They carry a folded blanket, a plain leather program holder, and an unbranded jacket; the wardrobe quietly uses forest, copper, black, and denim without forming a school mark. Camera is low and behind the group, with a wide darker negative-space field in the upper-left for future web typography. The feeling is return, pride, and FAMU-ly-like belonging without any official affiliation claim.",
].join("\n\n");

const revisionPrompt = [
  "Create a new companion portrait from this same unofficial visual world. Preserve the lighting grammar, palette, tactile materials, candid intergenerational feeling, and no-brand/no-text constraints from the previous image. Do not reuse any person, exact pose, or background.",
  "Make it 9:16: an older Black alumnus in a deep-green wool coat and a younger Black fan in a dark denim jacket share a quiet laugh beneath an unbranded concrete stadium stairwell after rain. Copper reflections, brushed metal rail, mist, believable anatomy, and clean darker top-third negative space for a social caption. No words, marks, mascot, seal, uniform, or signage.",
].join("\n\n");

function extractImage(response) {
  if (response.output_image?.data) {
    return { data: response.output_image.data, mime: response.output_image.mime_type ?? "image/jpeg" };
  }
  for (const step of response.steps ?? []) {
    for (const block of step.content ?? []) {
      const candidate = block.image ?? block;
      if (candidate?.data && (candidate.mime_type ?? candidate.mimeType)?.startsWith("image/")) {
        return { data: candidate.data, mime: candidate.mime_type ?? candidate.mimeType };
      }
    }
  }
  return null;
}

async function interaction(label, payload) {
  const started = Date.now();
  const response = await fetch("https://generativelanguage.googleapis.com/v1beta/interactions", {
    method: "POST",
    headers: { "content-type": "application/json", "x-goog-api-key": key },
    body: JSON.stringify(payload),
  });
  const raw = await response.text();
  let parsed;
  try { parsed = JSON.parse(raw); } catch { parsed = { raw_text: raw }; }
  const summary = {
    label,
    http_status: response.status,
    duration_ms: Date.now() - started,
    interaction_id: parsed.id ?? null,
    status: parsed.status ?? null,
    usage: parsed.usage ?? parsed.usage_metadata ?? null,
    response_sha256: sha256(raw),
    error: response.ok ? null : parsed.error ?? parsed,
  };
  if (!response.ok) {
    return { summary, response: parsed };
  }
  const image = extractImage(parsed);
  if (!image?.data) {
    summary.error = { message: "Interaction completed without an image output" };
    return { summary, response: parsed };
  }
  const bytes = Buffer.from(image.data, "base64");
  const extension = image.mime === "image/png" ? "png" : image.mime === "image/webp" ? "webp" : "jpg";
  const outputFile = path.join(output, `${label}.${extension}`);
  await writeFile(outputFile, bytes);
  summary.image = {
    file: path.relative(repository, outputFile),
    mime_type: image.mime,
    bytes: bytes.length,
    sha256: sha256(bytes),
  };
  return { summary, response: parsed };
}

await mkdir(output, { recursive: true });
const runStartedAt = now();
const referenceBytes = await readFile(reference);
const baseInput = [
  { type: "image", data: referenceBytes.toString("base64"), mime_type: "image/jpeg" },
  { type: "text", text: firstPrompt },
];
const first = await interaction("01-lite-reference-led", {
  model,
  input: baseInput,
  store: true,
  response_format: { type: "image", mime_type: "image/jpeg", aspect_ratio: "16:9", image_size: "1K" },
});

let second = null;
if (first.summary.interaction_id && first.summary.image) {
  second = await interaction("02-lite-stateful-revision", {
    model,
    input: revisionPrompt,
    store: true,
    previous_interaction_id: first.summary.interaction_id,
    response_format: { type: "image", mime_type: "image/jpeg", aspect_ratio: "9:16", image_size: "1K" },
  });
}

const receipt = {
  schema: "famtastic.gemini-interactions-image-benchmark.v1",
  run_id: "gemini-interactions-famu-reference-20260822-001",
  classification: "provider-route-test-no-customer-or-production-mutation",
  provider: "google-gemini-api",
  api: "interactions",
  model,
  started_at: runStartedAt,
  reference: {
    file: path.relative(repository, reference),
    sha256: sha256(referenceBytes),
    role: "FAMU-adjacent unofficial visual canon; not a request to reproduce people, marks, or official affiliation.",
  },
  prompts: {
    first: firstPrompt,
    revision: revisionPrompt,
    first_sha256: sha256(firstPrompt),
    revision_sha256: sha256(revisionPrompt),
  },
  expected_cost: { currency: "USD", amount: null, status: "provider_did_not_report_invoice_cost" },
  interactions: [first.summary, ...(second ? [second.summary] : [])],
  completed_at: now(),
};
await writeFile(path.join(output, "receipt.json"), `${JSON.stringify(receipt, null, 2)}\n`);
await writeBuildDna({ output, repository });
console.log(JSON.stringify(receipt, null, 2));
if (!first.summary.image || (second && !second.summary.image)) process.exit(2);
