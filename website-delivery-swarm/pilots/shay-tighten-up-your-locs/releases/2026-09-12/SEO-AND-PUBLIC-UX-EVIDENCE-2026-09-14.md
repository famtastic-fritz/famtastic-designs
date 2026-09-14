# Public-site SEO and UX evidence

Reviewed 2026-09-14. This is source and local-browser evidence, not a claim of deployment, Google indexing, rankings, email delivery, or production newsletter enrollment. The release orchestrator owns live verification.

## Implemented in this release

- A concise homepage title and description identify Sisterlocks care, Port St. Lucie, Shay, and Tighten Up Your Locs. The approved editorial hero heading remains; visible supporting text and the service heading explain the local service without keyword-stuffed repetitions or thin location pages.
- The homepage has one HTTPS apex canonical, index/follow metadata, Open Graph and Twitter image/title/description metadata, and meaningful illustration alt text. All four approved images retain their illustration/not-client-work disclosure and now declare dimensions measured from their actual files.
- Parseable JSON-LD describes the business as an Organization, its WebSite, and its local Service. It does not invent a physical address, phone, opening hours, certification, ratings, reviews, or customer transformation evidence. A Google LocalBusiness rich-result claim is deliberately not made without a verified public business address.
- `sitemap.xml` contains only the homepage, Privacy Policy, and Website Terms, using the same canonical host. It has no fabricated modification dates. `robots.txt` points to this sitemap and excludes private administration/API/appointment-action paths from crawling. Robots directives are not access control; independent application authentication remains responsible for private records.
- The gallery loops through four approved images on a fresh six-second timer, with 44-by-44-pixel dot targets, horizontal swipes, keyboard navigation, and an accessible keyboard/focus-revealed pause mechanism. There is no visible previous/next/playback control bar. Hover, keyboard focus, pointer interaction, hidden tabs, and reduced-motion preferences suspend automatic playback as documented in `design.md`.
- The Locs Letter is a Ruby Signal/warm-gold editorial signup component, not another booking field. It sends only email, explicit unchecked marketing consent, and the honeypot to the independent same-origin endpoint. A pending response asks the visitor to confirm by email; it does not claim an active subscription or inbox delivery. Errors, throttling, and unavailable service remain distinct. No newsletter form value is sent to Analytics.

## Current primary guidance used

- Google recommends descriptive, concise title text and may generate a different title link. The title change is a source improvement, not a guaranteed displayed search title. [Google title-link guidance](https://developers.google.com/search/docs/appearance/title-link).
- Google can use page content or a meta description for search snippets. The page now offers a factual local description, without a promised snippet or ranking. [Google snippet guidance](https://developers.google.com/search/docs/appearance/snippet).
- Canonical signals and sitemap URLs should identify the preferred public URLs. This release uses fully qualified canonical URLs consistently. A sitemap helps discovery but does not guarantee indexing. [Google canonicalization guidance](https://developers.google.com/search/docs/crawling-indexing/canonicalization), [Google sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).
- Organization markup can clarify business identity. Google's LocalBusiness documentation lists name and address as required properties for its local-business feature; an unverified address must not be invented to satisfy a validator. [Google Organization guidance](https://developers.google.com/search/docs/appearance/structured-data/organization), [Google LocalBusiness guidance](https://developers.google.com/search/docs/appearance/structured-data/local-business).
- Local visibility depends on relevance, distance, and prominence as well as accurate Business Profile information; on-page work alone cannot establish the result. [Google local ranking guidance](https://support.google.com/business/answer/7091?hl=en).

## Verification performed

- 36 focused Node tests passed: existing same-origin booking, receipt, analytics and footer contracts; six newsletter cases; ten gallery cases; five SEO/source checks. Tests cover unloaded images, wraparound, reduced motion, focus/hover/visibility pause, horizontal-versus-vertical gestures, explicit consent, pending-versus-subscribed state, malformed responses, throttling and unavailable service.
- CUA Chrome visual checks used only the local static site at `127.0.0.1:8771`: 390x844, 768x1024, and 1280x900. The hero and newsletter fit without horizontal document overflow at all three widths. All four images loaded and every dot measured 44x44 pixels. The editorial panel stacks on mobile and preserves readable form spacing.
- Actual browser observations confirmed automatic slide changes, dot selection, keyboard ArrowRight navigation, and persistent keyboard pause. Swipe direction/vertical-scroll exclusion and reduced-motion conditions have unit coverage; physical touch-device and OS-preference checks were not performed.
- An explicitly synthetic local newsletter email was submitted only to the local static server, which has no newsletter backend. Its unsuccessful response displayed an honest unverified-request message, preserved the form, and did not claim subscription. Successful durable signup, confirmation, unsubscribe, SMTP delivery and owner-list persistence belong to the separate backend/production acceptance checks.
- Browser logs included asynchronous listener/message-channel errors without a site-code stack; no gallery or newsletter runtime exception was observed. This is not a claim of a clean third-party browser-extension environment. Temporary viewport overrides were reset.

## Still unknown or requiring external acceptance

Google Business Profile ownership, verification, service-area/address settings, categories, duplicates, reviews and map visibility; authoritative public address/phone/hours; Search Console ownership and URL Inspection; actual crawl/index status and canonical selected by Google; real search queries/rankings; production redirects and published sitemap response; rich-result validation; field Core Web Vitals; actual GA receipt; newsletter confirmation and unsubscribe delivery. No SEO score, ranking guarantee, certification, or client before/after claim has been invented.

The public newsletter is independent of booking consent. No bulk newsletter campaign was created or sent by this public-site work. Campaign sending remains a separate owner-authorized action.
