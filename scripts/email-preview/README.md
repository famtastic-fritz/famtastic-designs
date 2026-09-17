# Valerie email preview — local only

Run from the agency repository root (PHP 8+). No Drupal bootstrap or mail transport
is loaded by these commands. Do not serve the repository root.

```sh
php scripts/email-preview/render.php '/Users/famtastic-fritz/Downloads/ChatGPT Image Sep 17, 2026, 08_45_02 AM.png'
php scripts/email-preview/test.php
php -S 127.0.0.1:8765 -t .local-email-preview
```

In a second terminal, from the separate Pros In Training repository:

```sh
npm test
npm run build
php -S 127.0.0.1:8766 -t site
```

With both servers running, capture screenshots using an existing Playwright installation:

```sh
node scripts/email-preview/capture.cjs /Users/famtastic-fritz/Development/worktrees/fd-command-center-integration/frontend/node_modules/@playwright/test
```

Open http://127.0.0.1:8765/ for the reference beside desktop/mobile screenshots,
images-disabled proof, real HTML email, plain text and local site-footnote views.
The original reference is optional after the first render, retained only in ignored
`.local-email-preview/`. All screenshots/results are regenerable local artifacts.
The staging CTA is a real public URL; testing inspects its href without navigating
to it or submitting anything. Nothing calls `send()` or creates an outbox record.

## Changed source

- `backend/web/modules/custom/famtastic_pipeline/src/Service/StagingReviewEmail.php`:
  pure branded presentation, escaping and URL validation.
- `.../Service/OutreachMailer.php`: new template/version registration and isolated dispatch.
- `scripts/email-preview/`: Valerie fixture, non-sending render, gallery, PHP and browser tests.
- `docs/design/`: email contract and byte-identical supplied PNG.
- Root `design.md`, template registry and status/learning records: discoverability and evidence.
- Customer repo: `site/index.html` footnote, source regression assertions, design checksum
  and local-only documentation. No existing access/robots settings changed.

## Test results — September 17, 2026

- 35 standalone PHP assertions: escaping, unsafe/missing URLs, unknown version,
  hosted-logo fail-closed, renderer dispatch, exact plain-text destination/signature,
  placeholder scan, and all five pre-existing template renderings byte-identical
  to source baseline `70d2a8cf` for the same regression input.
- Chromium browser: 320/390/660/768px email widths, no horizontal overflow,
  mobile stacking, real CTA with ≥44px target, exact destination and loaded images.
- Images blocked at 390px: message and CTA readable, no horizontal overflow.
- Local customer footer: 390/768/1280px, no overflow, noindex preserved.
- Customer `npm test` and build: standalone repository and static-site checks.
- Actual Gmail/Outlook/Apple Mail rendering: **not tested**. SMTP/recipient delivery:
  **not attempted**. Backend full PHPUnit suite: **not run** (vendor absent in this worktree).

Implementation awaits owner visual feedback. No push, deployment, asset hosting,
customer send, broad logo migration, or additional template fixtures in this milestone.
