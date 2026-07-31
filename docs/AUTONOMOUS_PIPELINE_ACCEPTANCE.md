# Autonomous pipeline acceptance record

## Implemented and locally proven

- lawful-source CSV lead ingestion, provenance, normalization, scoring,
  suppression, and deduplication;
- exactly three isolated Site Studio proofs through local and signed async
  adapters;
- campaign-specific approval, staged email, tracking, unsubscribe,
  bounce/complaint handling, and default-deny real transport;
- versioned $199 and $499 offers, versioned terms acceptance, exact amount
  verification, and signed idempotent Stripe-style webhooks;
- paid intake, verified image upload, package-specific revisions, approval, and
  immutable release fingerprint;
- isolated customer release, backup, atomic promotion, verification, and
  rollback;
- customer-owned domain records, delegated authorization, read-only DNS/TLS
  evidence, and no automated purchase or mutation;
- twelve included hosting months beginning at verified launch, separate
  recurring consent, month-13 renewal, retry, cancellation, and suspension;
- token-scoped customer lifecycle status and campaign/source/revenue analytics;
- GitHub acceptance workflow for the canonical React frontend and Drupal
  backend.

## Deterministic commands

```bash
scripts/acceptance-autonomous-pipeline.sh
```

The suite runs:

```bash
scripts/e2e-lead-import.sh
scripts/e2e-site-studio-callback.sh
scripts/e2e-email-campaign.sh
PORT=8899 scripts/e2e-proof.sh
PORT=8900 PACKAGE=business_499 EXPECTED_AMOUNT=49900 EXPECTED_REVISIONS=2 scripts/e2e-proof.sh
scripts/e2e-customer-deployment.sh
scripts/e2e-domain-lifecycle.sh
scripts/e2e-hosting-lifecycle.sh
scripts/e2e-analytics.sh
npm --prefix frontend audit --audit-level=high
npm --prefix frontend run build
composer --working-dir=backend audit
backend/vendor/bin/phpunit -c backend/web/core/phpunit.xml.dist backend/web/modules/custom/famtastic_pipeline/tests/src/Unit
```

## Production and legal gates

Completion of the software does not authorize a live action. The following stay
blocked until a human explicitly approves the exact action:

- real outreach;
- live Stripe charges or subscription creation;
- domain purchase or DNS mutation;
- paid cloud resources;
- merge and production deployment.

The website service terms are an operational draft and require legal review
before live sales. The monthly hosting price is deliberately not hardcoded; it
must be approved and shown in the separate recurring consent.

Production release must use the exact `main` SHA and the Git-tracked frontend
and backend deployment scripts. Both scripts create backups and record release
evidence. No agent-specific deployment shortcut is permitted.
