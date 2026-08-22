#!/usr/bin/env node

import { mkdtempSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawnSync } from "node:child_process";

const root = resolve(import.meta.dirname, "..");
const temporary = mkdtempSync(join(tmpdir(), "famtastic-proof-runner-"));
try {
  const fixtures = [
    ["public-three", join(root, "tests/fixtures/proof-runner-contract.fixture.json"), 3],
    ["detailed-refined-six", join(root, "tests/fixtures/proof-runner-refined-six.fixture.json"), 6],
  ];
  for (const [name, fixture, expectedCount] of fixtures) {
    const output = join(temporary, `${name}-receipt.json`);
    const result = spawnSync(process.execPath, [join(root, "scripts/validate-proof-runner-contract.mjs"), "--local-contract-fixture", "--contract", fixture, "--output", output], { encoding: "utf8" });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
    const receipt = JSON.parse(readFileSync(output, "utf8"));
    if (receipt.classification !== "local_contract_fixture" || receipt.status !== "local_contract_fixture_validated") throw new Error(`${name}: fixture receipt classification is wrong`);
    if (expectedCount === 6 && receipt.profile_id !== "portal_refined_six.v1") throw new Error("refined fixture did not preserve its profile");
    for (const [action, occurred] of Object.entries(receipt.mutations)) if (occurred) throw new Error(`${name}: fixture mutated ${action}`);
  }
  const callbackEvidence = spawnSync("php", [join(root, "tests/proof-runner-callback-evidence.test.php")], { encoding: "utf8" });
  if (callbackEvidence.status !== 0) throw new Error(callbackEvidence.stderr || callbackEvidence.stdout);
  process.stdout.write("PASS: public-three and detailed-refined-six local fixtures are no-send and no-proof\n");
}
finally {
  rmSync(temporary, { recursive: true, force: true });
}
