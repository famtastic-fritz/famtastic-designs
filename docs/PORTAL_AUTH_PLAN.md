# Customer portal identity and access

## Decision

The permanent FAMtastic customer portal uses branded email/password accounts
backed by Drupal users and secure same-origin sessions. Drupal is the private
identity and customer system of record; customers interact only with the React
portal at `/login` and `/portal`.

Cryptographically random prospect links remain available for personalized
pre-sale proofs and conversion. They are scoped to prospect-safe data, expire,
can be revoked, and are not the permanent customer identity.

## Customer model

- `famtastic_customer` links one Drupal user to verified contact, acquisition,
  marketing, and optional prospect history.
- Every customer has at least one individual or business organization.
- Organization membership roles are owner, administrator, billing, and member.
- Orders, projects, prospects, entitlements, conversations, and activity are
  attached to the organization through non-enumerable public UUIDs.
- A matching campaign prospect is claimed during account creation so a buyer's
  acquisition and fulfillment history stays continuous.

## Security controls

- Email verification is required before portal access.
- Passwords use Drupal's password service; card data stays with Stripe/Commerce.
- Session cookies are HttpOnly and governed by Drupal's secure cookie settings.
- Registration, login, and recovery are flood/rate limited and return generic
  account-discovery responses.
- Verification and recovery tokens are 32 random bytes, hash-only at rest,
  single-use, purpose-scoped, and expiring.
- Every workspace API request resolves the current Drupal user, customer, and
  active organization membership before reading customer data.
- Browser storage never contains OAuth access tokens or long-lived secrets.

## Routes

- `/login` — branded customer sign-in and registration
- `/verify-email` — branded verification completion
- `/reset-password` — branded account recovery
- `/portal` — authenticated customer lifecycle workspace
- `/portal/:token` — temporary legacy project-link compatibility
- `/p/:token` — pre-sale personalized proof and checkout flow

Google sign-in may be added later as an external identity linked to the same
Drupal user/customer record. It must never create a duplicate customer solely
because the login provider changed.
