# FAMtastic scenario acceptance matrix

The canonical, machine-readable question set is
`backend/config/famtastic-scenarios.json`. It is designed to answer operational
questions in the form “what happens if…?” with an expected outcome, exact proof,
and one evidence classification.

Run the safe local/provider gate with:

```bash
./scripts/run-launch-gate.sh
```

Add read-only production checks and the account/portal browser suite with:

```bash
FAMTASTIC_PRODUCTION_SMOKE=1 \
FAMTASTIC_E2E_CUSTOMER_EMAIL='allowlisted-test-address' \
FAMTASTIC_E2E_CUSTOMER_PASSWORD='controlled-test-password' \
./scripts/run-launch-gate.sh
```

The production browser suite never performs a payment unless
`FAMTASTIC_RUN_SANDBOX_PAYMENT=1` is also explicitly supplied. The payment test
itself verifies Drupal Commerce, Stripe Payment Element, order completion, and
mobile overflow using Stripe test data only.

Evidence terms are intentionally non-overlapping:

- `internally_proven`: deterministic local fixture and isolated side effects.
- `test_provider_proven`: a real provider sandbox accepted the behavior.
- `production_smoke_tested`: controlled test identity on the production app.
- `launch_blocked`: intentionally unavailable until its named approval gate.

The current intentional block is real charging. Stripe Commerce stays in test
mode and the legacy custom checkout is disabled. Moving to live mode is a
separate owner action after reviewing the latest gate artifact; it is never an
automatic result of running this script.

The matrix also covers repeat website purchases. A customer can save and submit
multiple independent portal requests; the proof verifies that one request binds
to one Commerce order and project while another remains a draft, and that a
different customer receives a not-found response instead of learning whether
the request exists.
