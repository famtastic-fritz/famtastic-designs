# Recovered local verification — September 22, 2026

**Local source and regression evidence only. No activation or deployment.**

The former `/tmp/famtastic-*` worktrees and raw receipts were absent at the
continuation check. Their reported results remain historical; their files are
not currently inspectable. The reason for their disappearance is unknown.
No missing worktree records were pruned. Tracked work was recovered from the
existing Git commits and pushed review branches, without editing owner checkouts.

The new owned work/evidence root is
`/Users/famtastic-fritz/Development/FAMtastic/worktrees/autopipeline-recovery.ggJXc1`.
Raw private receipts stay outside Git. This document records their sanitized facts.

## Exact source and isolation

- Designs: `5734fd75d11c0c70f57b5de6f63f4ea70a884757`, plus the core-mail test-harness
  repair whose pre-documentation `git diff HEAD` SHA256 is
  `3ed29bab3b7ad536246b08ee3143a18a04e912d88071c407916ce0fec82df4c9`.
- Studio: clean `754859e24d11fb2e4f81458db8453ff1b6b0c6b1`.
- Task-local guard SHA256:
  `150e3feaa6f0c02cbae46a032cd92ca8a486a9db5cb278f540186edd38b0cfd4`.
- Guard denies external network, canonical runtime port 3400, credentials and
  authoritative data access, protected checkout writes, sendmail and postdrop.
  Tests permit loopback, enforce a 200 MiB disk floor and 600-second deadline.
- Before/after protected inventories match for every completed run below.
  No production configuration, account, payment, provider or message was changed.

## Executed receipts

| Run beneath the owned root | Actual result | Timing |
| --- | --- | --- |
| `evidence-restored-php-regression.VfpqeF` | 983 tests / 6,731 assertions; no failures/skips; same 68 existing PHPUnit deprecations | 16.575s suite; 17.361s guarded |
| `evidence-core-mail-canonical-journey.jaIzc6` | Exact installed skill runner, root and both child JSON receipts inspected; all declared checks true | 62.056s guarded |
| `evidence-restored-studio-pair.E1VblP` | 1,588 tests / 135 files; lint and both execution proofs pass; whitespace clean | 372.62s suite; 376.288s guarded |

PHP used 8.5.9 / PHPUnit 11.5.56. Its 66 MiB final suite summary does not replace
the separately measured package-sizing allocator peak of 174.86 MiB. Studio used
Node 24.19.0; the frontend build used repository-required Node 22.23.2.
Both synthetic execution receipts explicitly deny external effects. Their model
and cloud-operation counters are fixture counters, not actual providers or GCP.

The fresh canonical journey is
`canonical-journey-evidence/fresh-customer-proof-20260922T110852Z-3830/evidence.json`.
Its child receipts cover three proofs, account ownership, selection, Commerce
binding and lifecycle operations. **It still contains the existing owner-review
fixture. It does not prove the managed unattended QA/release flow.** No browser,
real checkout, public deployment or SMTP acceptance is claimed.

## Core mail isolation repair

FAMtastic's memory transport does not capture Drupal core account mail. The
fresh-sandbox harness now configures the actual core `test_mail_collector` only
after verifying the disposable Drupal root and SQLite settings. The interface
map is replaced, not merged with potentially inherited SMTP overrides. A real
collector write probe verifies capture before the journey; the map and collection
are checked again afterward and recorded in `core-mail-safety.json`.

The inspected run captured four core messages (including that probe) and 34
existing FAMtastic transactional messages. No mail bodies are copied into the
sanitized receipt, and no production mail renderer or transport changed.

For the frontend's public CMS/SEO build input, six anonymous public JSON responses
(114 nodes) were captured with response hashes and replayed at a loopback-only
server. This is build input, not a replacement proof/job fixture. Snapshot:
`public-cms-snapshot.ejsZWS`. All customer-journey work then ran inside the guard.

## Reproduction and remaining gates

Use the existing `prove-famtastic-customer-journey` skill and its canonical
`scripts/run-proof.sh <isolated-designs-checkout>` entrypoint, not a smaller
substitute test. Select Node 22 for the frontend and fresh SQLite/private paths;
retain all root/child JSON evidence before reporting success. Run Studio with
the paired Designs dispatch, portal and PHP harness paths, one test worker,
followed by lint, `prove:execution` and `prove:execution:phase2`.

The receipt-backed importer is implemented and locally tested, but unregistered.
Managed protected reads, independent QA/release, real Mac creative provenance,
selection continuation and their installed complete journey remain unfinished.
The Mac's existing attended workflow is preserved. No new worker is enabled.

The cloud CLI still has no active account or selected project. Hosted CI was
last observed account-billing-locked; that old observation was not refreshed in
this run. Neither is permission to bypass required checks. The known two
moderate development-only Vitest/mocker findings remain; a fresh production-only
audit reports zero. No forced major upgrade, cloud creation or paid call occurred.
An actual laptop-unavailable run is still required before claiming independence.
