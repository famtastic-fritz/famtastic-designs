#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
job_type="fixture.failure.$run_id"
job_key="fixture:failure:$run_id"

"$DRUSH" eval "
  \$ledger = \\Drupal::service('famtastic_pipeline.operational_ledger');
  \$first = \$ledger->enqueue('$job_key', '$job_type', ['fixture' => TRUE], NULL, 2);
  \$second = \$ledger->enqueue('$job_key', '$job_type', ['fixture' => TRUE], NULL, 2);
  assert(\$first === \$second);
"

first="$("$DRUSH" famtastic:jobs-run --type="$job_type" --limit=1)"
jq -e '.[0].status == "retry"' <<<"$first" >/dev/null
"$DRUSH" sqlq "UPDATE famtastic_job SET available_at = 0 WHERE job_key = '$job_key';"

set +e
second="$("$DRUSH" famtastic:jobs-run --type="$job_type" --limit=1 2>/dev/null)"
exit_code=$?
set -e
test "$exit_code" -ne 0
jq -e '.[0].status == "failed"' <<<"$second" >/dev/null

"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$job = \$db->select('famtastic_job', 'j')->fields('j')->condition('job_key', '$job_key')->execute()->fetchAssoc();
  assert(\$job['status'] === 'failed');
  assert((int) \$job['attempts'] === 2);
  \$exception = \$db->select('famtastic_exception', 'e')->fields('e')->condition('exception_key', 'job:$job_key')->execute()->fetchAssoc();
  assert(\$exception !== FALSE);
  assert(\$exception['status'] === 'open');
  assert(\$exception['category'] === '$job_type');
  assert((int) \$exception['retryable'] === 0);
"

echo "PASS: idempotent enqueue, bounded retry, exhausted failure, and actionable exception verified."
