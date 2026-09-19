# FAMtastic email brand system v1

## Required action/navigation rule — September 18 correction

Keep HTML actions as named, real buttons with at least 44px touch height; do not
show raw URLs. Plain-text alternatives retain usable URLs. Personal proof copy
uses the existing `customer_proof_ready/v4` adapter and one account-bound portal
destination. Never send `/web/admin` or `/web/api` proof documents as customer
entry links. Sign-in must retain the exact project and return to Concepts.
This is an implementation correction to the existing shell, not a second mail
system. Standard/v2 compatibility rendering now uses named safe-link buttons.
Historical delivered mail remains unchanged. [Incident and proof](PROOF-EMAIL-NAVIGATION-2026-09-18.md).

## September 18 — Shared branding for every active agency notification

Owner requested replacement of all old email layouts after a verified-registration
alert still used the legacy green shell. `BrandedEmail` now owns the one approved
HTML shell; standard notifications, intake, proof-ready, revision acknowledgments,
conversation replies and staging reviews all delegate to it. No new renderer may
copy a full HTML shell or use historical showcase/mockup HTML as a sending template.

Message subjects, plain-text AltBody, recipients, queue keys, transport, unsubscribe
headers and template-specific CTA extraction remain unchanged. Staging-only claims
stay in the staging adapter. New template versions: standard/intake/revision/reply
v2, proof-ready v4; staging remains v1 because its approved design is reused.
Previously queued versions remain accepted but receive the approved shared branding;
sent history is never modified or resent. This owner-authorized visual compatibility
migration is explicit, not a claim that historical HTML has changed.

Run `php scripts/email-preview/test.php` and the six-template responsive harness
before changing any renderer. CI runs the presentation contracts. See
`docs/design/SHARED-EMAIL-BRAND-2026-09-18.md` for inventory and release evidence.


Owner-approved direction: September 17, 2026. Rendered implementation status:
**approved by owner for release and one Valerie email**. See
[release record](EMAIL_BRAND_RELEASE_2026-09-17.md) for authorization and actual receipts.

## Source and scope

Use the supplied rich black/lime email image as the visual target, and the supplied
table-based HTML as the structural foundation. Preserve the owner-supplied logo
exactly: `assets/famtastic-designs-logo-v1.png` (2172 × 724 RGBA PNG), SHA-256
`ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
Source filename: `ChatGPT Image Sep 17, 2026, 08_45_12 AM.png`. Owner supplied it
for this use; no independent trademark/third-party-rights determination is implied.
No AI redraw, recoloring, vector reconstruction, or baked-in message text.

Creative Studio guidance is applied by preserving the original artwork and
keeping typography in editable HTML. No image provider or new paid tool was used.
The identical PNG is now hosted by the approved release recorded above; the
documentation copy remains the provenance reference.

## Canonical visual DNA relationship

[FAMtastic Design System](FAMTASTIC-DESIGN-SYSTEM.md) owns visual grammar; this file
owns email-specific adaptation. September 17 brand enhancement audit found the
existing renderer already uses the approved logo, obsidian/lime/warm-paper tokens
and one CTA glow. It therefore remains unchanged. The crown inside the original
logo is canonical; no independent ornament or second EmailShell is needed.
Re-run `scripts/email-preview/test.php` and browser previews when tokens change.

## Presentation contract

- 620px desktop presentation table; stack header/footer at 640px and below.
- Canvas `#070907`, lime `#7cfc00`, warm paper `#f7f7f4`, ink `#252925`.
- Prominent original logo, “More than a website… A FUTURE.” header, warm-white
  reading panel, one lime CTA, service strip, branded footer and verified address.
- Real HTML headline, paragraphs and button; Arial/Helvetica body, italic system
  display/Georgia accents. Brush lettering and textured artwork in the reference
  are approximated with email-safe live type and understated CSS decoration,
  not claimed pixel-identical. No remote fonts or invented logo variants.
- One CTA glow. Gradients, rounded corners, glyph ornaments and watermark opacity
  are progressive enhancements. The watermark occupies separate whitespace, never
  behind text; if opacity is ignored, it remains a non-obstructing duplicate logo.
- No placeholder phone, unverified social link, preference/unsubscribe placeholder,
  or unimplemented browser-view destination. This fixture is transactional, not
  a marketing campaign; campaign compliance remains a separate contract.

## Original staging adapter integration (current shared shell described above)

`OutreachMailer` recognizes `customer_staging_review_ready/v1` and delegates only
that new template to `StagingReviewEmail`. The September 18 migration now shares the shell with the other active templates.
Rendering itself does not switch queue producers, emit events or send mail.
Plain text remains the durable body and existing PHPMailer `AltBody`.

The terminal system-authored plain-text block is required:

```text
Review your staging site:
https://CLIENT.famtasticinc.com/
```

The renderer accepts only HTTPS, one valid subdomain under `famtasticinc.com`,
root path, no credentials, port, query or fragment. Customer-body URLs are escaped
text, not links; only that terminal destination becomes the CTA. Recipient/project
authorization belongs to the future producer and must be verified before sending.
All message/subject content is HTML-escaped. No raw `body_html` slot is accepted.

Normal mail rendering requires Drupal setting `famtastic_staging_review_logo_url`
with an approved HTTPS PNG on `famtasticdesigns.com` (or www). After visual approval,
the default is `https://famtasticdesigns.com/brand/famtastic-designs-logo-v1.png`;
invalid overrides fail closed. Hosting and exact recipient dispatch are authorized
in the linked release record. Actual email-client tests remain unperformed, and
automatic producer wiring remains subsequent work.
The explicit pure-render local preview allows only the fixed relative asset path;
`OutreachMailer` never enables this preview flag.

## Preview and evidence

See [local preview instructions and file/test inventory](../../scripts/email-preview/README.md).
Valerie's approved copy uses The Signal Room, explains staging and no payment due,
links `https://prosintraining.famtasticinc.com/`, and signs Shay.
The related site footnote belongs to the separate `site-pros-in-training`
repository; its later approved staging publication is recorded in the release
record, not part of this visual-DNA review branch.

Local browser proof is not Gmail, Outlook or Apple Mail proof. The earlier release
records SMTP acceptance separately; previews establish neither recipient inbox
placement nor client approval nor charging. This enhancement sends nothing.
