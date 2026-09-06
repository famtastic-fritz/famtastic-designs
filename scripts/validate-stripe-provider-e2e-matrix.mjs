#!/usr/bin/env node

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(new URL('..', import.meta.url).pathname);
const readJson = (relativePath) => JSON.parse(readFileSync(resolve(root, relativePath), 'utf8'));
const catalog = readJson('backend/config/famtastic-products.json');
const matrix = readJson('backend/config/famtastic-stripe-provider-e2e-matrix.json');

function fail(message) {
  process.stderr.write(`FAIL: ${message}\n`);
  process.exitCode = 1;
}

if (matrix.schema !== 'famtastic.stripe-provider-e2e-matrix.v1') fail('matrix schema is missing or unsupported');
if (matrix.status !== 'scaffold_only_no_provider_calls') fail('matrix must remain provider-call free until an explicit test run is implemented');
if (matrix.execution_boundary?.requires_explicit_operator_gate !== 'FAMTASTIC_STRIPE_PROVIDER_E2E=1') fail('matrix must name the explicit provider execution gate');
if (matrix.private_offer_fixture?.amount_minor !== 100 || matrix.private_offer_fixture?.sku !== 'FAM-FOOT-199') fail('the exact scoped $1 private-offer fixture is missing');
if (!matrix.private_offer_fixture?.must_prove?.some((item) => item.includes('exact-account/exact-request'))) fail('the $1 private-offer fixture must be exact-account/exact-request scoped');

const catalogProducts = new Map((catalog.products || []).map((item) => [item.sku, item]));
const matrixProducts = new Map((matrix.products || []).map((item) => [item.sku, item]));
if (catalogProducts.size !== 16 || matrixProducts.size !== 16) fail('the catalog and matrix must each enumerate exactly 16 products');

let oneTime = 0;
let recurring = 0;
for (const [sku, product] of catalogProducts) {
  const scenario = matrixProducts.get(sku);
  if (!scenario) {
    fail(`matrix is missing ${sku}`);
    continue;
  }
  const billingKind = product.billing?.kind;
  if (scenario.billing_kind !== billingKind) fail(`${sku} billing kind differs from catalog`);
  if (scenario.payment_mode !== product.payment?.mode) fail(`${sku} payment mode differs from catalog`);
  if (!product.payment?.customer_message || !(product.payment?.requires || []).length) fail(`${sku} has an incomplete catalog payment contract`);
  if (billingKind === 'one_time') oneTime++;
  if (billingKind === 'recurring') recurring++;
}

for (const sku of matrixProducts.keys()) if (!catalogProducts.has(sku)) fail(`matrix names non-catalog SKU ${sku}`);
if (oneTime !== 12 || recurring !== 4) fail(`expected 12 one-time and 4 recurring products, found ${oneTime} and ${recurring}`);
if ((matrix.shared_scenarios || []).length < 8) fail('matrix must retain the complete shared provider evidence contract');

if (!process.exitCode) {
  process.stdout.write(`PASS: ${oneTime} one-time and ${recurring} recurring catalog products match the provider E2E evidence matrix. No Stripe call was made.\n`);
}
