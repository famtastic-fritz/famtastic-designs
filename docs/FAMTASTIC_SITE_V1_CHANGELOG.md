# FAMtastic Designs site v1 changelog

## What changed
- Replaced the active legacy Drupal/PHP site app with the approved Nuxt proof site.
- Added the required marketing routes, pricing, work, contact, portal preview, and thank-you flow.
- Added local/mock lead persistence to `.data/famtastic-leads.json`.
- Added Directus proof scaffolding under `directus/`.
- Added environment examples and proofing/handoff docs.

## What old content was preserved
- Full pre-integration git history remains in the backup branch and tag.
- External archive created before replacement.
- Inventory recorded in `docs/PREVIOUS_SITE_INVENTORY.md`.

## What was replaced
- Legacy Drupal/PHP runtime files were removed from the active branch so the Nuxt proof app can own the repo root cleanly.

## What was added
- Nuxt proof app structure
- Proof docs
- Directus local proof files
- Example lead payload
- Directus lead/CMS adapter scaffolding

## Known issues
- The current Nuxt dev fix still uses a package.json imports mapping for `#internal/nuxt/paths`.
- Directus schema import is documented/sample-shaped, not fully automated.
- Portal remains a UI proof path, not a live authenticated client workspace.

## Next steps
- Replace placeholder booking/payment links.
- Decide production Directus hosting.
- Remove demo labels where real portfolio is ready.
- Replace the Nuxt imports band-aid with a cleaner upstream-compatible fix.
