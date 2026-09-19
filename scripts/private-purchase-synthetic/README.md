# Synthetic private-purchase native fixture

Test-only module and source fixture. Nothing in this directory belongs in the
deployed `famtastic_pipeline` module. The production authority map is unchanged.

The parent-owned `scripts/test-selected-staging-drupal.sh
--private-purchase-synthetic` mode copies `famtastic_private_probe/` into the fresh
sandbox's `backend/web/modules/custom/`, enables it with Commerce Cart and Stripe,
then invokes `scripts/test-private-purchase-synthetic.php` twice:

1. Default phase: refuses nonempty business/native payment state, seeds wholly
   synthetic records and runs the first-process assertions.
2. `PRIVATE_SYNTHETIC_PHASE=verify`: a fresh Drush process reloads the exact binding
   and native order, compares executed hashes and verifies no additional records.

No authority is read during module installation/container alteration. Before the
first service access the seed exclusively creates this fixed 0600 file:

`<SELECTED_DRUPAL_SANDBOX>/backend/private/private-purchase-synthetic.json`

There is no authority-path environment/settings/HTTP selector. The file contains
only a synthetic run UUID, exact authority map and synthetic scope; no credentials,
passwords or user-session tokens. The service provider changes only
`famtastic_pipeline.private_purchase_authority`. Runtime boundaries independently
require the exact copied scripts/module, CLI, the `famtastic-selected-drupal.XXXXXX`
root, its SQLite file, memory mail and disabled PHP network/mail transports.

The seed uses request901, random `.test` user identities/UUIDs, customer IDs from
1001 and organization IDs from2001 allocated through native user hooks. It creates
its own private JSON proof asset, native campaign/variant/project and canonical
`SelectedSourceIntent`. The scope event explicitly declares a synthetic fixture,
never human approval. Existing real-account-mirroring fixtures are not imported.

Source implements **65 named assertions** across both phases (59 initial,6 fresh
process). These include the real Form API build, rollback after late native writes,
one unpaid order/replay, durable resource/audit binding, both production-default
static wrappers rejecting synthetic identity, stale scope/selection, foreign or
missing membership, and all three private-guard entry points. The guard matrix
covers valid state, disabled saved gateway, missing metadata using durable offer
lookup, missing membership, changed request and disabled checkout.

Guard events are constructed and passed to the actual subscriber without applying
the placement transition. The gateway is key-free; no gateway payment/provider
method is called. No payment or receipt is fabricated. No stock-prepaid record is
seeded and no account-mirroring fixture is enabled for provider access.

Evidence: `<SELECTED_DRUPAL_EVIDENCE>/private-purchase-synthetic.json`, plus an
`Evidence:` stdout marker. The initial status is
`seed_passed_awaiting_fresh_process`; only the second process can mark `passed`.
Evidence includes all assertion results, synthetic record IDs, private-binding
digest and executed application/container/module/harness source hashes, never
passwords, form signatures or raw mail.

Parent execution passed65/65 checks, including fresh-process durability, in
`.artifacts/selected-staging-drupal/20260919T104518Z-7595/private-purchase-synthetic.json`.
See `docs/plans/PRIVATE_PURCHASE_SYNTHETIC_AUTHORITY_2026-09-19.md` for review,
exact source hashes and the retained earlier failed fixture-integration attempt.
This proves only local SQLite native/offline behavior: no HTTP submission/CSRF
validation, browser, portal-link/catalog-exclusion parity, provider, payment,
refund, completed fulfillment, concurrent MySQL, production or launch claim.
