# Tighten Up Your Locs independent application — September 14, 2026

Owner correction supersedes the earlier FAMtastic-portal runtime decision. Tighten Up Your Locs is an independent business application, not a customer workspace module. The existing portal deployment does not satisfy this contract.

## Required boundary

- Same-domain `/admin/` login and booking management, with Locs branding.
- Dedicated MySQL database and least-scoped database user on the existing Locs hosting account. No Drupal tables, identities, sessions, APIs, queues or availability dependency at runtime.
- Independent password authentication, CSRF protection, secure host-only session cookies, throttled login/reset, owner-only authorization. No copied agency passwords and no public owner registration.
- Dedicated requests, appointments, openings, owner accounts, sessions, reset tokens, audit and notification tables. Atomic conflict authority and replay protection. Failed rescheduling preserves the original appointment.
- Separate private source repository `https://github.com/famtastic-fritz/site-tighten-up-your-locs`, with `application/`, `gateway/`, `public/` and `ops/` at its root; reusable components may be shared as pinned build-time packages. The old agency `customer-apps/tighten-up-your-locs/` location is retired and contains only a pointer. Existing public design remains in place. Agency footer attribution is not a runtime dependency.
- Framework: Laravel 12 (PHP 8.3 compatible), Composer-pinned dependencies and audit. Dedicated configuration/secrets/storage outside public document root. No customer data or credentials in Git.

## Migration and acceptance

Current source ownership and release evidence are in
`docs/architecture/LOCS_REPOSITORY_MIGRATION_2026-09-14.md`. The September 14
repository-only release changed no database, owner, credentials, scheduler or URLs;
the runtime/data cutover described below is historical and must not be repeated.

Read-only production inventory found one Locs request and zero appointments, appointment events or openings on September14. Recheck at cutover, preserve records and identifiers, reconcile count/hash evidence, and retain a recoverable source archive. Never delete agency-side records as an implementation shortcut.

Prove authentication/CSRF, request persistence, owner acceptance, same-slot concurrency, stale revision, replay, failed-reschedule rollback, notification retry, unauthorized denial, mobile reload and backup/restore. Same-origin booking must work when the agency application is unavailable. No new payment, external calendar or teaching capability is implied.

Keep old production paths operational until the independent application passes its gates. The cutover must prevent a split booking authority; old portal actions must become retired/read-only for this site. Deployment and migrated production data are separate from local tests. Update all release and training records with the actual independent URL only after it is live.
