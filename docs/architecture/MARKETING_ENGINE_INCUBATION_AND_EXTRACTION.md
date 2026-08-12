# Marketing engine incubation and extraction

Date: 2026-08-12
Status: Accepted

## Decision

The FAMtastic Marketing Engine will incubate inside `site-famtastic-designs`
while the 17-day campaign proves its workflow. The code must be built behind a
deliberate boundary so it can later move into a standalone repository without
moving FAMtastic customer data, credentials, product truth, or brand records.

## Why it starts here

The first proof needs the real capability registry, products, offer terms,
content library, Drupal lead operations, Commerce conversion events, GA4
attribution, email safeguards, and FAMtastic brand. Keeping those relationships
in one repository reduces integration theater and makes the campaign evidence
meaningful.

## Boundary

```text
marketing/engine/                 reusable schemas and provider contracts
marketing/brands/famtastic/       replaceable FAMtastic brand configuration
marketing/campaigns/              FAMtastic campaign manifests and evidence
scripts/                          repository adapters, preflight, and runners
backend/                          Drupal-specific records and operations
docs/marketing/                   FAMtastic doctrine, decisions, and runbooks
```

Reusable engine code may know about abstract brands, campaigns, approvals,
assets, providers, channels, and measurements. It must not import Drupal code,
hardcode FAMtastic prices, contain customer records, or read production secrets.

FAMtastic adapters may translate canonical Drupal/product/capability records
into the engine's stable inputs. Financial, consent, customer, and operational
authority remains in Drupal.

## Extraction gates

Create a standalone repository only after all gates pass:

1. One complete 17-day campaign has durable evidence from brief through
   measurement.
2. At least two social channels pass private/draft submission, public approval,
   delivery verification, and rollback/deletion proof.
3. Email, UTMs, GA4, Drupal lead attribution, and conversion reporting join on
   the stable content ID.
4. Approval enforcement, idempotency, bounded retries, failure alerts, and
   duplicate protection are proven.
5. Shay, Claude, and Codex can execute the same documented workflow.
6. A second mock brand passes without editing reusable engine source.
7. The extraction manifest identifies every portable and retained path.
8. No secret, customer record, OAuth token, provider credential, or FAMtastic
   private business record appears in the portable set.

## Standalone target

The future repository may be named `famtastic-marketing-engine`. FAMtastic
Designs will consume a versioned release while retaining its brand, campaign,
customer, Commerce, analytics, and credential configuration in this repository
or its deployment secret store.

Extraction is a proof milestone, not a calendar milestone. Until the gates pass,
duplicating the engine into another repository would create two sources of truth.

