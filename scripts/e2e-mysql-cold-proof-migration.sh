#!/usr/bin/env bash
set -euo pipefail

# Rehearses the additive 8042 cold-proof migration on a disposable local
# MariaDB container. It never connects to a configured FAMtastic database,
# sends mail, calls a provider, generates a proof, or touches production.

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
test_name="famtastic-cold-proof-mysql-migration"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/${test_name}.XXXXXX")"
container_name="${test_name}-$$"
database_name="coldproof_fixture"
database_user="coldproof"
database_password="coldproof-$(openssl rand -hex 12)"
root_password="root-$(openssl rand -hex 12)"

cleanup() {
  docker rm -f "$container_name" >/dev/null 2>&1 || true
  case "$sandbox" in
    "${TMPDIR:-/tmp}/${test_name}."*) rm -rf "$sandbox" ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
  esac
}
trap cleanup EXIT

for command_name in docker jq openssl rsync; do
  command -v "$command_name" >/dev/null || { echo "Missing required command: $command_name" >&2; exit 1; }
done
test -x "$repo_root/backend/vendor/bin/drush" || { echo "Run composer install in backend before this local acceptance test." >&2; exit 1; }
runtime_vendor="$(cd -P "$repo_root/backend/vendor" && pwd)"
runtime_backend="$(cd "$runtime_vendor/.." && pwd)"
test -d "$runtime_backend/web/core" || { echo "The installed Drupal runtime is missing web/core." >&2; exit 1; }
docker image inspect mariadb:11.4 >/dev/null 2>&1 || {
  echo "The local mariadb:11.4 image is required; pull it explicitly before running this isolated rehearsal." >&2
  exit 1
}

docker run -d --rm --name "$container_name" \
  -e MARIADB_ROOT_PASSWORD="$root_password" \
  -e MARIADB_DATABASE="$database_name" \
  -e MARIADB_USER="$database_user" \
  -e MARIADB_PASSWORD="$database_password" \
  -p 127.0.0.1::3306 mariadb:11.4 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null
for _ in $(seq 1 80); do
  docker exec "$container_name" mariadb-admin ping -uroot -p"$root_password" --silent >/dev/null 2>&1 && break
  sleep 0.25
done
docker exec "$container_name" mariadb-admin ping -uroot -p"$root_password" --silent >/dev/null
mysql_port="$(docker port "$container_name" 3306/tcp | sed -E 's/.*:([0-9]+)$/\1/' | head -1)"
test -n "$mysql_port"

mkdir -p "$sandbox/backend"
rsync -a --exclude vendor --exclude private --exclude 'web/sites/default/files' "$repo_root/backend/" "$sandbox/backend/"
rsync -aL "$repo_root/backend/vendor/" "$sandbox/backend/vendor/"
rsync -a "$runtime_backend/web/core/" "$sandbox/backend/web/core/"
rsync -a --ignore-existing "$runtime_backend/web/modules/" "$sandbox/backend/web/modules/"
rsync -a --ignore-existing "$runtime_backend/web/profiles/" "$sandbox/backend/web/profiles/"
rsync -a --ignore-existing "$runtime_backend/web/themes/" "$sandbox/backend/web/themes/"
for runtime_file in .ht.router.php .htaccess autoload.php autoload_runtime.php index.php robots.txt update.php; do
  cp "$runtime_backend/web/$runtime_file" "$sandbox/backend/web/$runtime_file"
done
cp "$runtime_backend/web/sites/default/default.settings.php" "$sandbox/backend/web/sites/default/default.settings.php"
mkdir -p "$sandbox/backend/web/sites/default/files" "$sandbox/backend/private"
chmod -R u+rwX "$sandbox/backend/web/sites/default"
perl -0pi -e 's/\n\$databases\['\''default'\''\]\['\''default'\''\] = array \(\n.*?\n\);\n/\n/s' "$sandbox/backend/web/sites/default/settings.php"

drush=("$sandbox/backend/vendor/bin/drush" "--root=$sandbox/backend/web")
"${drush[@]}" site:install standard \
  --db-url="mysql://${database_user}:${database_password}@127.0.0.1:${mysql_port}/${database_name}" \
  --account-name=admin --account-pass=admin --account-mail=admin@famtastic.local \
  --site-name="FAMtastic Cold Migration Fixture" --site-mail=no-reply@famtastic.local -y >/dev/null
"${drush[@]}" en -y famtastic_pipeline >/dev/null
"${drush[@]}" updb -y >/dev/null
"${drush[@]}" cr >/dev/null

# Simulate the exact prior 8042 shape: populated ingress records existed but
# did not yet have the optional proof_campaign_id identity link/index.
"${drush[@]}" php:eval '
  $db = \Drupal::database();
  $schema = $db->schema();
  $schema->dropIndex("famtastic_cold_proof_ingress", "proof_campaign");
  $schema->dropField("famtastic_cold_proof_ingress", "proof_campaign_id");
  $now = \Drupal::time()->getRequestTime();
  $cohort = $db->insert("famtastic_cold_proof_cohort")->fields([
    "cohort_key" => "mysql-legacy-fixture", "campaign_id" => 1, "campaign_key" => "mysql-legacy-fixture", "source_name" => "fixture",
    "package_profile" => "anonymous_safe_medium_ultra_v1", "direction_count" => 3, "direction_contract" => "{}", "profile_snapshot_hash" => str_repeat("a", 64),
    "source_lane" => "verified_cold", "status" => "seeded", "created" => $now, "changed" => $now,
  ])->execute();
  $db->insert("famtastic_cold_proof_ingress")->fields([
    "ingress_key" => hash("sha256", "mysql-legacy-fixture"), "cohort_id" => $cohort, "source_record_id" => "legacy-row", "source_lane" => "verified_cold",
    "source_url" => "https://example.test/source", "source_provenance" => "local fixture", "source_timeframe" => "checked 2026-08-27",
    "website_observation_status" => "verified_present", "website_observation_fact" => "Fixture fact", "corroborated_fact" => "Fixture corroboration", "proof_teaser" => "Fixture teaser",
    "evidence_hash" => str_repeat("b", 64), "status" => "seeded", "created" => $now, "changed" => $now,
  ])->execute();
  module_load_include("install", "famtastic_pipeline");
  $sandbox = [];
  famtastic_pipeline_update_8042($sandbox);
  $row = $db->select("famtastic_cold_proof_ingress", "i")->fields("i", ["proof_campaign_id"])->condition("source_record_id", "legacy-row")->execute()->fetchAssoc();
  print json_encode([
    "field" => $schema->fieldExists("famtastic_cold_proof_ingress", "proof_campaign_id"),
    "index" => $schema->indexExists("famtastic_cold_proof_ingress", "proof_campaign"),
    "legacy_is_null" => $row !== FALSE && $row["proof_campaign_id"] === NULL,
  ]);
' > "$sandbox/result.json"

jq -e '.field == true and .index == true and .legacy_is_null == true' "$sandbox/result.json" >/dev/null
echo "PASS: update 8042 adds the nullable canonical proof link/index to a populated legacy cold table on disposable MariaDB without inventing historical campaign IDs."
