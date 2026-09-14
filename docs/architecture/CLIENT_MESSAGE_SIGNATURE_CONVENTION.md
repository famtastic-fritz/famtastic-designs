# Customer message signature convention

Owner instruction: September 14, 2026. This is shared guidance for Codex,
Claude, Gemini, Shay and delegated sessions working on FAMtastic correspondence.

Sign future agent-authored customer messages **Shay** or **Shay-Shay**. Use
**Shay-Shay** when spelling the full name. Preserve an approved template's
short or full form; either permitted form is acceptable for new correspondence.
Do not invent a formal/casual switching rule or sign as the implementation
agent. Explicit owner instructions for a particular message take precedence.

FAMtastic Concierge may remain the email's brand/header. It does not replace
the personal body sign-off. Configured SMTP sender identity is a separate
transport setting. Do not change that identity as a side effect of this rule.

## Existing implementation

Current signatures are per-template, not selected by a shared persona helper:

- `backend/web/modules/custom/famtastic_pipeline/src/Controller/PublicRequestController.php`
  uses the legacy spaced `Shay Shay` sign-off for contact/quote receipts.
- `backend/web/modules/custom/famtastic_pipeline/src/Service/CampaignMessageService.php`
  uses `Shay — Your AI Growth Partner` in its enhanced proof email.
- `backend/web/modules/custom/famtastic_pipeline/src/Service/DeepDiveInvitationService.php`
  supports an explicit signature override; its default is company-only.
- `backend/web/modules/custom/famtastic_pipeline/src/Service/ClientMessagingService.php`
  preserves the supplied reply body and does not append a personal signature.
- `backend/web/modules/custom/famtastic_pipeline/src/Service/OutreachMailer.php`
  configures the separate SMTP sender identity.

This instruction governs future authored correspondence. It does not claim
that all legacy templates have been migrated or that an automatic signature
feature has shipped. Do not silently modify an operator's composed text.
Previously sent messages remain immutable; no correction email is needed
solely to change their signatures.

`AGENTS.md` and the common operating contract link this convention.
`CLAUDE.md` is the existing symlink to `AGENTS.md`; `GEMINI.md` already requires
that same entry point. Cross-session Codex memory also records the owner's rule.
