# Command-center runtime verification — 2026-09-09

## Scope and isolation

The source under test was the clean integration worktree on
`codex/fd-command-center-integration`. A disposable copy was created under
`/private/tmp/fd-command-center-runtime.zhmA9J`; Composer dependencies and a
fresh SQLite database were installed there only. The runtime enabled
`famtastic_admin`, `famtastic_pipeline`, and `famtastic_preview`. No customer
records, mail delivery, payment, deployment, provider call, or production
resource was used.

## Passing browser evidence

The reusable `frontend/playwright.runtime.config.js` and
`frontend/e2e/isolated-command-center-runtime.spec.js` harness started the
integration frontend locally and used route-local mock responses with an empty
workspace. The following command passed:

```text
FAMTASTIC_RUNTIME_SKIP_DRUPAL=1 ... playwright test --config=playwright.runtime.config.js --grep 'customer portal mocked runtime'
1 passed (2.7s)
```

At 390, 768, and 1280px the customer portal asserted the real visible `Open
Projects` action, a 44px-or-larger target, programmatic keyboard focus, a
computed foreground/background contrast ratio of at least 4.5:1, and no
horizontal document overflow. Captured screenshots are local ephemeral test
artifacts, not product fixtures:

| Viewport | SHA-256 |
| --- | --- |
| 390 | `a427a644aa1ceb73aa9b49f02ac3ef07f37d0ec86375c669fb22f8369ba83919` |
| 768 | `b5bac1aabb88719df51fdea2a08d61fe3fa481d1eb2d1d1378a07f46e9a95dd4` |
| 1280 | `0873be0ff59a49be84bb7170cac7569a17875e80d0bef16a8a0d8fc2caa96461` |

`node scripts/validate-client-portal-design-dna.mjs` also passed all 30 checks.
The portal result is deliberately mock-backed because this run was forbidden
from using customer data; it proves the rendered client behavior, not a
customer-account lifecycle.

## Drupal HTTP blocker

The new SQLite runtime installed and bootstrapped successfully through Drush,
including the FAMtastic admin theme and the custom pipeline routes. Browser
rendering could not begin because the PHP built-in HTTP runtime throws before
any page markup is emitted:

```text
Drupal\Component\Plugin\Exception\PluginNotFoundException:
The "block_page" plugin does not exist.
```

The same database reports the `block` module enabled and Drush can enumerate
`block_page` and `simple_page` from `plugin.manager.display_variant`. This is
therefore an HTTP/bootstrap inconsistency in this local PHP 8.5 runtime, not
evidence that the theme is working. Login/reset, native Drupal forms/tables,
status messages, staff permissions, and custom staff-route screenshots remain
**unproven** until that local HTTP issue is repaired or the test is run in the
supported container runtime. Docker could not be used here because its local
daemon socket was unavailable and the installed client has no Compose plugin.

## Re-run contract

Prepare a fresh isolated backend/database first, then set
`FAMTASTIC_RUNTIME_BACKEND_DIR` and `FAMTASTIC_RUNTIME_FRONTEND_DIR` to those
copies and run `frontend/playwright.runtime.config.js`. Do not point either
variable at production or at a customer-bearing local database. Run without
`FAMTASTIC_RUNTIME_SKIP_DRUPAL` to require the Drupal login/reset, staff
permission, native primitive, and custom-route assertions after the HTTP
bootstrap problem is resolved.
