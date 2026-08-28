# FAMtastic Designs production changelog

## 2026-08-28 — Modular Customer Portal, My Products Hub & Project Provisioning Wizard

- Added dedicated "My Products" hub (`/portal?tab=products`) displaying active SSD cloud hosting (IP `198.71.232.3`), custom domain DNS helper records, and client command center status.
- Introduced 4-step guided Project Provisioning Wizard (`ProjectProvisioningWizard`) with Domain Configuration, Cloud Hosting, Design Brief & Assets, and direct "🚀 Send to Site Studio for Build" action (`/api/customer/website-requests/{website_request}/send-to-site-studio`).
- Rebuilt the client portal into a modular architecture across 15 dedicated subview components under `frontend/src/components/portal/` coordinated by `CustomerPortalDashboard.jsx`.
- Covered all 13+ portal lifecycle modules: Command Center, My Products, Project Briefs & Proof Review Room, Service & Marketplace Hub, Asset Library, Real Growth Telemetry, Messages, Governed Shay Solutions Advisor, Support Triage, Knowledge FAQs, Growth Ideas, Referral Rewards, Billing & Orders, Account & Team, and Settings.
- Encoded the governed AI workforce boundary and Build DNA provenance observability into repository contracts (`FAMTASTIC_PORTAL_SERVICE_SYSTEM.md`).

## 2026-08-02 — Operations observability and clickable metric evidence

Expanded the authenticated Drupal operations surface so every dashboard total
is now a semantic drill-down link to the exact records represented by the
number. Campaigns, prospects, ready proofs, sent emails, proof-link clicks,
paid orders, open jobs, and open exceptions each have an admin-only paginated
record page. In particular, **Paid Orders** now exposes the order, business,
source campaign, package, amount, verified payment state, and paid timestamp
behind the tile.

Added focused acceptance coverage for all eight links and pages, including a
count-to-record assertion for paid orders. The full autonomous acceptance lane
now runs this operator-board check. This release builds on the same-day contact
correction, exact message/build telemetry, checksum-gated offline Site Studio
bridge, and route-shell deployment correction already recorded in the
production deploy log.

The final marker-only frontend apply exposed a separate hosting-account quota:
each private Git release retained a reproducible `frontend/node_modules` tree.
The shared deploy script now removes that dependency tree on every remote exit,
including a failed `npm ci`, while preserving source, compiled release output,
production assets, and rollback backups.

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
