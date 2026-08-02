#!/usr/bin/env bash
set -euo pipefail

# This acceptance fixture intentionally exercises the deterministic image-free
# pilot path. Customer outreach remains blocked unless a test opts in.
export FAMTASTIC_ALLOW_STUB_OUTREACH=1

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
campaign="e2e-import-$run_id"
source="licensed-e2e-fixture"
csv_path="$(mktemp "${TMPDIR:-/tmp}/famtastic-leads.XXXXXX.csv")"
first_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-leads-first.XXXXXX.json")"
second_result="$(mktemp "${TMPDIR:-/tmp}/famtastic-leads-second.XXXXXX.json")"
trap 'rm -f "$csv_path" "$first_result" "$second_result"' EXIT

suppressed_email="suppressed-$run_id@example.test"
qualified_email="qualified-$run_id@example.test"
unqualified_email="unqualified-$run_id@example.test"

{
  echo 'source_record_id,business_name,email,website_url,website_quality'
  echo "one,Suppressed Fixture,$suppressed_email,,"
  echo "two,Qualified Fixture,$qualified_email,https://qualified-$run_id.example,outdated"
  echo "three,Unqualified Fixture,$unqualified_email,https://unqualified-$run_id.example,good"
  echo "four,Invalid Fixture $run_id,not-an-email,,"
} > "$csv_path"

"$DRUSH" eval "\$l=\\Drupal::service('famtastic_pipeline.operational_ledger'); \$l->recordConsent('$suppressed_email', 'unsubscribed');"
"$DRUSH" famtastic:leads-import "$csv_path" --source="$source" --campaign="$campaign" > "$first_result"
"$DRUSH" famtastic:leads-import "$csv_path" --source="$source" --campaign="$campaign" > "$second_result"

test "$(jq -r '.counts.suppressed' "$first_result")" = "1"
test "$(jq -r '.counts.qualified' "$first_result")" = "1"
test "$(jq -r '.counts.unqualified' "$first_result")" = "1"
test "$(jq -r '.counts.invalid' "$first_result")" = "1"
test "$(jq -r '.counts.duplicate' "$second_result")" = "4"

prospect_id="$(jq -r '.rows[] | select(.status == "qualified") | .prospect_id' "$first_result")"
test "$prospect_id" != "null"
"$DRUSH" eval "
  \$storage = \\Drupal::entityTypeManager()->getStorage('famtastic_prospect');
  \$prospect = \$storage->load($prospect_id);
  assert(\$prospect->get('campaign')->value === '$campaign');
  assert(\$prospect->get('source')->value === '$source');
  \$db = \\Drupal::database();
  \$jobs = \$db->select('famtastic_job', 'j')->condition('job_key', 'proof.generate:prospect:$prospect_id')->countQuery()->execute()->fetchField();
  assert((int) \$jobs === 1);
  \$suppressed = \$storage->getQuery()->accessCheck(FALSE)->condition('public_email', '$suppressed_email')->count()->execute();
  assert((int) \$suppressed === 0);
"
"$DRUSH" famtastic:jobs-run --type=proof.generate --limit=100 >/dev/null
"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$proofs = \\Drupal::entityTypeManager()->getStorage('proof_campaign')->getQuery()->accessCheck(FALSE)->condition('prospect_id', $prospect_id)->execute();
  assert(count(\$proofs) === 1);
  \$campaign_id = reset(\$proofs);
  \$variants = \\Drupal::entityTypeManager()->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', \$campaign_id)->execute();
  assert(count(\$variants) === 3);
  \$directions = [];
  foreach (\\Drupal::entityTypeManager()->getStorage('proof_variant')->loadMultiple(\$variants) as \$variant) {
    \$directions[] = \$variant->get('direction_id')->value;
  }
  sort(\$directions);
  assert(\$directions === ['a', 'b', 'c']);
  \$outreach_jobs = \$db->select('famtastic_job', 'j')->condition('job_type', 'outreach.prepare')->condition('prospect_id', $prospect_id)->countQuery()->execute()->fetchField();
  assert((int) \$outreach_jobs === 1);
"

echo "PASS: lead attribution, suppression, dedupe, exactly-three isolated proofs, and outreach preparation verified."
