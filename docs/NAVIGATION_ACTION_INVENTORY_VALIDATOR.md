# Navigation and action inventory validator

Run `node scripts/validate-navigation-action-inventory.mjs` before closing a
customer-portal or staff-navigation change. It statically inventories the
React customer portal navigation/actions and the FAMtastic Drupal theme plus
pipeline controller links against `frontend/src/App.jsx` and the Drupal route
map.

It fails on static `#`/empty hrefs and form actions, missing internal React
routes, missing `famtastic_pipeline.*` Drupal routes, buttons without a submit
or click behavior (unless deliberately marked non-action), and customer CTAs
that claim publish/deploy/charge/payment confirmation/Site Studio execution
without a `data-record-backed="true"` source annotation.

Run `node scripts/test-validate-navigation-action-inventory.mjs` for the
focused passing and failing fixtures. This is intentionally source-only: it
cannot prove runtime-generated URLs, access permissions, API success,
provider delivery, customer persistence, or production routing. Those require
separate browser and integration evidence.

The accompanying admin-theme contract check also verifies that the native
shell preserves `page.pre_content`, selects the FAMtastic theme on Drupal
login/password-reset routes, uses a desktop left rail, and switches to a
fixed, 44px-target mobile bottom navigation. These are source contracts; a
running Drupal environment is still required to prove template selection and
rendered geometry.
