# Pipeline analytics

Run `drush famtastic:analytics-report` to return JSON grouped by campaign and
lead source. The report includes attributed leads, qualified leads, ready proofs,
paid sales, verified revenue, launches, renewal payments, conversion rate, cost
per sale, and average time to launch.

Definitions are embedded in every report so operators and agents use the same
math. Currency values use minor units (cents). Payments count only when the
authoritative order state is `paid`; launches and renewals come from the
append-only event ledger.
