# FAMtastic Designs — Content Model

This doc defines the public-facing content objects for the Designs marketing site and the flow that turns raw client/proof material into published pages.

## Content Objects

### 1. SiteBlock (editable page sections)
Mirrors the `page_content` table pattern used on FAMtastic Hosting. Lets non-dev editors change homepage copy, service descriptions, CTAs, and cross-promo cards without touching code.

| Field | Type | Notes |
|---|---|---|
| `id` | INT PK | auto |
| `page` | VARCHAR(64) | e.g. `index`, `services`, `pricing`, `church-connect` |
| `section` | VARCHAR(64) | e.g. `hero`, `services_grid`, `cross_promo_hosting` |
| `key_name` | VARCHAR(128) | e.g. `headline`, `body`, `cta_text`, `cta_url` |
| `value_text` | TEXT | HTML/markdown allowed; sanitized on write |
| `value_type` | ENUM | `text`, `html`, `markdown`, `url`, `image_url` |
| `updated_at` | TIMESTAMP | last edit |
| `updated_by` | VARCHAR(128) | admin email or `system` |

**Default fallback rule:** every `getValue(pageContent, section, key, fallback)` call must ship a hardcoded fallback so the site never 500 if a row is missing.

### 2. ServiceOffering
The services listed on `/services` and referenced in campaigns.

| Field | Type | Notes |
|---|---|---|
| `service_id` | VARCHAR(32) PK | e.g. `web-design`, `ai-automation`, `mobile-commerce` |
| `title` | VARCHAR(255) | human name |
| `slug` | VARCHAR(64) UNIQUE | URL slug |
| `short_pitch` | TEXT | 1-2 sentence value prop |
| `description` | TEXT | long-form service page body |
| `starting_price` | INT | cents, optional |
| `deliverables` | JSON | list of deliverable strings |
| `duration_days` | INT | typical turnaround |
| `icon` | VARCHAR(255) | icon path/name |
| `is_active` | BOOLEAN | show/hide |
| `sort_order` | INT | display order |

### 3. ProofArtifact (portfolio / proof gallery)
A completed transformation used in `/proof-gallery` and embedded in campaigns.

| Field | Type | Notes |
|---|---|---|
| `proof_id` | CHAR(16) PK | base62 slug |
| `client_name` | VARCHAR(255) | public or obfuscated |
| `industry` | VARCHAR(64) | e.g. `church`, `salon`, `law` |
| `campaign_key` | VARCHAR(32) FK | links to Campaign |
| `before_url` | VARCHAR(512) | before screenshot |
| `after_url` | VARCHAR(512) | after screenshot / live URL |
| `story_summary` | TEXT | 2-3 sentence transformation story |
| `metrics` | JSON | `{ "load_time_before": 4.2, "load_time_after": 0.9 }` |
| `is_public` | BOOLEAN | only public proofs appear in gallery |
| `published_at` | TIMESTAMP | gallery go-live |

### 4. CampaignPage
Static campaign landing pages (`/local-business`, `/church-connect`, etc.).

| Field | Type | Notes |
|---|---|---|
| `campaign_key` | VARCHAR(32) PK | e.g. `local_services` |
| `title` | VARCHAR(255) | page title |
| `slug` | VARCHAR(64) UNIQUE | URL path |
| `accent_color` | CHAR(7) | hex brand color |
| `hero_headline` | TEXT | |
| `hero_body` | TEXT | |
| `cta_label` | VARCHAR(128) | |
| `cta_action` | ENUM | `book_call`, `pay_now`, `claim_proof` |
| `social_proof_ids` | JSON | list of `proof_id` references |
| `pricing_snapshot` | JSON | `{ "starter": 499, "growth": 1299 }` |

### 5. Testimonial / Quote
Short quotes used across pages.

| Field | Type | Notes |
|---|---|---|
| `quote_id` | INT PK | |
| `author` | VARCHAR(255) | |
| `role` | VARCHAR(128) | |
| `quote` | TEXT | |
| `source` | ENUM | `video`, `email`, `survey`, `linkedin` |
| `proof_id` | CHAR(16) FK | optional link |
| `is_active` | BOOLEAN | |

## Sample Collection Flow (Proof → Published Page)

```
[Client submits Preview intake]      /preview  POST
        ↓
[Pipeline: WF-01 Discover]           place_id, website, category
        ↓
[Pipeline: WF-02 Classify]           campaign_key, score
        ↓
[Pipeline: WF-03 Scan + WF-04 Score] before screenshot, performance metrics
        ↓
[Pipeline: WF-05 Proof Generation]   generates before/after assets + story
        ↓
[Pipeline: WF-06 Proof QA]           human or calibrated AI review
        ↓
[Publish paths]
  A. Campaign send      → Proof attached to outreach email
  B. Proof gallery      → ProofArtifact.is_public = true
  C. Case study page    → creates CaseStudy from multiple Proofs
```

### Content capture touchpoints

1. **Intake form** (`/preview`) — captures business name, website, category, pain point.
2. **AI scan** — captures before screenshot, Lighthouse metrics, brand signals.
3. **Proof generation** — AI writes transformation story; designer approves.
4. **Client reply / survey** — captures testimonial quote and consent.
5. **Campaign performance** — click/claim events update `ProofArtifact.metrics`.

### SSG/SSR rules

- `/` and campaign pages are SSG; content is read from JSON exported at build time.
- `/proof-gallery` is SSG but revalidated every 60s as new proofs are published.
- `/p/[slug]` and `/claim/[slug]` are SSR and read live from the hosted MySQL plane.

## Cross-promo wiring

The Designs homepage and each campaign page include a `cross_promo_hosting` SiteBlock that links to `https://famtastichosting.com/?ref=designs`. The Hosting site returns the favor with a `cross_promo_designs` block.
