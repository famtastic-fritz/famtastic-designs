import assert from "node:assert/strict";
import { readFile, stat } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, "../public/showcase/booked-and-branded-pilot/alex-touch-prototype-v2");
const [publicHtml, ownerHtml, css, js, manifestText] = await Promise.all([
  readFile(path.join(root, "index.html"), "utf8"),
  readFile(path.join(root, "owner/index.html"), "utf8"),
  readFile(path.join(root, "prototype.css"), "utf8"),
  readFile(path.join(root, "prototype.js"), "utf8"),
  readFile(path.join(root, "prototype-manifest.json"), "utf8")
]);
const manifest = JSON.parse(manifestText);

const sectionIds = [...publicHtml.matchAll(/<section id="([^"]+)"/g)].map(match => match[1]);
assert.deepEqual(sectionIds, ["top", "reputation", "services", "work", "book"], "V2 must keep the five-section starter recipe");
assert.match(publicHtml, /FAMtastic V2 · Signal Cut/);
assert.match(publicHtml, /nothing sends, books, or charges/i);
assert.match(publicHtml, /id="public-map-frame"/);
assert.match(publicHtml, /id="contact-form"/);
assert.match(publicHtml, /A GREAT BARBER GETS REMEMBERED/);
assert.doesNotMatch(publicHtml, /owner of|owns the shop/i);
assert.match(ownerHtml, /Signal Cut V2/);
assert.match(ownerHtml, /data-owner-panel="dashboard"/);
assert.match(ownerHtml, /data-owner-panel="requests"/);
assert.match(ownerHtml, /data-owner-panel="services"/);
assert.match(ownerHtml, /data-owner-panel="hours"/);
assert.match(ownerHtml, /data-owner-panel="links"/);
assert.match(js, /famtastic\.alex-touch-prototype\.v2/);
assert.match(js, /setupContactForm/);
assert.doesNotMatch(js, /\bfetch\s*\(/, "V2 must not call an external application API");
assert.doesNotMatch(js, /stripe|smtp|twilio/i, "V2 must not imply connected payment or messaging infrastructure");
assert.match(css, /signal-ticker/);
assert.match(css, /reach-grid/);
assert.match(css, /@media \(max-width: 620px\)/);
assert.equal(manifest.parent_prototype_id, "alex-touch-port-st-lucie");
assert.equal(manifest.design_direction, "signal_cut_v2");
assert.equal(manifest.experience.external_effects.length, 0);

for (const asset of manifest.creative_assets) {
  const assetStat = await stat(path.join(root, asset.path));
  assert.ok(assetStat.size > 10_000, `${asset.path} should be a substantive local asset`);
}

console.log(`PASS Alex V2 Signal Cut: ${sectionIds.length} public sections, map + contact, 5 owner panels, ${manifest.creative_assets.length} local assets, zero external effects.`);
