import assert from "node:assert/strict";
import { readFile, stat } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, "../public/showcase/booked-and-branded-pilot/alex-touch-prototype");
const [publicHtml, ownerHtml, css, js, manifestText] = await Promise.all([
  readFile(path.join(root, "index.html"), "utf8"),
  readFile(path.join(root, "owner/index.html"), "utf8"),
  readFile(path.join(root, "prototype.css"), "utf8"),
  readFile(path.join(root, "prototype.js"), "utf8"),
  readFile(path.join(root, "prototype-manifest.json"), "utf8")
]);
const manifest = JSON.parse(manifestText);

const sectionIds = [...publicHtml.matchAll(/<section id="([^"]+)"/g)].map(match => match[1]);
assert.deepEqual(sectionIds, ["top", "reputation", "services", "work", "book"], "public prototype must keep exactly five sections");
assert.match(publicHtml, /nothing sends, books, or charges/i);
assert.match(publicHtml, /rel="icon" href="\.\/assets\/brand-leather\.webp"/);
assert.match(publicHtml, /Independent chair/i);
assert.doesNotMatch(publicHtml, /owner of|owns the shop/i);
assert.match(ownerHtml, /data-owner-panel="dashboard"/);
assert.match(ownerHtml, /data-owner-panel="requests"/);
assert.match(ownerHtml, /data-owner-panel="services"/);
assert.match(ownerHtml, /data-owner-panel="hours"/);
assert.match(ownerHtml, /data-owner-panel="links"/);
assert.match(ownerHtml, /rel="icon" href="\.\.\/assets\/brand-leather\.webp"/);
assert.match(ownerHtml, /FAMtastic founding-chair proposal/);
assert.match(ownerHtml, /\$199/);
assert.match(ownerHtml, /9\.99\/month only with separate authorization/);
assert.match(ownerHtml, /https:\/\/famtasticdesigns\.com\/buy/);
assert.match(js, /famtastic\.alex-touch-prototype\.v1/);
assert.doesNotMatch(js, /\bfetch\s*\(/, "prototype must not call an external API");
assert.doesNotMatch(js, /stripe|smtp|twilio/i, "prototype must not imply connected payment or messaging infrastructure");
assert.match(css, /@media \(max-width: 620px\)/);
assert.equal(manifest.experience.external_effects.length, 0);
assert.equal(manifest.generation_receipt.image_count, 3);
assert.equal(manifest.subject.ownership_claim, "not_claimed");

for (const asset of manifest.creative_assets) {
  const assetStat = await stat(path.join(root, asset.path));
  assert.ok(assetStat.size > 10_000, `${asset.path} should be a substantive local asset`);
}

console.log(`PASS alex-touch prototype: ${sectionIds.length} public sections, 5 owner panels, ${manifest.creative_assets.length} local assets, zero external effects.`);
