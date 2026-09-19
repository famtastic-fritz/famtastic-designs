#!/usr/bin/env bash
# Installed CLI smoke only; no claim, enrollment, provider, mail or worker run.
set -euo pipefail
test "${FAMTASTIC_PROOF_RUNTIME_READY:-0}" = 1
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
drush=(php "$repo_root/backend/vendor/drush/drush/drush.php" "--root=$repo_root/backend/web")
runtime_root="$("${drush[@]}" status --field=root)"
case "$runtime_root" in
  */famtastic-customer-proof.??????/repo/backend/web|*/famtastic-selected-drupal.??????/backend/web) ;;
  *) echo 'Bounded health smoke requires the disposable canonical runtime.' >&2; exit 1 ;;
esac
snapshot() {
  "${drush[@]}" php:eval '$db=\Drupal::database(); if ($db->getConnectionOptions()["driver"]!=="sqlite") throw new \RuntimeException("SQLite required"); $out=[]; foreach (["famtastic_job","famtastic_notification_outbox","famtastic_worker_claim","famtastic_worker_budget"] as $name) $out[$name]=(int)$db->select($name,"t")->countQuery()->execute()->fetchField(); print json_encode($out);'
}
before="$(snapshot)"
for command in famtastic:automation-health famtastic:automation-tick; do
  result="$("${drush[@]}" "$command")"
  jq -e '.schema == "famtastic.automation-health.v1" and .php_sapi == "cli" and .mode == "observe_only" and .queue_mutations == 0 and .enrolled_count == 0 and .reserved_cents == 0 and .laptop_independence_proven == false' <<<"$result" >/dev/null
done
test "$(snapshot)" = "$before"
echo 'PASS: installed bounded CLI health/tick are observe-only with unchanged queue, outbox, claims and budget.'
