# Public lead-to-portal funnel proof — 2026-08-17

## Proven locally

The browser scenario starts at `/start` without an account and completes the Website branch of Solution Finder. The mocked Drupal response represents a saved Prospect and Intake and returns a continuation URL.

The rendered result distinguishes the starter recommendation from a final quote or finished proof, invites the visitor to create a free account for working demos, and discloses that registration and the detailed brief do not require payment.

The continuation URL opens registration mode with the same email and business name. The authenticated continuation route `/portal?start=website` opens the detailed website-request form automatically. The form exposes business model, industry, research, reference-site reasons, existing technology, desired domains, domain fallback, business email, and custom-needs fields.

## Checks

- PHP syntax: `PublicRequestController.php` and `CustomerPortalService.php` passed.
- Frontend production build passed under Node 22.
- Demand Engine checks passed: 10 series, 80 posts, 40 FAQs, 5 categories, and 19 tags.
- Playwright desktop Chromium: passed.
- Playwright mobile Chromium: passed.
- Browser evidence is stored under `.artifacts/playwright-results/` for `public-lead-to-portal.spec.js`.

## Evidence boundary

The browser API response is intercepted and deterministic. This proves the local frontend conversion contract and detailed portal continuation. The PHP controller syntax and existing email-based claim logic are verified in source, but a real Drupal database write, real registration email, production deployment, and real promotional send were not performed by this change.
