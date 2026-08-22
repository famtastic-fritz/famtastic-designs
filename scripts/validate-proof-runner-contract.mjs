#!/usr/bin/env node

/**
 * Validates the safe local-contract-fixture lane for the proof runner.
 *
 * It is intentionally incapable of generating artwork, making a model call,
 * writing a proof, sending mail, charging, publishing, or calling Site Studio.
 * The production runner receives the same JSON contract through its signed
 * dispatch boundary and must return the final Build DNA in the callback.
 */
import { createHash } from "node:crypto";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";

const options = Object.fromEntries(process.argv.slice(2).reduce((items, value, index, all) => {
  if (!value.startsWith("--")) return items;
  const key = value.slice(2);
  const next = all[index + 1];
  items.push([key, next && !next.startsWith("--") ? next : true]);
  return items;
}, []));

const fail = (message) => {
  throw new Error(message);
};

const requireString = (value, field) => {
  if (typeof value !== "string" || value.trim() === "") fail(`${field} is required`);
};

const hash = (value) => createHash("sha256").update(value).digest("hex");

const prohibitedKeys = new Set(["email", "public_email", "phone", "public_phone", "password", "token", "access_token", "refresh_token", "api_key", "secret"]);
const assertNoRawContact = (value, key = "") => {
  if (prohibitedKeys.has(key.toLowerCase())) fail(`Raw contact or credential key is prohibited: ${key}`);
  if (value && typeof value === "object") Object.entries(value).forEach(([childKey, childValue]) => assertNoRawContact(childValue, childKey));
};

const validate = (contract) => {
  if (contract.schema !== "famtastic.proof-runner-request.v1") fail("Unsupported proof runner request schema");
  ["contract_version", "build_id", "idempotency_key", "routine", "created_at", "build_class"].forEach((field) => requireString(contract[field], field));
  if (contract.routine !== "website_proof.generate.v1") fail("Only website_proof.generate.v1 may use this runner");
  if (Number.isNaN(Date.parse(contract.created_at))) fail("created_at must be ISO date-time");
  if (!contract.source || !["public_solution_finder_intake", "authenticated_website_request"].includes(contract.source.type)) fail("Source type is not supported");
  if (!Number.isInteger(contract.source.prospect_id) || contract.source.prospect_id < 1) fail("Source prospect_id is required");
  if (!/^[a-f0-9]{64}$/.test(contract.source.contact_hash || "")) fail("Source contact hash is required");
  if (!contract.profile || !["public_initial.v1", "portal_initial.v1", "portal_showcase.v1"].includes(contract.profile.id)) fail("Known proof profile is required");
  if (contract.profile.proof_count !== 3 || contract.profile.customer_visibility !== "owner_review_only") fail("Fixture only accepts owner-gated three-direction profiles");
  const expectedDirections = contract.profile.id === "portal_showcase.v1" ? ["d", "e", "f"] : ["a", "b", "c"];
  const directions = Object.keys(contract.profile.directions || {}).sort();
  if (directions.join(",") !== expectedDirections.join(",")) fail("Proof profile must retain the exact routine direction set");
  expectedDirections.forEach((id) => {
    requireString(contract.profile.directions[id]?.name, `profile.directions.${id}.name`);
    requireString(contract.profile.directions[id]?.intent, `profile.directions.${id}.intent`);
  });
  if (!contract.provider_preflight || typeof contract.provider_preflight.ready !== "boolean") fail("Provider preflight result is required");
  ["customer_email", "outbox_enqueue", "checkout", "payment", "domain_purchase", "production_publish"].forEach((key) => {
    if (contract.mutation_policy?.[key] !== "forbidden") fail(`Mutation policy must forbid ${key}`);
  });
  if (contract.return_contract?.callback_must_include_complete_build_dna !== true || contract.return_contract?.owner_review_required_before_customer_visibility !== true) fail("Final Build DNA and owner review gates are required");
  if (contract.return_contract?.callback_variants_must_include_sha256 !== true || contract.return_contract?.callback_build_dna_must_include_per_direction_html_hashes !== true) fail("Callback artifact lineage gates are required");
  assertNoRawContact(contract);
};

try {
  if (options["local-contract-fixture"] !== true || !options.contract || !options.output) {
    fail("Usage: validate-proof-runner-contract.mjs --local-contract-fixture --contract <file> --output <file>");
  }
  const input = resolve(String(options.contract));
  if (!existsSync(input)) fail("Contract file does not exist");
  const raw = readFileSync(input, "utf8");
  const contract = JSON.parse(raw);
  validate(contract);
  const receipt = {
    schema: "famtastic.proof-runner-fixture-receipt.v1",
    classification: "local_contract_fixture",
    status: "local_contract_fixture_validated",
    build_id: contract.build_id,
    contract_sha256: hash(raw),
    source_type: contract.source.type,
    profile_id: contract.profile.id,
    mutations: { proof_generated: false, provider_called: false, email_sent: false, outbox_enqueued: false, payment_started: false, production_published: false },
    next_required_step: "Configure a signed provider route; return final Build DNA, browser QA, and independent review through the callback before owner review.",
  };
  const output = resolve(String(options.output));
  mkdirSync(dirname(output), { recursive: true });
  writeFileSync(output, `${JSON.stringify(receipt, null, 2)}\n`);
  process.stdout.write(`PASS: local_contract_fixture ${receipt.build_id}\n`);
}
catch (error) {
  process.stderr.write(`FAIL: ${error.message}\n`);
  process.exit(1);
}
