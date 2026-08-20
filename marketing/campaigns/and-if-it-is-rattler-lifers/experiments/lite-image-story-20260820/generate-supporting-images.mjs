#!/usr/bin/env node
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.dirname(fileURLToPath(import.meta.url));
const prompts = JSON.parse(await readFile(path.join(root, "prompts.json"), "utf8"));
const sourcePath = path.join(root, prompts.reference_asset);
const source = await readFile(sourcePath);
const startedAt = new Date().toISOString();
const apiKey = execFileSync("/usr/bin/security", [
  "find-generic-password",
  "-s", "FAMtastic.Gemini.Image",
  "-a", "famtastic-gemini-image-worker",
  "-w",
], { encoding: "utf8" }).trim();

const extensionFor = (mime) => mime === "image/png" ? "png" : mime === "image/webp" ? "webp" : "jpg";
const sha256 = (bytes) => createHash("sha256").update(bytes).digest("hex");
const results = [];

for (const asset of prompts.assets) {
  const started = Date.now();
  const response = await fetch(
    `https://generativelanguage.googleapis.com/v1/models/${prompts.model}:generateContent`,
    {
      method: "POST",
      headers: { "content-type": "application/json", "x-goog-api-key": apiKey },
      body: JSON.stringify({
        contents: [{ parts: [
          { text: asset.prompt },
          { inlineData: { mimeType: "image/jpeg", data: source.toString("base64") } },
        ] }],
        generationConfig: {
          responseModalities: ["IMAGE"],
          imageConfig: asset.requested_output ?? prompts.requested_output,
        },
      }),
    },
  );
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(`Gemini request failed for ${asset.id}: ${response.status} ${JSON.stringify(payload)}`);
  }
  const imagePart = payload.candidates?.flatMap((candidate) => candidate.content?.parts ?? [])
    .find((part) => part.inlineData?.data);
  if (!imagePart?.inlineData?.data) {
    throw new Error(`Gemini returned no image for ${asset.id}: ${JSON.stringify(payload)}`);
  }
  const image = Buffer.from(imagePart.inlineData.data, "base64");
  const mimeType = imagePart.inlineData.mimeType ?? "image/jpeg";
  const preferred = path.join(root, asset.output);
  const output = preferred.replace(/\.(jpg|jpeg|png|webp)$/i, `.${extensionFor(mimeType)}`);
  await mkdir(path.dirname(output), { recursive: true });
  await writeFile(output, image);
  results.push({
    id: asset.id,
    role: asset.role,
    prompt: asset.prompt,
    prompt_sha256: sha256(asset.prompt),
    output: path.relative(root, output),
    mime_type: mimeType,
    bytes: image.length,
    sha256: sha256(image),
    duration_ms: Date.now() - started,
    requested_output: asset.requested_output ?? prompts.requested_output,
    usage_metadata: payload.usageMetadata ?? null,
  });
  process.stdout.write(`PASS ${asset.id} ${Date.now() - started}ms ${path.relative(root, output)}\n`);
}

const receipt = {
  schema: "famtastic.reference-led-story-image-receipt.v1",
  run_id: prompts.run_id,
  provider: "google-gemini-api",
  api: "generateContent",
  model: prompts.model,
  started_at: startedAt,
  completed_at: new Date().toISOString(),
  reference_asset: prompts.reference_asset,
  reference_sha256: sha256(source),
  reference_role: prompts.reference_role,
  requested_output: prompts.requested_output,
  cost_usd_expected_per_image_output: 0.0336,
  cost_usd_expected_total_output: Number((0.0336 * results.length).toFixed(4)),
  results,
};
await writeFile(path.join(root, "evidence", "generation-receipt.json"), `${JSON.stringify(receipt, null, 2)}\n`);
