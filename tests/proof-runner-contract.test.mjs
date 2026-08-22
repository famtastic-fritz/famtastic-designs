#!/usr/bin/env node

import { mkdtempSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawnSync } from "node:child_process";

const root = resolve(import.meta.dirname, "..");
const fixture = join(root, "tests/fixtures/proof-runner-contract.fixture.json");
const temporary = mkdtempSync(join(tmpdir(), "famtastic-proof-runner-"));
const output = join(temporary, "receipt.json");
try {
  const result = spawnSync(process.execPath, [join(root, "scripts/validate-proof-runner-contract.mjs"), "--local-contract-fixture", "--contract", fixture, "--output", output], { encoding: "utf8" });
  if (result.status !== 0) throw new Error(result.stderr || result.stdout);
  const receipt = JSON.parse(readFileSync(output, "utf8"));
  if (receipt.classification !== "local_contract_fixture" || receipt.status !== "local_contract_fixture_validated") throw new Error("Fixture receipt classification is wrong");
  for (const [action, occurred] of Object.entries(receipt.mutations)) if (occurred) throw new Error(`Fixture mutated ${action}`);
  process.stdout.write("PASS: proof runner local contract fixture is no-send and no-proof\n");
}
finally {
  rmSync(temporary, { recursive: true, force: true });
}
