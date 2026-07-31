# FAMtastic Designs production changelog

## 2026-07-31 — Autonomous pipeline completion candidate

Completed the Git-tracked lead-to-launch implementation on PR #16. The branch
now includes attributed lead ingestion, three proofs, gated outreach,
proof/package selection, versioned terms, signed payment fulfillment, paid
intake, revision limits and purchasable add-ons, immutable customer deployment,
domain/TLS verification, included-year hosting, separately authorized month-13
renewal, customer lifecycle status, analytics, exception handling, and one
agent-agnostic acceptance command.

The $199 and $499 acceptance journeys correlate a single imported lead through
launch and renewal instead of relying only on independent component fixtures.
Removed the obsolete root-Nuxt lint/typecheck workflows and their pnpm setup
action. The remaining GitHub workflow validates only the canonical React
frontend and Drupal backend, matching the production deployment source.
No production release or live provider action is included in this changelog
entry; those remain approval-gated.

## 2026-07-30 — Blank-page recovery and permanent deployment correction

Recovered the React frontend after a historical deployment flattened
`frontend/dist/assets` into the GoDaddy document root while `index.html`
continued to request `/assets/*`. The missing module request received the SPA
HTML fallback, preventing React from mounting. Restored the bundles under
`public_html/assets/` and verified the live application on both apex and
`www`.

Replaced the mixed manual/production-branch process with one agent-agnostic,
Git-tracked release lane. GoDaddy now checks out and builds the exact merged
`main` commit in a private release directory outside `public_html`, using
repository-pinned Node 22. The workflow validates compiled asset paths, creates
a frontend-only rollback archive, promotes assets before `index.html`, records
the release commit and runtime, and verifies live MIME types.

The first complete release through the new lane deployed commit
`ebbbfa0c0e521e7d9de675eaafaf4cdf2a4e39ca` with Node `v22.23.2`. Browser
acceptance passed for `https://famtasticdesigns.com` and
`https://www.famtasticdesigns.com` with populated React roots, the correct
heading, no console errors, no page exceptions, and no failed requests.

Rollback archive:

```text
/home/xrdj7j99xhzt/backups/famtastic-frontend-20260730T192809Z-ebbbfa0c0e521e7d9de675eaafaf4cdf2a4e39ca.tgz
```

## 2026-07-30 — Canonical source promoted out of `v2`

Promoted the approved React/Drupal application source from the temporary `v2`
rebuild directory into the repository’s canonical `frontend`, `backend`, and
supporting root paths. Removed the obsolete `v2` directory so agents no longer
have to choose between competing application roots.

## 2026-07-23 — Reviewed quote, contact, and SEO work

Committed public quote/contact capture and route-specific SEO improvements in
`af3e8424`, `a35e0e8a`, and `a952b60d`. These changes were originally
transported through direct SSH/SCP rather than an authenticated Git release
lane, which contributed to production/source drift. The content changes were
not the direct cause of the later blank page.
