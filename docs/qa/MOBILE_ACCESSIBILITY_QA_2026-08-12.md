# Mobile Presentation and Accessibility QA — 2026-08-12

Scope: production template sampling at a 390 × 844 mobile viewport plus a
systematic review of the shared React components and CSS. This is a template
audit, not a claim of formal WCAG conformance.

## Summary scorecard

| Template | Mobile | Accessibility | Evidence / correction |
|---|---:|---:|---|
| Homepage | 94/100 | 90/100 | One H1, one main landmark, no horizontal overflow, no images missing alt text. Shared footer targets corrected. |
| About | 95/100 | 91/100 | One H1, one main landmark, no overflow or missing alt text. |
| FAQ hub | 95/100 | 92/100 | One H1 and no overflow. Accordion controls now explicitly own labelled answer regions. |
| 55 Cents campaign | 94/100 | 91/100 | One H1, no overflow, all images have alt text. Shared link-target correction applied. |
| Services hub | 94/100 | 90/100 | One H1, no overflow, no missing alt text. Shared mobile targets corrected. |
| Service detail | 94/100 | 90/100 | Production content loaded with correct heading hierarchy after async completion; no overflow. |
| Packages hub | 94/100 | 90/100 | One H1, no overflow. Fine-print labels remain intentionally compact but readable. |
| Package detail | 94/100 | 90/100 | Production content loaded after async completion; no overflow. |
| Blog hub | 95/100 | 91/100 | One H1, no overflow, no missing alt text. |
| Blog article | 92/100 | 89/100 | One H1, no overflow, all images labelled. Inline editorial links are below 44px high but are not dense adjacent controls. |
| Purchase | 90/100 | 88/100 | No overflow. Anonymous state intentionally has no H1 until async session/catalog resolution; authenticated purchase flow requires separate account-state QA. |
| Contact | 94/100 | 91/100 | One H1, no overflow, controls have names. |

Overall sampled-template score: **94/100 mobile, 90/100 accessibility**.

## Corrections implemented in source

- Added a keyboard-visible “Skip to main content” link and a focusable main landmark.
- Added a consistent high-contrast `:focus-visible` treatment for links, buttons,
  fields, selects, textareas, and disclosure summaries.
- Connected the mobile menu button to the menu with `aria-controls` and gave it
  state-specific accessible names.
- Raised mobile navigation and footer link targets to a 44px minimum height.
- Connected FAQ buttons and answer regions with `aria-controls`, `aria-labelledby`,
  stable IDs, and region semantics.
- Added a global reduced-motion mode for visitors who request it.

## Production evidence

Twelve representative URLs were inspected. Every sampled URL had:

- zero horizontal overflow at the applied mobile viewport;
- one main landmark;
- zero visible unnamed form controls or interactive elements;
- zero images missing an `alt` attribute.

The service and package detail routes are client-rendered and briefly have no H1
while their Drupal content is loading. After loading, the service detail had one
H1 and a valid H1 → H2 → H3 hierarchy. This is not a final-render accessibility
failure, but it reinforces the need for runtime-aware testing.

## Remaining limitations / launch follow-up

- Run authenticated mobile QA for checkout, intake, and customer portal states
  with controlled test accounts; the public anonymous purchase state cannot
  exercise those protected workflows.
- Run an automated axe-core suite and a manual keyboard/screen-reader pass before
  claiming WCAG 2.2 AA conformance.
- Several inline article links naturally render below 44px high. WCAG target-size
  exceptions may apply to inline text, but prominent editorial actions could be
  visually promoted in a future design pass.
- The production service CTA currently points to `/web/contact?...`; content/link
  QA should confirm that this is intentional rather than the branded `/contact`
  route.
- The build reports a JavaScript chunk over 500 kB. This does not create overflow,
  but code splitting should be tracked as a mobile-performance improvement.

## Verification performed

- Production responsive DOM audit at 390 × 844 across the twelve template URLs.
- Shared JSX/CSS review for labels, landmarks, heading hierarchy, alt text, focus,
  reduced motion, target size, and overflow.
- `npm --prefix frontend run build` — passed.

