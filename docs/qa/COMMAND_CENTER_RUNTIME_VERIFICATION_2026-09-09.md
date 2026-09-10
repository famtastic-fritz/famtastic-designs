# Command-center runtime verification — 2026-09-09

## Scope and isolation

The source under test was the integration source snapshot on
`codex/fd-command-center-integration`. A disposable copy was created under
`/private/tmp/fd-command-center-runtime.zhmA9J`; Composer dependencies and a
fresh SQLite database were installed there only. The runtime enabled
`famtastic_admin`, `famtastic_pipeline`, and `famtastic_preview`. No customer
records, mail delivery, payment, deployment, provider call, or production
resource was used.

## Passing browser evidence

The reusable `frontend/playwright.runtime.config.js` and
`frontend/e2e/isolated-command-center-runtime.spec.js` harness started the
integration frontend and the disposable Drupal runtime locally. The customer
portal used route-local mock responses with an empty workspace; Drupal used the
fresh SQLite runtime. The following complete command passed:

```text
FAMTASTIC_RUNTIME_BACKEND_DIR=/private/tmp/fd-command-center-runtime.zhmA9J/backend \
FAMTASTIC_RUNTIME_FRONTEND_DIR=/private/tmp/fd-command-center-runtime.zhmA9J/frontend \
FAMTASTIC_RUNTIME_BACKEND_URL=http://127.0.0.1:18081 \
npx playwright test --config=playwright.runtime.config.js --reporter=dot
3 passed (20.4s)
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

The portal result is deliberately mock-backed because this run was forbidden
from using customer data; it proves the rendered client behavior, not a
customer-account lifecycle.

## Drupal runtime evidence

The fresh runtime proved the FAMtastic theme on login and password reset at 390,
768, and 1280px; anonymous staff-route protection; authenticated Operations
Home; all fourteen live-count record-list destinations; Attention; native
Drupal content tables and node forms; FAMtastic settings; visible focus;
minimum input sizing; invalid-login recovery; placeholder-link absence; and
user-visible horizontal containment. The runtime exposed and the implementation
repaired four integration defects before the passing run:

- the custom pipeline module used Commerce, Webform, Node, and Views APIs
  without declaring their module dependencies;
- login, reset, and non-custom administration routes had no reusable theme
  negotiator and could fall back to the public theme;
- the desktop command-center body used content-box sizing, which made its own
  padding overflow a 1280px viewport by 64px.
- wide custom record tables could still widen a 390px page after drill-in; the
  theme now wraps every Drupal render-array table in a keyboard-focusable,
  touch-scrollable boundary while the page and navigation remain fixed.

The `block_page` bootstrap failure observed during setup did not recur after the
dependency declarations, module installation, and cache rebuild. The final
browser run used PHP with a 512MB memory limit because Webform container
compilation exhausted the default 128MB limit in the disposable environment.

| Drupal surface | Viewport | SHA-256 |
| --- | ---: | --- |
| Login | 390 | `437808ed7c273cf8fce86be63812d3be95e40c0a64e33c7e15ecd7b673de7998` |
| Login | 768 | `1939a79cc12368f64a36ce2fe469819f47e7d93d7a7dfbd219742850e4813ad1` |
| Login | 1280 | `f85351f8bdfbd21384cb915c8c7899b2b6f109d21586c23a798dd66459fbd955` |
| Operations Home | 390 | `3503bb146f8eaefd6ca46468fd65c0a19bf61f93cb2235a3d6e6893d886f6f4f` |
| Operations Home | 768 | `2d729657cc621fc5ddc8761109784295e37881712b3b15bbbe7f750fb5ea7f1f` |
| Operations Home | 1280 | `7574acad5d1043f72da4f98b29424aef0157716c21f9f2089acc13af21d1e5d5` |
| Native node form | 390 | `2ab8bddd3b91149d9b34c2e5cf5e916e785d97684000b5d4227b323d4a1acfe5` |
| Native node form | 768 | `6defcf583e4f656047fdde5822740c41f9374fdb016939e72d5be95321bfaee3` |
| Native node form | 1280 | `c8021baa51c209d529d90a9e585155228fc69fc64d752c9460e34f965f1346da` |

## Re-run contract

Prepare a fresh isolated backend/database first, including the dependencies
declared by `famtastic_pipeline.info.yml`; then set
`FAMTASTIC_RUNTIME_BACKEND_DIR` and `FAMTASTIC_RUNTIME_FRONTEND_DIR` to those
copies and run `frontend/playwright.runtime.config.js`. Do not point either
variable at production or at a customer-bearing local database. Do not use
`FAMTASTIC_RUNTIME_SKIP_DRUPAL` for release evidence; that switch remains useful
only for isolated portal development.

## Protected-staging safety checkpoint

Commit `ce72eaae` adds runtime-level refusal for native Commerce checkout,
custom and revision checkout, simulation, Stripe webhook, cron, direct
outreach, and Drupal mail. The focused PHPUnit safety contract and the full
suite pass locally. This is application evidence, not hosted-staging evidence.
The hosted run must repeat the payment refusal and row-count checks, exercise
both mail entry points and confirm digest-only capture, run cron and confirm no
outbox/lifecycle changes, inspect all disabled runtime settings, and prove the
exact pushed SHA before this document calls protected staging verified.
