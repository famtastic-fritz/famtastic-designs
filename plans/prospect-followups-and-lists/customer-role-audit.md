# Registered-customer lookup and agency access audit — 2026-09-14

## Finding and completed outreach

The owner identified the missing lookup scope: customers and registered Drupal users must be searched independently of prospects. The account exists as Tarian Lee / Travel Addicts Courier Express, customer 6 / user 6 / organization 6, with a verified email and no prospect, website request, existing conversation or claimed project resource. The initial prospect-led search missed this valid customer-only registration; the earlier no-match conclusion was incomplete.

The already-authorized follow-up was personalized to courier/delivery services, service area, pickup/quote/contact goals and existing assets. It carries owner-selected special discounted-pricing language, with a scoped quote confirmed before payment, and is signed Shay. No amount, offer, project, proof or payment state was invented.

Message 24 in customer-scoped thread 19 was sent exactly once through outbox 637 (`client-message:24:customer`) at **2026-09-14T22:43:31Z** / **06:43:31 PM EDT**. SMTP acceptance is confirmed with a provider message ID; recipient inbox placement/read is not claimed. The first message is authored by staff user 1, not fabricated as an inbound customer request. Its branded template is `customer_message_reply/v1`. The live staff inbox shows the correct recipient, greeting, discount language, Shay sign-off and email-service acceptance.

## Effective access audit

All 12 registered users, all 6 role definitions and all 12 active organization memberships were read from production without changing roles.

- User 1 (`fritz.medine@gmail.com`) is the only assigned Administrator and the only account with `administer famtastic pipeline`, user/role/site administration or all-order administration permissions.
- User 7 has `famtastic_proof_reviewer`, which grants only `review famtastic website proofs`; it is not an admin role and does not grant the staff inbox or user/role management.
- Every other registered account has customer/authenticated roles with no FAMtastic administrative permissions. Anonymous/authenticated/customer role definitions do not carry an admin flag or agency-administration permission.
- All 12 membership rows say `owner` because each customer owns their own organization. Agency organization 3 (`FAMtastic Designs`) has only customer 3 / user 1 as a member. The owner label is scoped to that organization and does not imply ownership of the agency.
- The registration controller explicitly creates only the authenticated role; the user-insert hook adds the customer role. The portal's staff capability is derived from the actual Drupal `administer famtastic pipeline` permission, not the organization-owner label or request input.

Four actual administrative route-access checks for every registered account confirm only user 1 is allowed: Operations, staff Messages, Users and Roles. The production organization-authorizer accepts the agency workspace only for user 1. The new Travel Addicts conversation is accessible only to user 1 (staff) and user 6 (its verified customer); every other registered user is denied. All customer-scope read probes ran inside a rolled-back transaction, preserving contact claims and read state. No access correction was necessary and no roles or memberships were changed.

Sanitized per-user access results are in `customer-role-verification.json`. Private account listings, exact mail rendering and provider receipts remain in ignored `.artifacts/customer-role-audit-20260914/`. This task changes documentation and the one authorized conversation/send; runtime source remains backend `ff18b916` and frontend `fea57649`.
