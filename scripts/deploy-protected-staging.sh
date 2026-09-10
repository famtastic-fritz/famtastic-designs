#!/usr/bin/env bash
set -euo pipefail

# Guarded cPanel deployment for the isolated protected-staging vhost. It is
# deliberately incompatible with the production deployment scripts: no
# public_html, no copied data, no scheduler, and no live provider settings.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MODE="${1:-}"
STAGING_HOST="${FAMTASTIC_STAGING_HOST:-staging.famtasticdesigns.com}"
STAGING_ADDRESS="${FAMTASTIC_STAGING_ADDRESS:-$STAGING_HOST}"
STAGING_REF="${FAMTASTIC_STAGING_REF:-}"
REPOSITORY_URL="${FAMTASTIC_STAGING_REPOSITORY_URL:-$(git -C "$REPO_ROOT" remote get-url origin 2>/dev/null || true)}"
SSH_TARGET="${FAMTASTIC_STAGING_SSH_TARGET:-}"
CPANEL_HOME="${FAMTASTIC_STAGING_HOME:-}"
STAGING_ROOT="${FAMTASTIC_STAGING_ROOT:-}"
DOCROOT="${FAMTASTIC_STAGING_DOCROOT:-}"
DB_CREDENTIAL_FILE="${FAMTASTIC_STAGING_DB_CREDENTIAL_FILE:-}"
EVIDENCE_ROOT="${FAMTASTIC_STAGING_EVIDENCE_ROOT:-$REPO_ROOT/.protected-staging/evidence}"
APPLY_CONFIRM="${FAMTASTIC_STAGING_APPLY_CONFIRM:-}"
BASIC_AUTH_USER="${FAMTASTIC_STAGING_BASIC_AUTH_USER:-}"
BASIC_AUTH_PASSWORD="${FAMTASTIC_STAGING_BASIC_AUTH_PASSWORD:-}"

fail() { echo "protected-staging: $*" >&2; exit 2; }

usage() {
  cat <<'USAGE'
Usage: scripts/deploy-protected-staging.sh --preflight|--dry-run|--apply

Required for every mode:
  FAMTASTIC_STAGING_REF=refs/heads/<pushed-branch>
  FAMTASTIC_STAGING_REPOSITORY_URL=<read-only Git URL>

Required only for --apply:
  FAMTASTIC_STAGING_APPLY_CONFIRM=DEPLOY_PROTECTED_STAGING:<exact-SHA>
  FAMTASTIC_STAGING_SSH_TARGET=<cpanel-user@host>
  FAMTASTIC_STAGING_HOME=/home/<cpanel-user>
  FAMTASTIC_STAGING_ROOT=/home/<cpanel-user>/famtastic-staging
  FAMTASTIC_STAGING_DOCROOT=/home/<cpanel-user>/famtastic-staging/current/public
  FAMTASTIC_STAGING_DB_CREDENTIAL_FILE=/home/<cpanel-user>/famtastic-staging/secrets/db-provision.json
  FAMTASTIC_STAGING_BASIC_AUTH_USER=<new-stage-only-user>
  FAMTASTIC_STAGING_BASIC_AUTH_PASSWORD=<new-stage-only-password>
  FAMTASTIC_STAGING_ADDRESS=<verified server IP, optional during DNS propagation>

Before --apply, provision the staging subdomain at this docroot, create the
GoDaddy DNS record, and install TLS. The credential JSON must be the mode-0600
result of cPanel Mysql/setup_db_and_user with an fdstg prefix. This deployer
does not receive provider credentials and never copies production data.
USAGE
}

case "$MODE" in --preflight|--dry-run|--apply) ;; *) usage >&2; exit 2 ;; esac
[[ "$STAGING_HOST" == staging.famtasticdesigns.com ]] || fail "only the dedicated staging host is supported"
[[ "$STAGING_REF" =~ ^refs/heads/[A-Za-z0-9._/-]+$ ]] || fail "FAMTASTIC_STAGING_REF must be one pushed branch ref"
[[ -n "$REPOSITORY_URL" ]] || fail "staging repository URL is required"

