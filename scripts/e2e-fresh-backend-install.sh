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
ln -s "$REPO_ROOT/backend/vendor" "$SANDBOX/backend/vendor"
mkdir -p "$SANDBOX/backend/web/sites/default/files" "$SANDBOX/backend/private"

(
  cd "$SANDBOX/backend"
  DB_URL="sqlite://sites/default/files/.ht.sqlite" ./setup.sh >/dev/null
  vendor/bin/drush en -y famtastic_pipeline >/dev/null
  vendor/bin/drush updb -y >/dev/null
  vendor/bin/drush cr >/dev/null
  vendor/bin/drush eval '
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
    ] as $table) {
      assert($schema->tableExists($table), $table);
    }
    $ledger = \Drupal::service("famtastic_pipeline.operational_ledger");
    assert($ledger->activeOffer("essential_199")["amount_minor"] === 19900);
    assert($ledger->activeOffer("business_499")["amount_minor"] === 49900);
    assert($ledger->activeTerms() !== NULL);
  '
)

echo "PASS: fresh isolated Drupal install, module schema, updates, offers, and terms verified."
