# Project-shared marketing skills adoption

**Date:** 2026-08-22
**Status:** installed as supporting methods; not evidence of a new sellable or
autonomous capability

## Decision

FAMtastic adopted a narrow, version-pinned marketing core from
[`coreyhaines31/marketingskills`](https://github.com/coreyhaines31/marketingskills)
at commit `3df87f97621e18fbed7f6aa684edba54f49779a7` under its MIT license.
The pinned directories and their SHA-256 checksums are in
`marketing/engine/upstream-skills.json`.

The upstream collection is not a second marketing operating system. Its
specialists are supporting methods for FAMtastic's existing demand-engine,
Build DNA, product truth, capability evidence, and approval contracts.

## Installed core

| Customer/business job | Project-shared specialists | FAMtastic boundary |
|---|---|---|
| Shared positioning context | `product-marketing` | Read `.agents/product-marketing.md` first; it is derived from FAMtastic sources and cannot invent an offer or proof. |
| Conversion and intake | `cro`, `signup`, `onboarding`, `popups` | Produce a recommendation or implementation plan; browser/accessibility evidence and release approval remain separate. |
| Lead-to-service continuity | `revops`, `sales-enablement`, `offers` | Drupal/Commerce/customer ownership is canonical. No contact, quote, grant, discount, or charge without authority. |
| Campaign ideation and creative | `marketing-ideas`, `marketing-loops`, `ad-creative`, `social` | Draft-first. Campaign, media, and publish approvals remain three distinct decisions. |
| Findability and measurement | `ai-seo`, `site-architecture`, `schema`, `analytics`, `ab-testing` | Claims require source evidence; live tracking, structured-data deployment, and experiments require implementation and verification. |

## Explicit exclusions

The upstream repository also contains outreach, email, prospecting, paid-media,
retention, and publishing-oriented skills. They were not installed in this core
because FAMtastic already has more specific contracts or they would expand
authority around real recipients, spend, or customer data. They remain
on-demand evaluation candidates, not automatically available execution lanes.

## Required workflow

1. Read `.agents/product-marketing.md`, then the relevant FAMtastic doctrine.
2. Load the narrowest specialist for the job.
3. Ground output in the capability registry, product/terms records, and cited
   external research where appropriate.
4. Attach stable IDs, claim/evidence basis, CTA, destination, analytics plan,
   and approval state to campaign or content work.
5. Use Build DNA for creative proofs and media, then stop at the required
   content, media, and publish gates.

## Maintenance

Do not run broad auto-updates. Review an upstream version in isolation, compare
the source commit, license, individual skill hashes, and FAMtastic policy
compatibility, then update the manifest and shared copies in one reviewed
commit. A generic skill can advise; it cannot upgrade a capability classification
or create a production claim.
