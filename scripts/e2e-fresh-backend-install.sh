#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SANDBOX="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-fresh-install.XXXXXX")"
cleanup() {
  case "$SANDBOX" in
    "${TMPDIR:-/tmp}"/famtastic-fresh-install.*)
      chmod -R u+rwX "$SANDBOX" 2>/dev/null || true
      rm -rf "$SANDBOX"
      ;;
    *) echo "Refusing to remove unexpected sandbox: $SANDBOX" >&2 ;;
  esac
}
trap cleanup EXIT

mkdir -p "$SANDBOX/backend"
rsync -a \
  --exclude vendor \
  --exclude private \
  --exclude 'web/sites/default/files' \
  "$REPO_ROOT/backend/" "$SANDBOX/backend/"
chmod -R u+rwX "$SANDBOX/backend/web/sites/default"
# Drush intentionally redispatches to the Drupal project that owns its vendor
# directory. A symlink here silently points the test back at the developer
# site, so copy the installed dependencies and assert the resulting root below.
cp -R "$REPO_ROOT/backend/vendor" "$SANDBOX/backend/vendor"
mkdir -p "$SANDBOX/backend/web/sites/default/files" "$SANDBOX/backend/private"

(
  cd "$SANDBOX/backend"
  # Keep the root explicit and fail below if Drush ever bootstraps elsewhere.
  export DRUSH_ROOT="$SANDBOX/backend/web"
  DRUSH=(vendor/bin/drush --root="$DRUSH_ROOT")
  DB_URL="sqlite://sites/default/files/.ht.sqlite" ./setup.sh >/dev/null
  ACTUAL_ROOT="$("${DRUSH[@]}" status --field=root)"
  EXPECTED_ROOT="$(cd "$SANDBOX/backend/web" && pwd -P)"
  if [ "$ACTUAL_ROOT" != "$EXPECTED_ROOT" ]; then
    echo "Fresh-install test bootstrapped the wrong Drupal root: $ACTUAL_ROOT" >&2
    exit 1
  fi
  test -s "$SANDBOX/backend/web/sites/default/files/.ht.sqlite"
  "${DRUSH[@]}" en -y famtastic_pipeline >/dev/null
  "${DRUSH[@]}" updb -y >/dev/null
  "${DRUSH[@]}" cr >/dev/null
  "${DRUSH[@]}" eval '
    $schema = \Drupal::database()->schema();
    foreach ([
      "famtastic_campaign",
      "famtastic_offer_version",
      "famtastic_terms_version",
      "famtastic_consent",
      "famtastic_event",
      "famtastic_job",
      "famtastic_deployment",
      "famtastic_domain",
      "famtastic_hosting_entitlement",
      "famtastic_subscription",
      "famtastic_exception",
      "famtastic_lead_import",
      "famtastic_email_message",
      "famtastic_build_run",
      "famtastic_revenue_freshness",
    ] as $table) {
      assert($schema->tableExists($table), $table);
    }
    foreach (["claimed_at", "claim_token"] as $field) {
      assert($schema->fieldExists("famtastic_notification_outbox", $field), $field);
    }
    foreach (["recipient_address", "from_address", "body_snapshot", "proof_campaign_id", "proof_url"] as $field) {
      assert($schema->fieldExists("famtastic_email_message", $field), $field);
    }
    $ledger = \Drupal::service("famtastic_pipeline.operational_ledger");
    assert($ledger->activeOffer("essential_199")["amount_minor"] === 19900);
    assert($ledger->activeOffer("business_499")["amount_minor"] === 49900);
    assert($ledger->activeTerms() !== NULL);
  '
  FAMTASTIC_SYNTHETIC_RUN_ID="fresh-install-${RANDOM}" \
    "${DRUSH[@]}" php:script "$SANDBOX/backend/scripts/e2e-revenue-health.php" >/dev/null
)

echo "PASS: fresh isolated Drupal install, module schema, revenue freshness recovery, offers, and terms verified."
