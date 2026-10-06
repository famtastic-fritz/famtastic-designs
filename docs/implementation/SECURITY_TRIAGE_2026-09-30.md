# Source-lock security maintenance triage — 2026-09-30

Status: read-only source audit; no dependency changes, production inventory, deployment or exploit testing.

## Confirmed findings and proposed patch targets

The checked-in `backend/composer.lock` contains **22 current advisories across three packages**. Composer audit completed with exit 1 (advisories found), zero abandoned packages. This is evidence about the source lock, not a claim about live installed/enabled packages or exploitation. Live admin update notices are consistent with a maintenance need but do not establish exact package versions.

Lock SHA-256: `74e4984a628de3ad26b9d38aad05f97579baeb031ea6eefd2d6274f933a1e009`.

| Package | Locked | Proposed patch target | Basis |
|---|---|---|---|
| Drupal core and core-recommended/scaffold/project-message | 11.4.5 | 11.4.8 (minimum security fix 11.4.7) | [SA-CORE-2026-013](https://www.drupal.org/sa-core-2026-013); [11.4.8 release](https://www.drupal.org/project/drupal/releases/11.4.8) includes 11.4.7 and later bug fixes |
| drupal/webform | 6.3.0 | 6.3.1 | [6.3.1 security release](https://www.drupal.org/project/webform/releases/6.3.1), compatible with Drupal ^10.3 or ^11.0 |
| drupal/project_browser | 2.1.4 | 2.1.5 | [SA-CONTRIB-2026-178](https://www.drupal.org/sa-contrib-2026-178), critical CSRF correction |

Current root constraints `drupal/core-*: ^11`, `drupal/webform: ^6.3`, and `drupal/ai_dashboard`'s transitive `drupal/project_browser: ^2.0` admit these patch targets. Full solver/platform compatibility has **not** been run; this is constraint-compatible targeting, not an installed-runtime result. PHP root requirement is >=8.3. Do not blanket-upgrade unrelated packages.

The core advisory concerns the bundled CKEditor library. Webform's highest-rated advisory is [SA-CONTRIB-2026-175](https://www.drupal.org/sa-contrib-2026-175); affected behavior depends on a configured custom multiple-value format with submission tokens. No configuration exposure or exploit has been established here. Project Browser's advisory concerns insufficient CSRF validation on administrative actions. The source lock's AI 1.4.8, AI Agents 1.3.4, and OpenAI provider 1.2.4 were not reported affected by this Composer audit; this does not prove every possible AI integration is secure.

## Audit reproduction and exact advisory inventory

Run from backend:

```sh
COMPOSER_CACHE_DIR=/private/tmp/famtastic-composer-security-cache composer audit --locked --format=json --no-interaction
```

Initial restricted-network run failed DNS; approved read-only network retry succeeded. Audit consumed Drupal/Packagist advisories. Temporary JSON evidence: `/private/tmp/famtastic-security-audit.json` (not a durable artifact). Exact findings retained below; official pages [Drupal security advisories](https://www.drupal.org/security), core advisory, Project Browser advisory and Webform release were independently checked.

| Package | Advisory | Class |
|---|---|---|
| `drupal/core` | SA-CORE-2026-013 | Third-party libraries |
| `drupal/project_browser` | SA-CONTRIB-2026-178 | Cross-site request forgery |
| `drupal/webform` | SA-CONTRIB-2026-175 | Remote Code Execution |
| `drupal/webform` | SA-CONTRIB-2026-174 | Access bypass |
| `drupal/webform` | SA-CONTRIB-2026-173 | Access bypass |
| `drupal/webform` | SA-CONTRIB-2026-172 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-158 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-159 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-154 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-155 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-160 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-161 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-171 | Access bypass |
| `drupal/webform` | SA-CONTRIB-2026-170 | Denial of service |
| `drupal/webform` | SA-CONTRIB-2026-169 | Access bypass |
| `drupal/webform` | SA-CONTRIB-2026-162 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-163 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-165 | Access bypass |
| `drupal/webform` | SA-CONTRIB-2026-164 | Access bypass, Server-side request forgery |
| `drupal/webform` | SA-CONTRIB-2026-166 | Cross-site scripting |
| `drupal/webform` | SA-CONTRIB-2026-167 | Access bypass |
| `drupal/webform` | SA-CONTRIB-2026-168 | Access bypass |

## Reviewed platform release procedure

1. First inventory live `composer show --locked` and installed package versions, Drupal version, enabled Webform submodules, PHP/extensions, release SHA and pending database updates read-only. Capture package/version output without settings or secrets. Do not equate a newer Git source lock with deployed dependencies.
2. Create a separate maintenance branch from current refreshed main. In an isolated runtime solve only the target patch updates (core packages, Webform, Project Browser and required dependencies), inspect every lock delta, retain the previous exact lock. Re-run current advisory audit. No `composer update` in public_html and no admin UI installer.
3. Validate Composer strictly, `composer check-platform-reqs --lock --no-dev`, fresh install and existing-schema updates against disposable databases. Exercise Webform creation/submission, access isolation, file/export permissions, AJAX and source editing; specifically review newly introduced remote-post permissions rather than granting them broadly. Verify Project Browser administrative action CSRF. Exercise CKEditor and admin theme/forms at desktop and 390px.
4. Run pipeline unit suite, email renderer contracts, messaging/draft fixtures and full account→request→proof lifecycle with memory mail and disabled payment/provider/worker side effects. Recheck AI API adapter compatibility, canonical portal authentication/tenant boundaries, and Commerce order/checkout regression tests. There is no reason for a security maintenance test to send customer mail or create a real payment.
5. Obtain reviewed clean main SHA and use only `scripts/deploy-backend-godaddy.sh` per `docs/BACKEND_DEPLOYMENT.md`. Preflight is read-only. Production apply needs its explicit authorization and must back up code/themes/dependency tree/config/database before reviewed Composer install and update hooks. Preserve existing scheduler and exact-dispatch safeguards; do not inadvertently drain old campaigns/outbox.
6. After authorized apply, record exact runtime SHA/package versions, `composer audit --locked`, `drush updatedb:status`, targeted route/customer lifecycle smoke and error logs. Until then classify this maintenance as **identified, not fixed or deployed**.

## Rollback and release gate

Use actual paths recorded by the canonical deployment receipt. Restore prior code/themes plus prior reviewed Composer files and dependency backup as a matched runtime; do not mix new vendor with old lock. Database updates may not be reversible: only restore the pre-update SQL backup if required for compatibility, with separate explicit destructive-restore approval. Preserve new customer data and queue history through a deliberate recovery decision. Repeat bootstrap, cache rebuild and read-only smoke after rollback.

This triage should block declaring the entire backend repaired: the three source-lock security patches still need their own isolated implementation, regression proof and reviewed platform release. No package or production configuration was changed by this task.

## Isolated patch implementation addendum — 2026-09-30

**Source lock patched; new matched-runtime regression and production release remain pending.** This supersedes the earlier “no package changed” source status only. The current live inventory remains unverified. No install, plugin, project script, database update, customer transport or production operation ran in this patch task.

Independently rechecked the official [Drupal 11.4.8 release](https://www.drupal.org/project/drupal/releases/11.4.8), [Webform 6.3.1 release](https://www.drupal.org/project/webform/releases/6.3.1) and [Project Browser security advisory](https://www.drupal.org/sa-contrib-2026-178). Exact-target solver operated in `/private/tmp/famtastic-security-patch-solve-20260930`, with a separate Composer cache and copied input files. Initial sandbox DNS failure was retried through approved network escalation.

```sh
COMPOSER_CACHE_DIR=/private/tmp/famtastic-security-patch-solve-20260930/cache \
composer update drupal/core:11.4.8 drupal/core-recommended:11.4.8 \
  drupal/core-composer-scaffold:11.4.8 drupal/core-project-message:11.4.8 \
  drupal/core-dev:11.4.8 drupal/webform:6.3.1 drupal/project_browser:2.1.5 \
  --with-all-dependencies --minimal-changes --patch-only \
  --no-install --no-plugins --no-scripts --no-interaction
```

Solver succeeded: **zero installs, seven updates, zero removals**. Full package-object comparison confirmed no unrelated lock-package changes:

| Package | Previous | Patched |
|---|---|---|
| drupal/core | 11.4.5 | 11.4.8 |
| drupal/core-recommended | 11.4.5 | 11.4.8 |
| drupal/core-composer-scaffold | 11.4.5 | 11.4.8 |
| drupal/core-project-message | 11.4.5 | 11.4.8 |
| drupal/core-dev | 11.4.4 | 11.4.8 |
| drupal/webform | 6.3.0 | 6.3.1 |
| drupal/project_browser | 2.1.4 | 2.1.5 |

`backend/composer.json` remains byte-identical; existing constraints admit all targets. Only the resulting lock was copied to the task worktree. New lock SHA256: `8467af6de95c5f13df557c4be03a61968f4a633f056d7ab5d4312727532eb7bc`.

Checks completed in the disposable solver directory:

- `composer validate --strict --no-check-publish --no-interaction --no-plugins`: exit 0, valid.
- `composer check-platform-reqs --lock --no-dev --no-plugins`: exit 0 on local PHP 8.5.9, every required extension/API successful. This is not a production PHP/platform result.
- `composer audit --locked --format=json --no-interaction --no-plugins`: exit 0 against current advisories; exact result `{"advisories":[],"abandoned":[],"filter":[]}`. The original 22 lock advisories are absent from the patched lock's current audit; this is not a guarantee of no undisclosed vulnerability.

Integration must now create a **new matched runtime** from this lock, without mutating the already-running feature test runtime. Run the existing-schema database update and regression checklist above before release: Webform remote-post permissions and other tightened access paths may affect existing owner workflows; Project Browser actions need CSRF verification; CKEditor, admin forms/mobile layout, pipeline AI/mail drafts, Commerce and tenant isolation must remain intact. Core-dev now matches production core, correcting its prior 11.4.4 mismatch. No additional transitive packages changed.

Rollback remains matched old code/lock/dependencies plus deliberate database recovery only if necessary. Retain original lock hash above as the prepatch identity. Do not report production fixed until canonical reviewed deployment and runtime verification complete.
