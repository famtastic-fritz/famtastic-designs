# Email Campaign Automation

## Safety contract

Imported leads never send email directly. The sequence is:

1. a qualified, non-suppressed lead generates exactly three proofs;
2. an `outreach.prepare` job creates one idempotent staged message;
3. an operator explicitly approves the exact campaign key;
4. approval queues its staged messages;
5. each send rechecks campaign approval and suppression;
6. the real transport additionally requires
   `FAMTASTIC_ALLOW_REAL_OUTREACH=true`.

The local `memory` transport persists the same lifecycle without contacting an
external recipient. It is the only transport used by automated tests.

## Explicit approval

Review campaign source, recipients, suppression results, template, proof links,
and legal/compliance requirements. Then repeat the exact campaign key:

```bash
vendor/bin/drush famtastic:campaign-approve CAMPAIGN \
  --confirm=CAMPAIGN
```

This command is intentionally unsuitable for wildcard or bulk approval.

For local verification only:

```bash
FAMTASTIC_EMAIL_TRANSPORT=memory \
  vendor/bin/drush famtastic:jobs-run --type=outreach.send
```

Real sending requires both the campaign approval above and environment-owned
configuration:

```bash
FAMTASTIC_EMAIL_TRANSPORT=real
FAMTASTIC_ALLOW_REAL_OUTREACH=true
FAMTASTIC_PUBLIC_BASE_URL=https://famtasticdesigns.com
FAMTASTIC_OUTREACH_POSTAL_ADDRESS='A valid street address, registered PO box, or registered private mailbox'
```

Do not commit these values. Enabling real outreach is an explicit production
approval, not a routine deployment step. The postal address is mandatory for
real transport; the sender fails closed when it is absent. The proof-ready
template identifies the message as an advertisement, explains why the public
business contact was selected, includes the postal address, and provides the
one-click suppression link.

The configured `support_from_email` must be a real mailbox or permitted alias
that matches the authenticated SMTP account. The install default is
`hello@famtasticdesigns.com`. A successful Drupal mail call proves only that
the transport accepted the message; delivery requires either inbox evidence or
a provider delivery event.

When the contributed SMTP module is enabled, `system.mail` must select
`SMTPMailSystem`. Setting `smtp.settings:smtp_on` alone is insufficient; a
drifted `php_mail` default bypasses the configured SMTP transport. Verify both
settings before a live campaign:

```bash
drush config:get smtp.settings smtp_on
drush config:get system.mail interface.default
```

For the current cPanel mailbox, the proven production settings are authenticated
SSL/TLS on `famtasticdesigns.com:465`, using
`hello@famtasticdesigns.com`. Keep the password in environment-owned Drupal
configuration and never in Git.

## Tracking and provider events

Messages store only a recipient hash. Opaque random keys support:

- open: `/api/pipeline/email/open/{tracking_key}`;
- click/magic-link issuance: `/api/pipeline/email/click/{tracking_key}`;
- unsubscribe: `/api/pipeline/email/unsubscribe/{unsubscribe_key}`.

Provider delivery, bounce, and complaint events post JSON to
`/api/pipeline/email/provider-event` with:

```text
X-FAMtastic-Signature: sha256=<HMAC-SHA256(raw body)>
```

The secret is `FAMTASTIC_EMAIL_WEBHOOK_SECRET`. Event IDs are idempotency keys.
Bounces, complaints, and unsubscribes write suppression records that are checked
during import, staging, and immediately before send.

## Verification

```bash
./scripts/e2e-email-campaign.sh
```

The test proves mismatched approval is rejected, memory delivery is persisted,
open and click are attributed, provider signatures are verified, duplicate
events are ignored, and unsubscribe suppresses future outreach.
