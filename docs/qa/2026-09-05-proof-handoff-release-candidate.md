# Proof handoff release candidate — 2026-09-05

## Scope

Close the five lifecycle integrity gaps before testing one of Fritz's paid-but-undelivered personal projects:

- [x] Preserve approved proof research outside mutable intake data.
- [x] Exercise snapshot persistence and non-switchable selection in the synthetic journey script.
- [x] Add exact first-party customer/site binding for the mobile Owner Desk.
- [x] Make customer proof selection terminal with an explicit owner reopen exception.
- [x] Register the mobile command-center recipe as actual Site Studio components (separate Site Studio commit `02e758495`).

## Current evidence

- PHP lint passes for all changed module files.
- Client Portal Design DNA: 30/30 passes.
- Mobile command-center recipe validation: 10 stable component instances resolve to 12 definitions.
- Frontend production build passes.
- Site Studio registry contract: 12/12 registered definitions and HTTP component retrieval pass in its isolated worktree.
- `git diff --check` passes.

## Required test before using a paid personal project

The complete synthetic journey is intentionally not marked passed. Its runner requires a bootstrapped Drupal database; this isolated source worktree has no `key_value` table, so Drush stops during bootstrap before migrations or customer fixtures can run.

1. Provision/select an isolated local Drupal database for this worktree.
2. Apply pending module updates, including migrations 8052 and 8053.
3. Run `scripts/e2e-autonomous-journey.sh` with the default memory email and local fixture providers.
4. Inspect its generated evidence JSON and confirm the three new checks: research survives an intake save, a second selection is rejected, and another customer cannot read or change the bound Owner Desk.
5. Only then identify the exact paid personal project/account from the authoritative records and test its current fulfillment state. That later test must not send customer mail, publish a site, charge a card, or change the project without a separate explicit instruction.

## Safety boundary

This release candidate is source-only. No production database, deployment, payment, calendar, booking provider, or customer communication was changed.
