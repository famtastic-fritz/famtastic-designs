# Marketing engine split status

Date: 2026-08-20
Status: incubation continues; no standalone repository created

## Decision

Do not split all of `marketing/` into a new repository. That would separate
campaign source, capability truth, customer operations, Drupal adapters,
analytics, and evidence from the system that gives those things meaning.

The only portable seed today is:

```text
marketing/engine/
├── README.md
├── schemas/campaign-manifest.schema.json
└── postiz/compose.override.yaml
```

It is a contract boundary, not a finished standalone product.

## Keep in FAMtastic Designs

- `marketing/campaigns/**`: brand work, routes, source media, receipts, Drive
  references, Build DNA, and case-study evidence.
- `marketing/brands/famtastic/**`, local model policy, and environment
  examples: business-specific configuration.
- `backend/**`, `frontend/**`, `docs/marketing/**`, campaign scripts, and
  publishing adapters: product, customer, consent, attribution, and operational
  truth.
- Credentials, recipient data, cookies, private offers, payment data, and
  customer Build DNA: never portable.

## Required gates before extraction

1. Prove one complete lifecycle with approval, delivery, attribution, retry,
   alert, and rollback evidence.
2. Prove two controlled channels and a second neutral mock brand without
   editing reusable engine source.
3. Generalize model routes and the manifest identifier; inject brand/provider
   configuration rather than hard-code it.
4. Add neutral fixtures, engine tests, a provider-result contract, approval
   state contract, and portable run ledger that references—not owns—the core
   Build DNA record.
5. Extract only from a clean committed source using
   `git subtree split --prefix=marketing/engine`; preserve lineage and do not
   copy campaigns into the future engine repository.

Until those gates pass, FAMtastic remains the sole source of truth and the
engine remains a versioned incubating boundary here.
