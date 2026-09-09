# FAMtastic Admin Theme

`famtastic_admin` is the reusable administration theme for compatible
FAMtastic Drupal sites. It inherits stable Drupal administration behaviour
from Claro and owns the FAMtastic visual system: the dark/lime tokens,
responsive staff shell, native navigation, form and recovery states, tables,
status messages, local tasks, details, pagination, entity lists, and focus
treatment.

## Install and reuse

1. Copy this theme into `web/themes/custom/famtastic_admin` in a Drupal 11
   site with Claro available.
2. Enable it and select it as the administration theme.
3. Clear Drupal caches. Native routes marked `_admin_route: TRUE` automatically
   receive the shell; no per-route CSS registration is required.
4. For an operational module, attach its records with ordinary Drupal render
   arrays, forms, tables, messages, local tasks, and pager elements. The theme
   styles those primitives globally. Use a semantic, module-prefixed class only
   for a layout that cannot be expressed with those primitives.

## Extension contract

The theme guarantees the shared system for standards-compliant Drupal markup:
forms and fieldsets, validation messages, tables, details, tabs/local tasks,
action links, pagers, Views filters/lists, entity-edit sidebars, status reports,
and responsive containment. It does **not** claim to perfect arbitrary
third-party proprietary markup or embedded applications.

For a custom component, add a namespaced class (for example
`acme-inventory__queue`) and use the token variables documented in
`css/famtastic-admin.css`; do not hard-code a competing palette. If the markup
needs structural control, add a narrowly-scoped Twig suggestion under
`templates/` and register it through the theme's suggestion hook. Keep the
native form element and its Drupal render array intact so access, validation,
and CSRF behavior are preserved.

`tests/fixtures/future-module-primitives.html` is the representative fixture
for a future standards-compliant module. `node scripts/validate-famtastic-admin-theme.mjs`
checks that the shell, template, reusable primitive coverage, fixture, and
documentation remain connected.

## Accessibility and safety

The theme maintains 44px touch targets on handheld layouts, visible keyboard
focus, reduced-motion support, readable semantic status colors, and no page
horizontal overflow. It only presents existing Drupal routes and actions; it
does not create a publishing, payment, customer-message, provider, or deploy
authority.
