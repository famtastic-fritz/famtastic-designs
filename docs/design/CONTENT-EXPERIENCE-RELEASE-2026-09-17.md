# Content Experience production release — September 17, 2026

Owner authorization: **“ok approved push live !”**, following review of the
six-family desktop/mobile gallery. Approved implementation: `5d98e4d1` and `c004c589`.

Scope: the existing 17 public Packages, Solutions/Services, About and Contact
routes; reusable presentation primitives and their two documented routing/state
safety corrections. No new content rewrite, logo artwork, backend/database release,
customer-site deployment, real intake, email, payment or social-account update.

## Pre-release evidence

- Clean `codex/content-experience-web-basics`; fetched `origin/main` has no
  incoming commits. Normal fast-forward integration, never a force push.
- Pre-release Build DNA validated 5 stages and 26 source/acceptance hashes.
  Its expanded-review checkpoint remains in Git at `c004c589`; release evidence
  is appended to the active family ledger, not the frozen parent Web Basics proof.
- Current Node 22 build and shared-asset checks pass; dependency audit found no
  vulnerabilities and the tracked-source secret-pattern check passed. Prior 68 responsive route
  checks, Web Basics regressions and non-sending form fixtures are retained.
- Before release, both live markers were `9d4e000d4294461808f5a69068af778320f6b568`.
  Only the frontend marker changed. No database migration was needed or run.
- Existing CMS performance promises and response-time inconsistency remain
  separate copy-review concerns, not new claims validated by this visual release.

## Apply and live acceptance

**Released and verified.** Normal fast-forward push from
`codex/content-experience-web-basics` to GitHub `main`, then canonical
`./scripts/deploy-frontend-godaddy.sh` preflight and `--apply`, both exit 0.

- Frontend commit: `71620bf12a56289a7c99fa753b196aa92441a2ff`.
- Deployed at: `2026-09-17T20:10:05Z` (4:10 PM Eastern).
- Node: `v22.23.2`; server install audit: 0 vulnerabilities.
- All 216 generated route shells matched the server build; root `.htaccess`,
  release marker, compiled assets and HTTP/MIME checks passed.
- Backend remained `9d4e000d4294461808f5a69068af778320f6b568`, deployed at
  `2026-09-17T16:59:13Z`; no backend deployment or database migration.
- Follow-up documentation, QA instrumentation and receipts do not change any
  `frontend/` file relative to the live commit and do not trigger redeployment.

Public JS, CSS and canonical logo were independently fetched with HTTP 200 and
compared byte-for-byte via SHA-256 to the approved local build:

| Public asset | SHA-256 |
| --- | --- |
| `assets/index-xXt6hQKB.js` | `36e3257d207d4b7f09d53097f3e6fb46a57841d3c08081619c230fa6e1f0d603` |
| `assets/index-C4xyUebf.css` | `4050eeefa8c5df2ca1ce095a13cbfe854998b0f22e97bfd1b72b5f3469e444ae` |
| `brand/famtastic-designs-logo-v1.png` | `ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950` |

## Browser acceptance

`node scripts/test-content-live.cjs` passed **50 live cases** at 390/1440px:
apex homepage plus all 17 enrolled routes; www homepage plus one representative
of each of the six families. Completed `2026-09-17T20:14:01.683Z`.

- Real anonymous public CMS data, no application data mocks; correct recipe,
  one H1, loaded logo, safe research destinations, package-specific billing,
  guides, catalogue counts, F/A/M definitions and no horizontal overflow.
- No page exceptions, console errors or failed static assets. Both hosts returned
  compiled JS/CSS with successful HTTP/MIME checks (4 host/asset combinations).
- Contact fields and empty-form validation checked without submission; no
  unearned success crown. Zero application writes or blocked application attempts.
- Three telemetry requests were locally acknowledged with 204 and not sent to
  analytics. Production analytics/consent settings were not changed.
- Initial attempt failed because intentionally aborted analytics POSTs produced
  `net::ERR_FAILED`; the QA helper was corrected to suppress only the two exact
  known collection endpoints while retaining all application-error assertions.
  This was a test-instrumentation correction, not a deployed frontend change.

Portable result: `docs/evidence/content-experience-production/browser-results.json`.
Full and hero desktop/mobile screenshots: `.local-email-preview/content-live/`
(ignored local artifacts). Actual live Web Basics/mobile, About/desktop and
Contact/desktop screenshots were visually inspected against the approved direction.
The separate logo/navigation regression passed 320/390/768/960/1100/1440px;
receipt: `.local-email-preview/content-live-logo-logo-results.json`.

These checks establish anonymous production presentation, not full accessibility
certification, authenticated customer workflows, successful intake/mail/payment,
inbox delivery or email-client compatibility. No customer communication occurred.

## Hosted CI limitation

[GitHub Actions run 35268560051](https://github.com/famtastic-fritz/famtastic-designs/actions/runs/35268560051)
could not start: “The job was not started because your account is locked due to a
billing issue.” Frontend, backend and secret jobs executed zero steps. No hosted
CI pass is claimed. Local checks, server build and live browser acceptance above
are separate evidence; no account or billing settings were changed.

The existing large-chunk Vite warning remains (JS 857.18 kB, gzip 248.25 kB;
CSS 199.37 kB, gzip 38.17 kB). This release does not claim a performance improvement.

## Rollback receipts

On `p3plzcpnl497512.prod.phx3.secureserver.net`, retain both archives:

- Canonical root/assets/config/marker backup:
  `/home/xrdj7j99xhzt/backups/famtastic-frontend-20260917T200612Z-71620bf12a56289a7c99fa753b196aa92441a2ff.tgz`.
- Supplemental previous 216 generated route shells, captured before apply:
  `/home/xrdj7j99xhzt/backups/famtastic-route-shells-20260917T200609Z-before-71620bf1.tgz`.
  SHA-256: `df35f15dbaa1476694866b372ea7906c5cdf4ef6594ddb0f40fb2fc5a49c5a13`.

The second archive complements the canonical backup because nested generated
HTML must roll back with root/assets. Follow `docs/FRONTEND_DEPLOYMENT.md`, inspect
both manifests and restore both exact archives for a complete rollback; then
recheck marker, assets, nested routes and browser behavior. No rollback was needed
or exercised. No destructive cleanup of customer files was performed.

## Documentation and handoff

Updated root design.md, canonical design/content guides, AGENTS.md, rollout
status, changelog, capability registry, both learnings files and active Build DNA.
Dated Drive mirror: `2026-09-17-content-experience-production-release.md` in the
configured `FAMtastic/famtasticdesigns.com` folder. Local file read-back is proof
of the mirror write only; remote Drive synchronization is not independently verified.
