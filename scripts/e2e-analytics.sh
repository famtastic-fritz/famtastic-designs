#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPORT="$("$REPO_ROOT/backend/vendor/bin/drush" famtastic:analytics-report)"

jq -e '
  (.campaigns | type == "array") and
  (.sources | type == "array") and
  (.proof_performance | length == 6) and
  (all(.proof_performance[]; (.direction | IN("a", "b", "c", "d", "e", "f")) and (.selections | type == "number"))) and
  (.definitions.revenue_minor | length > 0) and
  (all(.campaigns[];
    (.leads | type == "number") and
    (.sales | type == "number") and
    (.revenue_minor | type == "number") and
    (.conversion_rate >= 0)
  )) and
  (any(.campaigns[];
    (.campaign_key | startswith("journey-")) and
    .sales >= 1 and
    .revenue_minor >= 19900 and
    .addon_revenue_minor >= 7500 and
    .launches >= 1 and
    .renewals_paid >= 1
  ))
' <<<"$REPORT" >/dev/null

echo "PASS: campaign/source attribution, conversion, cost, revenue, launch, and renewal report contract verified."
