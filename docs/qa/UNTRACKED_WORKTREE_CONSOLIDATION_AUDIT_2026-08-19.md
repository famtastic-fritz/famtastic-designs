# Untracked worktree consolidation audit

**Date:** August 19, 2026
**Repository:** `famtastic-fritz/famtastic-designs`
**Branch:** `codex/shay-website-delivery-swarm`
**Purpose:** Understand, preserve, and classify the shared worktree before any bulk commit, archive, or deletion.

## Executive finding

The worktree contained 1,931 untracked files totaling 1,822,474,952 bytes (about 1.82 GB). The apparent disorder was primarily generated evidence rather than 1,931 independent source changes:

- 1,696 files and 1.69 GB were under `artifacts/`;
- 131 files and 83.5 MB were the website-delivery swarm engine, schemas, tests, pilots, and pilot media;
- 70 files and 44.0 MB were the Rattler Lifers campaign source and evidence;
- the remaining hooks, skills, backend service, frontend test, scripts, and documents totaled less than 120 KB excluding media.

No untracked text file matched the credential-value screening patterns. However, 386 untracked text files contain email addresses. Those files are operational evidence and must not be broadly shared or copied to a public repository without sanitization.

## Evidence classes

### A. Durable application and automation source

Review, test, and commit deliberately:

- `.githooks/pre-push`;
- `agent-skills/run-human-experience-tester/`;
- `agent-skills/run-website-delivery-swarm/`;
- `backend/web/modules/custom/famtastic_pipeline/src/Service/SiteStudioBuildPacketService.php`;
- `frontend/e2e/public-lead-to-portal.spec.js`;
- `scripts/` new proof, publishing, certification, drift, and runner scripts;
- `website-delivery-swarm/` engine, provider routing, schemas, prompts, tests, library tooling, and scenario definitions.

These files describe behavior and are not replaceable merely by retaining screenshots.

### B. Durable human and agent knowledge

Review and commit with their related implementation:

- autonomous preview-to-Site-Studio master plan;
- Gandalf FAMtastic/Site Studio bridge contract;
- lean social-presence baseline and production process;
- website-delivery swarm implementation and six-direction benchmark;
- FAMtastic Lab plan;
- Broward pilot and public lead-to-portal QA documents.

### C. Campaign source with mixed durable and generated content

`marketing/campaigns/and-if-it-is-rattler-lifers/` contains the campaign brief, manifest, research, formula, prompts, routing, quality contract, run blueprint, site/lab source, fonts, artwork, proof scripts, and evidence.

Recommended consolidation:

- commit the brief, manifest, research, formula, prompts, routing, contracts, site/lab source, required licensed fonts, optimized production artwork, runners, and small verification records;
- retain only the minimum screenshots needed for the golden benchmark;
- move redundant screenshots and duplicate source exports to the private Drive evidence archive;
- keep university-affiliation disclaimers and public-use boundaries with the campaign.

### D. Generated proof and certification evidence

Keep locally and archive selectively; do not commit wholesale:

- `artifacts/website-delivery-swarm/`;
- `artifacts/site-studio-build-packets/`;
- `artifacts/autonomous-preview-bridge-certification-*`;
- `artifacts/portal-proof-bundles/`;
- `artifacts/portfolio-experience-20260819/`.

These directories contain screenshots, HTML builds, copied artwork, signed packets, ZIP files, stage journals, browser evidence, and repeated packet snapshots. The largest individual ZIP files are approximately 89 MB, 55 MB, and 33 MB. Many screenshots and packet files appear in both run output and copied Site Studio packet directories.

The audit found substantial byte-identical repetition across proof output, packet copies, and certification replays. Evidence should be retained by run ID and hash, but Git should not become the binary evidence store.

### E. Disposable generated cache

Safe to regenerate and exclude from Git:

- all `__pycache__/` directories;
- all `*.pyc` files;
- temporary test server/runtime output when present.

No material design, source, prompt, or customer record belongs in this class.

## Pilot inventory and purpose

- `broward-barbershop`: early three-direction working pilot and browser proof.
- `shay-tighten-up-your-locs`: Shay client proof lineage and Safe/Wild/OMG prototype material.
- `fort-pierce-black-church-showcase`: six-direction church benchmark.
- `bossy-nails-by-pri`: six-direction beauty-services benchmark.
- `good-ole-candy-lady-shop`: six-direction mobile commerce/community benchmark.
- `famu-corner`: six-direction information/experience benchmark.
- `palm-pepper-supper-club`: autonomous certification fixture.
- `the-set-club`: live-research/provider pipeline run and Site Studio packet fixture.
- `rattler-football-fan-portal`: research-first quality benchmark and packet fixture.

The pilot scenario, direction contract, prompt, builder, and review files are durable benchmark source. Generated run directories under `artifacts/` are evidence outputs.

## Privacy and sharing boundary

- 386 untracked text files contain one or more email addresses.
- Raw proof packets can include customer/request continuity identifiers.
- No credential-value pattern was detected, but that does not authorize public sharing.
- Drive archives containing raw operational evidence must remain private.
- Public portfolio material must use sanitized media and must not expose proof tokens, private share URLs, customer intake, or unpublished communications.

## Consolidation applied

The repository ignore policy now excludes:

- `artifacts/`;
- `__pycache__/`;
- `*.pyc`, `*.pyo`, and `*.pyd` through `*.py[cod]`.

This does not delete evidence. It removes reproducible and privacy-sensitive output from ordinary Git status so durable source can be reviewed deliberately.

## Remaining cleanup sequence

1. Review the small hooks, skills, backend service, frontend test, scripts, and architecture documents as one implementation lineage.
2. Run swarm unit, browser, contract, and campaign validation against the durable source.
3. Split the durable source into bounded commits: swarm engine; Site Studio bridge; portal/customer continuation; campaign baseline.
4. Create a sanitized evidence index containing run IDs, hashes, status, and private archive locations rather than committing every binary.
5. Upload the audit and sanitized evidence index to the private FAMtastic Drive knowledge area.
6. Archive only final/golden evidence packages; retain intermediate repair runs only when they document a unique failure or decision.
7. Delete nothing material until its final archive hash and location are verified.

## Current conclusion

The work is not random debris. It is a mixture of a substantial delivery-swarm implementation, campaign source, reusable benchmark pilots, and repeated local evidence packages. The correct cleanup is to commit the reusable engine and contracts, archive selected golden evidence privately, ignore reproducible run output, and preserve a small sanitized evidence index in Git.