for command in git awk tar npm node; do command -v "$command" >/dev/null || fail "missing local prerequisite: $command"; done
NPM_COMMAND=(npm)
NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]')"
if [[ "$NODE_MAJOR" != 22 ]]; then
  command -v fnm >/dev/null || fail "Node 22 is required; install it or make fnm available"
  fnm exec --using=22 node -e 'if (Number(process.versions.node.split(".")[0]) !== 22) process.exit(1)' || fail "fnm could not select Node 22"
  NPM_COMMAND=(fnm exec --using=22 npm)
fi
cd "$REPO_ROOT"
[[ -z "$(git status --porcelain)" ]] || fail "refusing a dirty worktree"
HEAD_SHA="$(git rev-parse HEAD)"
REMOTE_LINE="$(git ls-remote --exit-code "$REPOSITORY_URL" "$STAGING_REF")" || fail "could not resolve the required pushed staging ref"
REMOTE_SHA="$(awk 'NR == 1 { print $1 }' <<<"$REMOTE_LINE")"
[[ "$REMOTE_SHA" =~ ^[0-9a-f]{40}$ ]] || fail "remote ref did not resolve to one commit SHA"
[[ "$HEAD_SHA" == "$REMOTE_SHA" ]] || fail "local HEAD is not the exact pushed staging ref"

# Shell flags cannot replace application-level safety. Refuse a source release
# until both required implementations are present.
[[ -f backend/web/modules/custom/famtastic_pipeline/src/Service/DisabledPaymentGateway.php ]] || fail "DisabledPaymentGateway is required before protected staging"
[[ -f backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module ]] || fail "protected staging mail hook is required before protected staging"
[[ -f backend/web/modules/custom/famtastic_pipeline/src/EventSubscriber/ProtectedStagingRequestSubscriber.php ]] || fail "ProtectedStagingRequestSubscriber is required before protected staging"
[[ -f backend/web/modules/custom/famtastic_pipeline/src/Plugin/Mail/FamtasticBlackholeMail.php ]] || fail "FamtasticBlackholeMail is required before protected staging"

RELEASE_ID="$HEAD_SHA-$(date -u +%Y%m%dT%H%M%SZ)"
TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-staging.XXXXXX")"
trap 'rm -rf -- "$TMP_ROOT"' EXIT
git archive --format=tar "$HEAD_SHA" | tar -xf - -C "$TMP_ROOT"
test -f "$TMP_ROOT/backend/composer.lock" || fail "exact source archive is incomplete"

mkdir -p "$EVIDENCE_ROOT"
RECEIPT="$EVIDENCE_ROOT/$RELEASE_ID.release.json"
ROLLBACK="$EVIDENCE_ROOT/$RELEASE_ID.rollback.json"
printf '{"schema":"famtastic.protected-staging-release.v2","release_id":"%s","commit":"%s","remote_ref":"%s","remote_sha":"%s","host":"%s","payment":"disabled_gateway_and_route_guard_required","mail":"global_hook_blackhole_required","scheduler":"no_cron_install","status":"preflighted","source_archive":"exact_git_archive"}\n' "$RELEASE_ID" "$HEAD_SHA" "$STAGING_REF" "$REMOTE_SHA" "$STAGING_HOST" > "$RECEIPT"
printf '{"schema":"famtastic.protected-staging-rollback.v2","release_id":"%s","commit":"%s","rollback":"repoint current to prior release; restore only the staging DB backup if schema rollback is required","executed":false}\n' "$RELEASE_ID" "$HEAD_SHA" > "$ROLLBACK"
echo "Protected staging preflight passed: $HEAD_SHA"
echo "Exact pushed ref: $STAGING_REF"
echo "Release evidence: $RECEIPT"
echo "Rollback evidence: $ROLLBACK"
[[ "$MODE" != --apply ]] && exit 0

