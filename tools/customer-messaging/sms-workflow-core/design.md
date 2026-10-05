# SMS workflow core design

This package is an operational component, not a visual widget. The owner UI belongs to the customer application and should fit that site's design system. It needs one plain status per message, a visible paused state, a quota meter with reset time, and an explicit preview/confirmation before a manual send. Template edits create a new version; already queued message snapshots stay immutable.

The backend boundary is one business-owned transaction before one provider call. Never let an agency-wide dashboard, build recipe or provider adapter become the appointment authority. The business application decides whether a confirmed appointment exists and whether a reply may change attendance state. A reusable component may shape that workflow but must not share a tenant's customer data or secret with another site.

The host's concurrency test should attempt two workers for one appointment/version and one remaining quota unit. Exactly one may reserve and at most one may call the provider. A cancellation or STOP racing with reservation must refuse or supersede unsent work; a send already handed to the provider needs an owner-visible audit and any appropriate follow-up rather than a claim of automatic reversal.
