# Independent signup for The Locs Letter

Candidate source addition, September 14, 2026. Deployment and provider delivery require separate release evidence. The newsletter is part of Tighten Up Your Locs' own application/database; it does not use agency identity, tables, APIs or marketing lists.

## Public contract

`POST /api/newsletter/signup`, JSON `{ "email": "reader@example.com", "consent": true, "website": "" }`, exact Locs Origin. The explicit consent checkbox must start unchecked. Never copy booking details or auto-enroll appointment customers. Success returns `{ "status": "pending", "message": "Check your email to confirm your subscription." }` after a durable subscription/confirmation queue transaction. Duplicate and already-subscribed addresses receive the same response without exposing membership or sending another email. Honeypot/validation failures return 422, cross-origin 403, unavailable 503, throttling 429.

Independent `newsletter_subscribers`, `newsletter_outbox` and `newsletter_locks` tables are created by migration. `NEWSLETTER_ENABLED=false` is the default. Signup also requires existing `LOCS_MAIL_ENABLED=true`; production dispatch requires SMTP. Enabling this flag does not authorize newsletter campaigns. This release has **no campaign send, bulk export, appointment enrollment or third-party list synchronization**.

Confirmation tokens are random 256-bit values, hashed in the subscriber record, expire after 24 hours, and are single-use. GET only shows the branded confirmation page; POST requires CSRF and records confirmation. Opening a link in an email scanner does not subscribe anyone. Resending after a 15-minute cooldown (maximum three confirmations per address per UTC day) replaces the confirmation token. A former subscriber remains unsubscribed until a new signup and a new confirmation are both completed.

Unsubscribe tokens are independent random 256-bit values with persistent hashes; they do not expire, and GET does not mutate. The CSRF-protected unsubscribe POST works even with signup/mail disabled, invalidates outstanding confirmations, and leaves all appointments unchanged. Raw link material is encrypted at rest using the independent application key. Never log links or expose tokens through analytics, owner JSON or public messages.

## Delivery and owner visibility

`locs:dispatch-newsletter` dispatches requested confirmation messages only. The independent minute scheduler invokes it. Queue and membership commit together; a newsletter-only write lock serializes duplicate signup and suppression with send preflight without locking the booking tables. Each notice pins `locs_newsletter_confirmation` v1 and stores its encrypted link payload. Sender rechecks the current token/version and expiry, then records provider Message-ID on acceptance. SMTP acceptance is not inbox delivery or readership. Ambiguous send/crash becomes `uncertain`; it never retries automatically. After provider review, `locs:dispatch-newsletter --retry=EXACT_UUID` explicitly accepts possible duplicate delivery. Superseded/expired notices never send.

`/admin/newsletter` is Shay's read-only subscriber page, linked from the booking desk. It uses the same verified independent owner authentication as `GET /admin/api/newsletter/subscribers`. Both show exact status counts, the newest 100 subscriber rows, a truthful partial-list note and queued/uncertain delivery counts. No bearer tokens or encrypted payloads are projected. Public pages, admin pages and future mail must never imply a pending address is subscribed.

The transaction containing SMTP is explicitly single-attempt: a database deadlock or connection failure after SMTP acceptance must become uncertain, never transparently replay the send. Database-only signup and lease operations may use bounded transaction retries. A regression test simulates the concurrency-class database failure after SMTP acceptance and requires exactly one send.

## Release checks

Run the complete application test suite. Verify both feature flags and local SMTP account before enabling signup. Prove a controlled signup → confirmation → unsubscribe lifecycle with real provider receipt, no accidental campaign send, and cleaned fixtures before claiming production-ready. Backup/restore of the independent database includes these tables and encrypted link material; preserve the independent APP_KEY in private backups. The creative public form and privacy policy must describe explicit newsletter signup separately from booking requests.
