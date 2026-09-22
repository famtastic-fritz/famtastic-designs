# Signed worker principal verification — September 22, 2026

Source integrated, locally verified, not activated or deployed. The existing
worker controller now uses the existing registry/HMAC/nonce authority via a scoped
principal. No new credential scheme, secret, enable flag, mail system or scheduler.
Contract: `../contracts/WORKER-REQUEST-PRINCIPAL-V1.md`.

## Independent review and test fidelity

Review found a concrete replay gap: a conditional INSERT-ignore could leave a
matching row from another authenticated request, letting the loser adopt it.
`rememberNonce` now requires exactly one affected row from the actual prepared
INSERT before cleanup and exact expiry readback. A deterministic interleaving
authenticates a real second signed request after the first caller's precheck;
the winner retains its nonce/principal, the ignored loser receives none. Targeted
independent re-review found this P1 closed. This is SQLite service evidence, not
multi-process MariaDB contention or installed reverse-proxy/TLS evidence.

Tests use actual HMACs, the real coordinator/SQLite nonce table and paused time.
They cover exact wire bytes, bounded payload/time, mutable request isolation,
foreign/cloned principals, strict review request IDs, expiry/revocation/rotation,
shared reviewer-key rejection, caller transactions/replicas, failed/ignored/altered
nonce persistence, signed controller-to-release composition and fresh-nonce exact
historical retry without duplicate reveal/outbox. Retained visual QA/provenance
and downstream fixture seams remain explicitly synthetic; separate keys are not
proof that a real independent reviewer inspected the site.

## Retained guarded runs — overlapping totals, never sum

Private root:
`/Users/famtastic-fritz/Development/FAMtastic/worktrees/autopipeline-recovery.ggJXc1`.
Guard SHA256: `150e3feaa6f0c02cbae46a032cd92ca8a486a9db5cb278f540186edd38b0cfd4`.
External network/MTA and authoritative configuration/data access were denied.
Every listed completed run retained identical protected before/after inventories.

| Receipt directory | Result | Measured time |
| --- | --- | --- |
| `evidence-signed-worker-managed-release.nNTM7c` | 173 tests / 1,555 assertions, two fixture-hook failures, not a pass | 15.573s suite / 15.970s guarded |
| `evidence-signed-worker-managed-release-repaired.4kVnXw` | 173 / 1,561, fixed actual SQLite hook; before final nonce fix | 15.329s / 15.438s |
| `evidence-signed-worker-final-regression.LRGbDo` | 1,313 / 13,967, two old fixture DI/clock failures, not a pass | 49.005s / 49.288s |
| `evidence-signed-worker-integrated-regression.cOySwP` | 1,313 / 13,983, fixtures fixed; before independent nonce fix | 51.019s / 51.408s |
| `evidence-signed-worker-nonce-winner.WTobxy` | 235 / 1,841 focused, final row-count/interleaving fix | 15.430s / 15.560s |
| `evidence-signed-worker-nonce-final-regression.AVKm6U` | **1,314 / 13,990**, final full module | **50.986s / 51.310s** |
| `evidence-signed-worker-final-canonical.zNRUEB` | actual disposable canonical Drupal journey; root and both children pass | **37.675s guarded** |

Initial post-nonce hooks targeted a query path the real SQLite INSERT did not use;
the final fixture brackets the actual prepared statement execution. Old shared-
claim controller fixtures lacked the new service/paused clock and were corrected
with the real authenticator and distinct synthetic keys. No product assertions
were relaxed. Early green runs predate the independently found replay fix.

Final full run: PHP8.5.9 / PHPUnit11.5.56, no failures/skips, same68 existing
deprecations; portal DNA **34/34**, email presentation **86**, whitespace and
changed PHP syntax pass. Suite allocator summary76MiB; maximum-package case
separately captures **187,547,648 bytes**, 21files / 21,064,260content bytes and
1.828s through preparation/verification/four role reads. Do not report the smaller
suite summary as the workload maximum.

Both final runs use Designs base
`c1a335f11b4d440dc4b90219280888eedf26064a` and exact dirty-diff SHA256
`1c4487fd35a06f1aa90f78649283343b2a41a82c68ef97c97b900f626e1c71d1`.
Studio is paired clean at `0f2a656ba3403f8c2a2f40afeba92d649cee872b` and unchanged.
Only documentation was added after these final runtime runs. The final source
commit is the commit introducing this receipt.

## Canonical regression is still the legacy customer journey

Run `fresh-customer-proof-20260922T125127Z-21800` retains root `evidence.json`, plus:

- `proof-runs/journey-essential_199-1790081511-22462/evidence.json`
- `lifecycle-runs/1790081520-23809/evidence.json`

Both actual child files equal the root's embedded records. All5root checks,
23journey booleans plus exactly3proofs and14lifecycle booleans pass. Required
Commerce/correlated/Evidence markers were read from actual customer-proof.log;
the wrapper log is empty, not the proof source. Four core messages (including
probe) and34transactional messages were captured locally. Lifecycle's9 “sent”
rows mean memory transport only. No external message or provider call occurred.

The runner uses the legacy owner-review fixture, synthetic payment/domain and
local deployment. It does not prove fresh managed unattended proof generation,
QA, notification, selection, staging or a laptop-off cloud worker. Public CMS
snapshot replay supplies frontend build data only.

## External gates and remaining implementation

PR42 head c1a335f1 hosted run **35727309714** has three failed jobs with empty
step lists; backend check106744009593 says the account is locked for billing.
That is not a source-test failure or CI pass. Google Cloud CLI has no active
account or configured project. Account resolution/authorization was requested;
no billing, CI, credential or cloud settings were changed or bypassed.

Still required: retained independent QA evidence authority and bounded review
consumer; managed DI registration; released portal metadata and exact winner
adoption; real Mac creative routine/provenance; installed controlled managed
journey with interruption/duplicate/concurrency proof; cloud auth, create-only
containment and laptop-unavailable canary under shared ownership and budget.
No merge, live service restart, customer send, provider call, production/customer
deployment or cloud mutation occurred. Owner checkouts and authoritative data
remain untouched. Source tests do not authorize turning workers on.
