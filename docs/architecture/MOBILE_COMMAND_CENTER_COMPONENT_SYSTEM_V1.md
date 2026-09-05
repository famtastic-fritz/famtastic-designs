# Mobile Command Center component system v1

## Decision

FAMtastic will keep the reusable small-business operating patterns in a
source-controlled component registry rather than burying them inside client
proof pages. A customer receives a branded front door; the owner receives a
phone-first control surface. The two are connected by the same business truth,
not by marketplace links or decorative dashboard data.

## Canonical contract

- Registry: `frontend/public/component-systems/mobile-command-center.v1.json`
- Recipe doctrine: `docs/architecture/FAMTASTIC_PAGE_COMPONENT_DOCTRINE_V1.md`
- Shay recipe: `docs/design/proofs/tighten-up-your-locs-v2/site-recipe.json`
- Validation: `scripts/validate-mobile-command-center-recipe.mjs`
- Delivery boundary: the authenticated website-request proof review, never an
  emailed or public static proof link.

## Component families

| Family | Reusable components | Truth boundary |
| --- | --- | --- |
| Customer front door | brand navigation, hero, quick actions, services, location/contact | A proposed domain is not a registration; no marketplace exit is required. |
| Business control | request windows, protected request | A request is not an appointment; backend slices remain disabled until the exact site is enabled. |
| Owner Desk | setup status, mobile controls, next-best actions | Render recorded state only. Provider, DNS, calendar, payment, and publish actions remain independently authorized. |
| Growth | research plan | Evidence and hypotheses travel with the client, never a promise of growth. |
| Delivery | account-owned proof review | Research, choice, reset, edit history, and receipts remain inside the customer account. |

## Upgrade continuity

The owner’s selected visual direction and stable component instances survive a
one-page → multi-page or starter → growth-system upgrade. New capabilities add
to the recipe. They do not replace the client’s chosen site with unrelated SaaS
chrome or force a fresh website build.

## Shay as the first implementation

Tighten Up Your Locs uses the registry as a client-specific recipe: branded
front door, availability invitation, protected direct request, location and
contact, phone Owner Desk, growth plan, and account-owned Studio Review. The
recipe is a source contract, not proof that its disabled backend routes,
provider integrations, or proposed domain are live.
