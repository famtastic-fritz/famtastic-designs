# FAMtastic Designs — Design System & Content Guidelines

This document is the shared contract between the Drupal 11 backend (content
model + tone) and the React 18/Vite frontend (components + visual language).
It mirrors the v1 site's look and feel so both tiers stay in sync.

---

## 1. Color Tokens

Dark-first theme. Use CSS custom properties with these exact names/values:

| Token             | Hex       | Usage                                              |
| ----------------- | --------- | -------------------------------------------------- |
| `--color-bg`      | `#0a0a0a` | Page background                                    |
| `--color-surface` | `#141414` | Raised surfaces (sections, nav, footer)            |
| `--color-card`    | `#111111` | Cards, panels, testimonial/pricing blocks          |
| `--color-border`  | `#2a2a2a` | Hairline borders, dividers, card outlines          |
| `--color-text`    | `#EAEAEA` | Primary text                                       |
| `--color-muted`   | `#888888` | Secondary text, captions, meta                     |
| `--color-accent`  | `#7CFC00` | Electric lime — CTAs, links on hover, highlights, active nav |

Rules:

- Accent is for **action and emphasis only** — never large fills, never body text.
- All text on `--color-bg`/`--color-surface`/`--color-card` must meet WCAG AA
  (`#EAEAEA` and `#7CFC00` on these backgrounds both pass; `#888888` only for
  large or non-essential text).
- Never introduce new hues; depth comes from the bg → surface → card ladder,
  not from color.

## 2. Typography

| Role         | Font            | Weights      | Notes                                   |
| ------------ | --------------- | ------------ | --------------------------------------- |
| Headings     | **Space Grotesk** | 500, 700   | H1–H3, hero headline, stat values, card titles |
| Body         | **Inter**       | 400, 500, 600 | Paragraphs, lists, buttons, nav, forms |

- Load via Google Fonts (or self-hosted) with `display=swap`.
- Hero headline: Space Grotesk 700, clamp ~2.5–4rem, tight line-height (1.1).
- Body: Inter 400, 1rem/1.6, `--color-text`; secondary copy in `--color-muted`.
- Stat values (`$4.2M`, `22+`) render in Space Grotesk 700 accent-colored.

## 3. Component Naming Convention

Drupal paragraph types map 1:1 to frontend React components. Component names
are **PascalCase versions of the paragraph bundle**, minus the `_item`/`_qa`
suffixes where noted. Props are camelCase versions of the Drupal field names.

| Drupal paragraph type | Drupal fields                                        | React component  | Used in                                  |
| --------------------- | ---------------------------------------------------- | ---------------- | ---------------------------------------- |
| `metric_item`         | `field_metric_value`, `field_metric_label`           | `MetricItem`     | Homepage stats strip (`StatGrid`)        |
| `why_item`            | `field_why_title`, `field_why_body`                  | `WhyItem`        | Homepage/About "Why us" grid (`WhyGrid`) |
| `process_step`        | `field_step_number`, `field_step_title`, `field_step_description` | `ProcessStep` | Numbered process lists (`ProcessTimeline`) |
| `faq_qa`              | `field_question`, `field_answer`                     | `FaqItem`        | FAQ accordions (`FaqAccordion`)          |
| `addon_item`          | `field_addon_name`, `field_addon_price`              | `AddonItem`      | Package add-on lists (`AddonList`)       |
| `social_link`         | `field_platform`, `field_url`                        | `SocialLink`     | Footer/contact social row (`SocialLinks`) |

Conventions:

- JSON:API responses expose paragraphs via `?include=`; the frontend resolves
  included entities into the matching component by `type` (e.g.
  `paragraph--metric_item` → `MetricItem`).
- Node-level sections are named after their field groups: `Hero`,
  `FinalCta` (`field_final_cta_*`), `Testimonial` (`field_testimonial_*`),
  `Proof` (`field_proof_*`), `ServiceArea` (`field_service_area_*`).
- Containers wrap items in plural grid/list components (`StatGrid`,
  `WhyGrid`, `ProcessTimeline`, `FaqAccordion`, `AddonList`).

## 4. Content Tone Guidelines

Voice: **professional, engineering-focused, not salesy.**

- Always **we / us / our team / FAMtastic Designs** — never I / me / my.
- Founder references are third person and credential-led
  ("founded by Fitzgerald 'Fritz' Medine, 2024 BEYA Leader in Technology"),
  never "Call Fritz" framing.
- Lead with outcomes and evidence (metrics, process, architecture), not
  superlatives. Numbers beat adjectives: "40% more appointments" over
  "amazing results".
- Positioning is **agency / worldwide**: "Based in Florida. Serving clients
  worldwide." — no city-level service-area framing in copy.
- Testimonials keep plausible business attributions ("HVAC Company Owner",
  "Real Estate Broker") without street-level locality.
- CTAs are direct and low-pressure: "Tell us what you're building. We'll send
  you a quote within 24 hours."
- Em dashes are fine; exclamation marks are not. Sentence case for headings.

## 5. Image Requirements

- **Dark-theme compatible**: images must sit on `#0a0a0a`–`#141414`
  backgrounds. Prefer dark or high-contrast photography; avoid white
  backgrounds. Logos/icons need a dark-mode variant (light or lime-on-dark).
- **Formats**: WebP primary, with PNG/JPEG fallback for older clients; SVG
  for icons and logos.
- **Sizes**:
  - Hero: 1920×1080 (16:9), ≤ 300 KB WebP.
  - Case-study / work cards: 1200×675 (16:9), ≤ 150 KB.
  - Testimonial avatars: 256×256 (1:1), ≤ 40 KB.
  - OG/social share: 1200×630.
  - Favicon set: SVG + 32×32 PNG.
- Provide `width`/`height` attributes to prevent layout shift; use `srcset`
  with 1x/2x for heroes.
- Alt text is required on editorial images; purely decorative images use
  empty `alt=""`.

---

*Maintained alongside `backend/scripts/rebrand-agency.php` (content
positioning) — update both when the brand system changes.*
