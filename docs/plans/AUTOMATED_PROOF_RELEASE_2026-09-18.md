# Independent QA release, not owner impersonation

Owner authorization: September 18 client-delivery plan. Routine green account-bound
three-proof work may proceed without another Fritz gate. Escalate scope, spending,
rights, security, missing merchant authority and repeated QA failures. Selection,
staging, client acceptance, settlement and final launch remain separate milestones.
Commercial exceptions are recorded by the commercial lane, not this service.

## Exact supported boundary

This release adds a trusted staff/worker service operation, **not a public route**.
Existing owner approval and all email renderers/bodies remain unchanged. No scheduled
process starts calling this method merely because the code is deployed. A separately
authenticated orchestrator must obtain independent review of the actual artifact set.
Do not manufacture green QA evidence or pass the generator as its own reviewer.

```php
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$context = $portal->websiteRequestAutomatedProofQaContext($requestId, $research);
// Read-only: context binds request/customer/campaign, three current file hashes,
// policy customer-delivery-green-v1 and normalized research_sha256.
$evidence = $context + [
  'producer' => 'automation:builder-run-identity',
  'reviewer' => 'automation:independent-review-identity',
  'scope_in_bounds' => TRUE,
  'exceptions' => [],
  'checks' => $independentlyRecordedChecks,
];
$receipt = $portal->releaseWebsiteRequestProofAfterQa(
  $requestId, $research, $evidence, 'automation:independent-review-identity', [
    'notification_key' => "website-request:$requestId:proofs:$campaignId:qa-v1",
    'recipient' => $exactVerifiedLowercaseEmail,
    'subject' => $approvedSubject,
    'body' => $approvedPlainTextMessage,
  ],
);
```

Research follows the existing normalization: overview; a/b/c direction_rationale;
sources; researched_at YYYY-MM-DD; optional market_signals/opportunities/growth_plan
(days_30/days_60/days_90) and research_lesson. Pass the same research to both calls.
Do not call the human research-save operation and attribute it to uid 1.

Required independent checks: desktop, mobile, accessibility, links, functional,
rights, claims, no_live_checkout, distinct_directions. Each is
`{passed: true, evidence_ref: "evidence:relative/path", evidence_sha256: "64-hex"}`.
The orchestrator retains actual evidence at those references; this gate validates
bindings and attestations, not the pixels or test quality by itself. The authenticated
caller is responsible for reviewer provenance. Missing/failed checks remain blocked.

## Atomic effects and idempotency

Within one database transaction:

1. Verify submitted account/request/campaign ownership, ready generation, exactly
   a/b/c and their current on-disk bytes under the campaign's own root.
2. Validate independent reviewer, exact research/artifact hashes, policy, QA and scope.
3. Reveal customer proofs using a compare-and-set from `owner_review` to `customer_ready`.
   `proof_approved_by_uid` is NULL: no human approval is invented.
4. Save normalized research with reviewed_by/review_policy metadata. The existing
   research table requires a non-null uid; 0 means no attributed human, not Fritz.
5. Queue the supplied personal message unchanged via existing `standard/v2` rendering,
   one immutable key, `max_attempts=1`. No generic proof mail is queued or sent.
6. Supersede only queued/retry owner-review reminders for this request, then retain
   the immutable automated decision (identity, policy, evidence and notification hashes).

An exact retry returns the original outbox and status; changed content, research,
current artifact, campaign or evidence is rejected, not silently overwritten.
Already selected status stays selected on an exact retry. A late database/outbox
failure rolls back the reveal and research. No payment, fulfillment, SMTP, DNS,
hosting, final acceptance or launch call exists in this operation.

The receipt distinguishes `email_sent_by_this_operation: false`. The delivery lane
must send only the returned exact outbox row through the existing dispatch primitive.
Uncertain SMTP outcomes require reconciliation before resend; provider acceptance
is not inbox placement or readership. This service cannot guarantee external
exactly-once SMTP delivery when a provider accepts then the connection is lost.

## Validation and release

`AutomatedProofReleaseTest`: 15 tests, 64 assertions, real in-memory Drupal SQLite
queries and filesystem artifact hashes; mocked entity lookups. Covers happy path,
duplicate release, no human attribution, personal standard/v2 message preservation,
cross-account/campaign/request, stale policy, self-review, missing/failed evidence,
scope exception, changed research/artifacts, wrong recipient/key, changed retry and
late failure rollback. **Locally proven**, not MySQL concurrency, SMTP or live proof.

```sh
FAMTASTIC_BACKEND_VENDOR=/absolute/existing/backend/vendor \
php /absolute/existing/backend/vendor/phpunit/phpunit/phpunit \
  --bootstrap scripts/automation-test-bootstrap.php --no-configuration \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/AutomatedProofReleaseTest.php
```

The bootstrap reads an existing dependency tree without installing/copying it or
booting its site. No database migration is needed for this gate. Deploy exact
reviewed source through the canonical backend script with main-lane coordination.
Do not enable the broad lifecycle scheduler as part of this gate release.

## Separate activation gaps

Selected continuation repair integration is a separate source commit. It preserves
authored source but has only bounded static capabilities, not ecommerce generation.
The running Studio, target bindings, signed callbacks, lease worker, cloud credentials
and laptop-unavailable proof each need their own evidence. This gate alone does not
make the full pipeline unattended and does not select a customer direction.
