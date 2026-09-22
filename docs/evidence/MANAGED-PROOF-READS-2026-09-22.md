# Managed proof reader verification — September 22, 2026

Source-only; not merged, deployed or activated. Runtime source was independently
reviewed; no concrete blocking defect was found in the bounded reader/consumer
slice. Follow-up tests cover an ordinary asset response and real Symfony route
matching. Production release authentication and installed browser/session behavior
remain unproved. Contract: `../contracts/MANAGED-PROOF-READS-V1.md`.

Private raw evidence root:
`/Users/famtastic-fritz/Development/FAMtastic/worktrees/autopipeline-recovery.ggJXc1`.
All runs used `run-guarded.mjs`, SHA-256
`150e3feaa6f0c02cbae46a032cd92ca8a486a9db5cb278f540186edd38b0cfd4`.
Network was denied except bounded local test services, the authoritative Studio
port was denied, authoritative config/data were inaccessible to tests, and before/
after protected inventories were identical. No model/provider/customer send,
production DB, service restart, deployment or cloud mutation occurred.

## Exact runs (overlapping counts, do not sum)

| Receipt directory | Result | Time |
| --- | --- | --- |
| `evidence-managed-reader-consumers-first.JnRpLn` | 192 tests / 5,071 assertions | 13.483s suite / 13.883s guarded |
| `evidence-managed-reader-consumers-regression.CkjZz0` | 1,078 / 10,981; portal DNA 34/34 | 26.861s / 27.312s |
| `evidence-managed-reader-final-regression.MQnqVt` | **1,079 / 11,002**; portal DNA **34/34**; whitespace pass | **25.516s / 25.945s** |
| `evidence-managed-reader-canonical-journey.JWsgxe` | canonical disposable Drupal journey root + both children pass | 42.073s guarded |

PHP 8.5.9 / PHPUnit 11.5.56, full module unit suite without exclusions. Same 68
existing PHPUnit deprecations, no failing/skipped tests, final allocator summary
68 MiB. This is not the separately measured maximum package allocator peak.
Canonical frontend runner uses Node 22; Studio is paired clean at
`0f2a656ba3403f8c2a2f40afeba92d649cee872b` (runtime unchanged by its documentation).

Designs baseline `4619c073180d6c514ac703b21c4ddb8a663268b0` plus dirty-diff hashes:

- First focused/full and canonical run:
  `a3e5d9f76e3bbb1f56ce0152ee4392ce2cc09f01e5fe27dbd6aa58939ac09b01`.
- Final full suite after HTTP image/matcher test additions:
  `07085353faf5062d93ceaf921e99a934e8e7ce3456fbe501d8d57c0d12176f63`.

Only test assertions changed between these runtime runs; documentation follows
verification. The final source commit is the commit introducing this receipt.

## Canonical installed regression, not managed automation

Run `fresh-customer-proof-20260922T114047Z-8816` retains root `evidence.json` and:

- `proof-runs/journey-essential_199-1790077273-9520/evidence.json`
- `lifecycle-runs/1790077283-10907/evidence.json`

All five root assertions, 23 customer-journey booleans plus exactly three proofs,
and all 14 lifecycle booleans are true. Actual transports captured four core
messages (including the isolation probe) and 34 transactional messages. The
lifecycle's nine “sent” notifications are memory-transport results, not SMTP.
The test uses the legacy owner-review fixture (`owner_proof_gate: true`), synthetic
payment/domain data and local deployment; it does **not** establish unattended
managed production/QA/release or a real cloud/laptop-off journey. Public CMS
snapshot replay supplies frontend build data only, not fake proof-job results.

## Account-level release gates, freshly checked

GitHub run **35720982600**, Designs source4619c073, ran no frontend/security/backend
steps. Backend check106723567868 reports that the job did not start because the
account is locked by a billing issue. This is not a source-test failure or CI pass.
No billing or required-check setting was changed. Cloud CLI remains without active
account/project in the latest local check; authorization has been requested.

Local Linux image build evidence is now real and recorded in Studio's
`docs/evidence/PHASE2-LINUX-IMAGES-2026-09-22.md`; the owned VM is stopped. It does
not resolve cloud access, create-only/containment review, canary or complete
customer-delivery gates. Work can continue locally on independent release and the
existing Mac producer path while those account-level requirements are resolved.
