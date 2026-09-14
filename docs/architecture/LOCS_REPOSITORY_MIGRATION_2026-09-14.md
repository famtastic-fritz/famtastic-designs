# Tighten Up Your Locs repository migration ledger

## Canonical ownership and release

- Private customer repository: [famtastic-fritz/site-tighten-up-your-locs](https://github.com/famtastic-fritz/site-tighten-up-your-locs).
- Independent checkout: `/Users/famtastic-fritz/Development/FAMtastic-Repos/site-tighten-up-your-locs`.
- Live customer source: `e25f7f1a92cdae2093a760481d100ea0b32a111e`.
- Documentation/receipt revision: `c0a20f5566996d19301854c05985bf79f676e463`.
- [Full release, fresh-clone, state-preservation and rollback evidence](https://github.com/famtastic-fritz/site-tighten-up-your-locs/blob/c0a20f5566996d19301854c05985bf79f676e463/docs/evidence/REPOSITORY-RECONCILIATION-2026-09-14.md).
- Original agency source: `e261a10084f49b7e32ca70e4de63e51f495c60f1`; original deployed application/public source: `576b190ab28d8cef15b82982c4429055880058e1`.

The source repository is separate from FAMtastic Designs. Hosting, dedicated database,
credentials, owner account, private storage, cron and public/admin URLs are preserved.
The agency owns its customer-delivery records, not the customer's runtime or data.
Both older Shay prototype repositories were left unchanged and remain historical.

## Scoped history and removed duplicates

Extraction retained 28 customer-scoped commits without rewriting agency history.
Original `e261a10084f49b7e32ca70e4de63e51f495c60f1` maps to customer historical
`7bc3b3f6c448a405772d0fb104059efe5e282476`. The complete original-to-filtered mapping
and explanation are in the customer's `docs/provenance/scoped-commit-map.txt` and
`docs/provenance/EXTRACTION.md`. Original Build DNA references remain immutable.

| Former agency location | Before cleanup | Canonical customer location | Agency result |
| --- | ---: | --- | --- |
| `customer-apps/tighten-up-your-locs/` | 120 tracked files | `application/`, `gateway/`, `ops/`, `ops/history/`, root/docs | 119 duplicate files removed; README replaced with pointer |
| `website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/` | 30 tracked files | `public/`; QA in `docs/evidence/` | 29 duplicate files removed; README replaced with pointer |
| Pilot briefs/design/learning records | Historical evidence | `docs/briefs/` | Retained in agency with historical notice |
| `docs/design/proofs/tighten-up-your-locs*/` | Historical proofs/assets | `docs/history/proofs/tighten-up-your-locs*/` | Retained as dated agency delivery evidence, not build targets |
| Architecture and September 13 research | Dated decisions | `docs/architecture/`, `docs/research/` | Retained with explicit current-ownership correction |

The 148 removed tracked files and two replaced README contents are recoverable from
the exact original agency commit above. All 150 original file blobs were also verified
present in the independent repository's retained Git objects/history. For a read-only recovery, use
`git show e261a10084f49b7e32ca70e4de63e51f495c60f1:<original-path>` or inspect that
revision in GitHub. Do not restore working duplicates into agency main. Ignored local
dependencies/private test state were not deleted. Original production data/backups and
the agency legacy records were not touched.

The historical proof packager now permits only `--check`, with explicit historical
output. Every write/output invocation rejects before reading assets or creating a file.
Its original implementation remains in agency Git history. New customer source, proof
generation and deployment must start from the independently verified repository.

## Acceptance before removal

- Fresh remote clone outside all agency/studio checkouts installed its own npm/Composer
  locks and passed 62 Node tests, 63 PHP tests / 383 assertions, browser QA at
  390/768/1280, source-repository contract checks and production packaging.
- Foundation contract/scaffold 1.0.0 is vendored in the customer repository, with actual
  private remote verification. Full source, design/research/conversation records, safe
  environment examples, asset provenance and release/rollback tools are present.
- All 88 runtime source hashes and 14 public files matched the preceding live source.
  Source identity in the app/public receipts now points to the customer's exact commit.
- Application package SHA256: `1666833f9d164ec3a0b212131eb9fb8d25caab71c13333f836f26876b617bafe`.
- Public package SHA256: `0398c9f851767ea0ec3a62b10f629d0cc7e4fa8692093d766d997d6e04adf132`.
- Same-host release completed September 14 at 18:23 UTC. All 11 captured table
  count/digests and private config/storage matched immediately across installation.
  Later only the existing minute scheduler's newsletter mutex version advanced;
  the other 10 captured table digests and private settings still matched.
- Actual guarded code rollback to previous source and restoration to the new source
  passed at 18:24 UTC. This proves code rollback, not database restoration.
- Live apex/www checks passed 28 file hashes, nine canonical redirects, two private
  API 401 responses and branded login 200. Responsive browser checks observed automatic
  carousel advancement, four dots/no arrow bar, booking and unchecked newsletter consent.
- Focused tracked/history secret scans and dependency audits passed. No migration,
  credentials/account/DNS change, legacy reimport or customer email occurred.

GitHub Actions jobs did not start because of an account billing lock (runs 34879887297
and 34880143109); local fresh-clone gates passed, hosted CI is not claimed passing.
Personal owner sign-in, inbox/read receipt, database restore drill, search rankings and
new external calendars/payments/SMS/classes remain separate, unclaimed acceptance.

## Knowledge and future work

Site facts and future changes belong to the customer repository. Reusable source,
installation recipes and tests belong to Component Studio; rights-scoped media catalogs
and production recipes belong to Media Studio. Universal independent-repository rules
belong to FAMtastic and must be consumed by all builders; the ecosystem directory is
`config/repositories/catalog.v1.json` in the FAMtastic repository. This agency retains
only delivery history and verified links. Drive mirrors committed records through the
coordinating reconciliation task; do not claim a cloud copy without its read-back receipt.

This cleanup changes repository ownership/documents and retires a local historical
packager write path. It performs no further production deployment or customer message.

Cleanup acceptance: `node --test scripts/locs-repository-ownership.test.mjs` passes five
tests: historical read-only proof checking; rejected absent/output/mixed arguments with
existing target preservation; only the canonical pointers in the agency Git index; and
readable original source history; plus ignore protection for old local/private test state
and retired source paths. Syntax, staged whitespace and all 34 Portal Design DNA checks pass. Agency
`frontend/` and `backend/` have no changed files in this cleanup.

The verified owner-only Drive conversation archive, exact hashes and cutoff are recorded
in root `CONVERSATIONS.md`. Archive confirmation was supplied by the coordinating task
after cloud read-back; the archive does not claim messages beyond its recorded cutoff.