[[ "$APPLY_CONFIRM" == "DEPLOY_PROTECTED_STAGING:$HEAD_SHA" ]] || fail "explicit exact-SHA apply confirmation is required"
[[ -n "$SSH_TARGET" && -n "$CPANEL_HOME" && -n "$STAGING_ROOT" && -n "$DOCROOT" && -n "$DB_CREDENTIAL_FILE" ]] || fail "cPanel target, root, docroot, and isolated DB credential file are required"
[[ "$CPANEL_HOME" == /home/* && "$STAGING_ROOT" == "$CPANEL_HOME"/* ]] || fail "staging root must be beneath the cPanel home"
[[ "$DOCROOT" == "$STAGING_ROOT/current/public" && "$DOCROOT" != *'/public_html'* ]] || fail "docroot must be isolated current/public, never public_html"
[[ "$BASIC_AUTH_USER" =~ ^[A-Za-z0-9._-]{3,64}$ && ${#BASIC_AUTH_PASSWORD} -ge 20 ]] || fail "a new stage-only Basic Auth user and 20+ character password are required"
for command in ssh openssl; do command -v "$command" >/dev/null || fail "missing local prerequisite: $command"; done

# DNS and TLS are preconditions. DNS is intentionally provisioned through the
# authoritative GoDaddy API, never through cPanel's non-authoritative zone.
[[ "$STAGING_ADDRESS" =~ ^[A-Za-z0-9.:-]+$ ]] || fail "staging address is malformed"
TLS_SAN="$(printf '' | openssl s_client -connect "$STAGING_ADDRESS:443" -servername "$STAGING_HOST" 2>/dev/null | openssl x509 -noout -ext subjectAltName 2>/dev/null)" || fail "TLS certificate cannot be read for staging host"
grep -F "DNS:$STAGING_HOST" <<<"$TLS_SAN" >/dev/null || fail "TLS certificate does not cover the staging host"
VITE_DRUPAL_BASE_URL="https://${STAGING_HOST}/web" VITE_STRIPE_PUBLIC_KEY='' VITE_GA_MEASUREMENT_ID='' "${NPM_COMMAND[@]}" --prefix "$TMP_ROOT/frontend" ci --include=dev --no-audit --no-fund
VITE_DRUPAL_BASE_URL="https://${STAGING_HOST}/web" VITE_STRIPE_PUBLIC_KEY='' VITE_GA_MEASUREMENT_ID='' "${NPM_COMMAND[@]}" --prefix "$TMP_ROOT/frontend" run build
! grep -R -E 'pk_live_|https://famtasticdesigns\.com/web|G-T2ENFBZR4K' "$TMP_ROOT/frontend/dist" >/dev/null || fail "staging build contains a production provider/API identifier"
printf '%s\n' "$BASIC_AUTH_PASSWORD" | ssh -T "$SSH_TARGET" "set -e; umask 077; mkdir -p '$STAGING_ROOT/secrets'; read -r password; hash=\$(printf '%s' \"\$password\" | openssl passwd -apr1 -stdin); printf '%s:%s\\n' '$BASIC_AUTH_USER' \"\$hash\" > '$STAGING_ROOT/secrets/staging.htpasswd'; chmod 600 '$STAGING_ROOT/secrets/staging.htpasswd'"
ssh -T "$SSH_TARGET" "set -e; mkdir -p '$STAGING_ROOT/releases/$HEAD_SHA/source' '$STAGING_ROOT/releases/$HEAD_SHA/public'"
git archive --format=tar "$HEAD_SHA" backend | ssh -T "$SSH_TARGET" "tar -xf - -C '$STAGING_ROOT/releases/$HEAD_SHA/source'"
tar -cf - -C "$TMP_ROOT/frontend/dist" . | ssh -T "$SSH_TARGET" "tar -xf - -C '$STAGING_ROOT/releases/$HEAD_SHA/public'"
ssh -T "$SSH_TARGET" bash -s -- "$CPANEL_HOME" "$STAGING_ROOT" "$DOCROOT" "$DB_CREDENTIAL_FILE" "$REPOSITORY_URL" "$STAGING_REF" "$HEAD_SHA" "$STAGING_HOST" "$RELEASE_ID" <<'REMOTE'
set -euo pipefail
home="$1"; root="$2"; docroot="$3"; db_file="$4"; repository="$5"; ref="$6"; sha="$7"; host="$8"; release_id="$9"
fail() { echo "protected-staging remote: $*" >&2; exit 2; }
[[ "$home" == /home/* && "$root" == "$home"/* && "$docroot" == "$root/current/public" && "$docroot" != *'/public_html'* ]] || fail "unsafe remote paths"
[[ -f "$db_file" ]] || fail "isolated DB credential file missing"
[[ "$(stat -c '%a' "$db_file" 2>/dev/null || stat -f '%Lp' "$db_file")" == 600 ]] || fail "DB credential file must be mode 0600"
for command in composer php mysql mysqldump gzip openssl python3 uapi; do command -v "$command" >/dev/null || fail "missing cPanel prerequisite: $command"; done
if crontab -l 2>/dev/null | grep -F -- "$root" >/dev/null; then fail "a cPanel cron already targets staging; this deployer never edits cron"; fi
uapi --output=json DomainInfo domains_data format=list | python3 -c '
import json, sys
expected_host, expected_root = sys.argv[1:]
payload = json.load(sys.stdin)
domains = (payload.get("result") or {}).get("data") or []
match = next((item for item in domains if item.get("domain") == expected_host), None)
if not match:
    raise SystemExit("protected-staging remote: staging subdomain is not provisioned")
if match.get("documentroot") != expected_root:
    raise SystemExit("protected-staging remote: staging subdomain points to the wrong docroot")
' "$host" "$docroot"
mkdir -p "$root/releases" "$root/state/files" "$root/state/private" "$root/state/email-capture" "$root/backups" "$root/secrets"
chmod 700 "$root/state/private" "$root/state/email-capture" "$root/backups" "$root/secrets"
database_env="$root/secrets/database.env"
hash_salt_file="$root/secrets/hash-salt"
admin_password_file="$root/secrets/stage-admin-password"
if [[ ! -s "$hash_salt_file" ]]; then openssl rand -hex 32 > "$hash_salt_file"; fi
if [[ ! -s "$admin_password_file" ]]; then openssl rand -base64 24 > "$admin_password_file"; fi
python3 - "$db_file" "$database_env" "$hash_salt_file" <<'PY'
import json, pathlib, shlex, sys
source, target, salt_path = map(pathlib.Path, sys.argv[1:])
payload = json.loads(source.read_text())
data = payload.get("data") or (payload.get("result") or {}).get("data") or payload
required = {"database", "database_user", "database_user_password"}
if not required.issubset(data):
    raise SystemExit("protected-staging remote: cPanel database receipt is incomplete")
if "fdstg" not in str(data["database"]).lower():
    raise SystemExit("protected-staging remote: database does not use the isolated fdstg prefix")
values = {
    "STAGING_DB_NAME": data["database"],
    "STAGING_DB_USER": data["database_user"],
    "STAGING_DB_PASSWORD": data["database_user_password"],
    "STAGING_DB_HOST": data.get("hostname") or "localhost",
    "STAGING_DB_PORT": str(data.get("port") or "3306"),
    "STAGING_HASH_SALT": salt_path.read_text().strip(),
}
target.write_text("".join(f"{key}={shlex.quote(str(value))}\n" for key, value in values.items()))
target.chmod(0o600)
PY
chmod 600 "$hash_salt_file" "$admin_password_file" "$database_env"
# shellcheck disable=SC1090
source "$database_env"
[[ "${STAGING_DB_NAME:-}" == *fdstg* && -n "${STAGING_DB_USER:-}" && -n "${STAGING_DB_PASSWORD:-}" && -n "${STAGING_HASH_SALT:-}" ]] || fail "fresh staging DB credentials are invalid"
STAGING_ADMIN_PASSWORD="$(tr -d '\r\n' < "$admin_password_file")"
release="$root/releases/$sha"; source_dir="$release/source"
test -f "$source_dir/backend/composer.lock" || fail "exact Git-archived backend is absent"
test -f "$release/public/index.html" || fail "exact locally built frontend artifact is absent"
test -f "$source_dir/backend/web/modules/custom/famtastic_pipeline/src/Service/DisabledPaymentGateway.php" || fail "disabled payment gateway absent in release"
test -f "$source_dir/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module" || fail "protected staging mail hook absent in release"
test -f "$source_dir/backend/web/modules/custom/famtastic_pipeline/src/EventSubscriber/ProtectedStagingRequestSubscriber.php" || fail "payment route guard absent in release"
test -f "$source_dir/backend/web/modules/custom/famtastic_pipeline/src/Plugin/Mail/FamtasticBlackholeMail.php" || fail "global Drupal mail blackhole absent in release"

b64() { printf '%s' "$1" | base64 | tr -d '\n'; }
db_name_b64="$(b64 "$STAGING_DB_NAME")"; db_user_b64="$(b64 "$STAGING_DB_USER")"; db_password_b64="$(b64 "$STAGING_DB_PASSWORD")"; hash_salt_b64="$(b64 "$STAGING_HASH_SALT")"
cat > "$root/state/settings.local.php" <<PHP
<?php
\$databases['default']['default'] = ['database' => base64_decode('${db_name_b64}'), 'username' => base64_decode('${db_user_b64}'), 'password' => base64_decode('${db_password_b64}'), 'host' => '${STAGING_DB_HOST}', 'port' => '${STAGING_DB_PORT}', 'driver' => 'mysql', 'prefix' => '', 'collation' => 'utf8mb4_general_ci'];
\$settings['hash_salt'] = base64_decode('${hash_salt_b64}');
\$settings['file_public_path'] = 'sites/default/files'; \$settings['file_private_path'] = '${root}/state/private';
\$settings['trusted_host_patterns'] = ['^staging\\.famtasticdesigns\\.com$'];
\$settings['stripe_secret_key'] = ''; \$settings['famtastic_payment_mode'] = 'disabled'; \$settings['famtastic_protected_staging'] = TRUE; \$settings['famtastic_staging_mail_capture'] = '${root}/state/email-capture/messages.jsonl'; \$settings['famtastic_email_transport'] = 'disabled'; \$settings['famtastic_transactional_email_transport'] = 'memory'; \$settings['famtastic_transactional_email_capture'] = '${root}/state/email-capture/messages.jsonl'; \$settings['famtastic_deploy_transport'] = 'disabled'; \$settings['famtastic_allow_customer_deployments'] = FALSE; \$settings['famtastic_hosting_billing_provider'] = 'disabled'; \$settings['site_studio_staging_dispatch_secret'] = ''; \$settings['site_studio_callback_secret'] = '';
\$config['smtp.settings']['smtp_on'] = FALSE; \$config['system.mail']['interface']['default'] = 'famtastic_blackhole'; \$config['famtastic_pipeline.settings']['frontend_base_url'] = 'https://${host}'; \$config['famtastic_pipeline.settings']['public_api_base_url'] = 'https://${host}/web'; \$config['famtastic_pipeline.settings']['site_studio_staging_url'] = ''; \$config['famtastic_pipeline.settings']['pilot_exact_dispatch_only'] = TRUE;
putenv('STRIPE_SECRET_KEY='); putenv('STRIPE_WEBHOOK_SECRET='); putenv('SITE_STUDIO_URL='); putenv('SITE_STUDIO_STAGING_URL='); putenv('SITE_STUDIO_DISPATCH_SECRET='); putenv('SITE_STUDIO_CALLBACK_SECRET='); putenv('FAMTASTIC_STUDIO_DISPATCH_SECRET='); putenv('FAMTASTIC_EMAIL_TRANSPORT=disabled'); putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory'); putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE=${root}/state/email-capture/messages.jsonl'); putenv('FAMTASTIC_ALLOW_REAL_OUTREACH=false'); putenv('FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH=false'); putenv('FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH=false'); putenv('FAMTASTIC_ALLOW_PAYMENT_SIMULATION=false'); putenv('FAMTASTIC_DEPLOY_TRANSPORT=disabled'); putenv('FAMTASTIC_ALLOW_CUSTOMER_DEPLOYMENTS=false'); putenv('FAMTASTIC_DOMAIN_VERIFY_MODE=disabled'); putenv('FAMTASTIC_HOSTING_BILLING_PROVIDER=disabled'); putenv('FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1');
PHP
chmod 600 "$root/state/settings.local.php"
ln -sfn "$root/state/settings.local.php" "$source_dir/backend/web/sites/default/settings.local.php"
files_dir="$source_dir/backend/web/sites/default/files"
if [[ ! -L "$files_dir" ]]; then
  if [[ -d "$files_dir" ]]; then
    mv "$files_dir" "$release/files-template"
    cp -a "$release/files-template/." "$root/state/files/"
  fi
  ln -s "$root/state/files" "$files_dir"
fi
cd "$source_dir/backend"; composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
mkdir -p "$release/public/.well-known/acme-challenge"
ln -sfn ../source/backend/web "$release/public/web"
cat > "$release/public/.htaccess" <<HTACCESS
Header always set X-Robots-Tag "noindex, nofollow, noarchive"
Header always set Content-Security-Policy "default-src 'self'; connect-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'"
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=302,L]
AuthType Basic
AuthName "FAMtastic protected staging"
AuthUserFile ${root}/secrets/staging.htpasswd
SetEnvIf Request_URI "^/\.well-known/acme-challenge/" allow_acme=1
<RequireAny>
  Require env allow_acme
  Require valid-user
</RequireAny>
HTACCESS

table_count="$(MYSQL_PWD="$STAGING_DB_PASSWORD" mysql -h "$STAGING_DB_HOST" -P "$STAGING_DB_PORT" -u "$STAGING_DB_USER" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${STAGING_DB_NAME}'")"
[[ "$table_count" =~ ^[0-9]+$ ]] || fail "could not inspect the staging database"
drush="$source_dir/backend/vendor/bin/drush"
previous="$(readlink "$root/current" 2>/dev/null || true)"; backup="$root/backups/${release_id}.before.sql.gz"
MYSQL_PWD="$STAGING_DB_PASSWORD" mysqldump --no-tablespaces --single-transaction -h "$STAGING_DB_HOST" -P "$STAGING_DB_PORT" -u "$STAGING_DB_USER" "$STAGING_DB_NAME" | gzip > "$backup"
if [[ "$table_count" == 0 ]]; then
  "$drush" --root="$source_dir/backend/web" site:install standard --site-name='FAMtastic Protected Staging' --account-name=stage-admin --account-mail=stage-admin@example.test --account-pass="$STAGING_ADMIN_PASSWORD" -y
else
  MYSQL_PWD="$STAGING_DB_PASSWORD" mysql -h "$STAGING_DB_HOST" -P "$STAGING_DB_PORT" -u "$STAGING_DB_USER" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${STAGING_DB_NAME}' AND table_name='key_value'" | grep -qx '1' || fail "non-empty staging database is not a Drupal installation"
  "$drush" --root="$source_dir/backend/web" updatedb -y
fi
"$drush" --root="$source_dir/backend/web" en -y jsonapi serialization rest jsonapi_extras consumers admin_toolbar famtastic_pipeline
"$drush" --root="$source_dir/backend/web" theme:enable -y famtastic_admin famtastic_customer
"$drush" --root="$source_dir/backend/web" config:set system.theme default famtastic_customer -y
"$drush" --root="$source_dir/backend/web" config:set system.theme admin famtastic_admin -y
"$drush" --root="$source_dir/backend/web" cr
"$drush" --root="$source_dir/backend/web" php:eval "if (\Drupal::service('famtastic_pipeline.gateway_manager')->active()->getMode() !== 'disabled') { throw new \RuntimeException('payment gate not disabled'); } if (\Drupal\Core\Site\Settings::get('famtastic_payment_mode') !== 'disabled') { throw new \RuntimeException('payment route guard not disabled'); } if (\Drupal\Core\Site\Settings::get('famtastic_protected_staging') !== TRUE || \Drupal\Core\Site\Settings::get('famtastic_staging_mail_capture') === '') { throw new \RuntimeException('global mail blackhole is not enabled'); } if (\Drupal::config('system.mail')->get('interface.default') !== 'famtastic_blackhole') { throw new \RuntimeException('Drupal mail interface is not the protected-staging blackhole'); }"
ln -sfn "$release" "$root/.next-current"; mv -Tf "$root/.next-current" "$root/current"
printf '%s\n' "$sha" > "$release/.protected-staging-release"
printf '{"release_id":"%s","commit":"%s","previous":"%s","stage_db_backup":"%s","docroot":"%s","status":"deployed"}\n' "$release_id" "$sha" "$previous" "$backup" "$docroot" > "$release/receipt.json"
printf '{"release_id":"%s","commit":"%s","previous":"%s","rollback":"repoint current and restore only this stage DB backup if required","executed":false}\n' "$release_id" "$sha" "$previous" > "$release/rollback.json"
echo "Protected staging release prepared at $docroot for $sha"
REMOTE
echo "Protected staging apply completed for $HEAD_SHA. Verify unauthenticated 401 and authenticated HTTPS behavior before use."
