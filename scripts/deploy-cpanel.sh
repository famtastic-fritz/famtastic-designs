#!/bin/bash
# Deploy FAMtastic Designs Drupal site to GoDaddy cPanel.
#
# Usage: ./scripts/deploy-cpanel.sh

set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE_HOST="p3plzcpnl506112.prod.phx3.secureserver.net"
REMOTE_USER="nineoo"
REMOTE_ROOT="/home/nineoo/public_html/famtasticdesigns.com"
REMOTE_WEB="${REMOTE_ROOT}/web"
REMOTE_FILES="${REMOTE_WEB}/sites/default/files"
DB_NAME="famtastic_designs"
DB_USER="famtastic_fritz"
DB_HOST="localhost"
ADMIN_USER="admin"
ADMIN_PASS="TestPass123!"
SSH_KEY="${HOME}/.ssh/id_ed25519"
SSH_OPTS="-i ${SSH_KEY} -o BatchMode=yes"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

run_ssh() {
  ssh ${SSH_OPTS} "${REMOTE_USER}@${REMOTE_HOST}" "$@"
}

echo "=== Building local vendor (no-dev) ==="
cd "${REPO_DIR}"
if [[ ! -d vendor ]]; then
  composer install --no-dev --optimize-autoloader
else
  composer install --no-dev --optimize-autoloader --quiet
fi

echo "=== Generating / updating database password ==="
DB_PASS="$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)"
run_ssh "uapi --output=json Mysql set_password user='${DB_USER}' password='${DB_PASS}'" >/dev/null

echo "=== Preparing remote document root ==="
run_ssh "rm -rf '${REMOTE_ROOT}' && mkdir -p '${REMOTE_ROOT}'"

echo "=== Syncing codebase to server ==="
rsync -avz --delete \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='node_modules' \
  --exclude='**/.ht.sqlite' \
  --exclude='web/default/files' \
  --exclude='web/sites/default/files' \
  --exclude='web/sites/default/settings.local.php' \
  --exclude='**/tests' \
  --exclude='**/Tests' \
  --exclude='**/doc' \
  --exclude='**/docs' \
  --exclude='**/.github' \
  --exclude='**/phpunit.xml*' \
  --exclude='**/LICENSE*' \
  --exclude='**/README*' \
  --exclude='**/CHANGELOG*' \
  --exclude='**/CONTRIBUTING*' \
  --exclude='**/*.md' \
  -e "ssh ${SSH_OPTS}" \
  "${REPO_DIR}/" \
  "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_ROOT}/"

echo "=== Creating production settings.local.php ==="
run_ssh "cat > '${REMOTE_ROOT}/web/sites/default/settings.local.php' <<'EOF'
<?php
\$databases['default']['default'] = [
  'database' => '${DB_NAME}',
  'username' => '${DB_USER}',
  'password' => '${DB_PASS}',
  'host' => '${DB_HOST}',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];
\$settings['trusted_host_patterns'] = [
  '^famtasticdesigns\\.com$',
  '^.+\\.famtasticdesigns\\.com$',
];
EOF
chmod 640 '${REMOTE_ROOT}/web/sites/default/settings.local.php'"

echo "=== Creating writable files directory ==="
run_ssh "mkdir -p '${REMOTE_FILES}' && chmod 755 '${REMOTE_FILES}' && chmod 755 '${REMOTE_WEB}/sites/default'"

echo "=== Installing Drupal with existing config ==="
run_ssh "export PATH='${REMOTE_ROOT}/vendor/bin:/usr/local/bin:\$PATH' && cd '${REMOTE_ROOT}' && ./vendor/bin/drush site:install minimal --existing-config --account-name='${ADMIN_USER}' --account-pass='${ADMIN_PASS}' --yes"

echo "=== Seeding sample content ==="
run_ssh "export PATH='${REMOTE_ROOT}/vendor/bin:/usr/local/bin:\$PATH' && cd '${REMOTE_ROOT}' && ./vendor/bin/drush php:script scripts/seed-proof-artifacts.php && ./vendor/bin/drush php:script scripts/seed-designs-content.php"

echo "=== Setting front page ==="
run_ssh "export PATH='${REMOTE_ROOT}/vendor/bin:/usr/local/bin:\$PATH' && cd '${REMOTE_ROOT}' && ./vendor/bin/drush php:script scripts/set-front-page.php"

echo "=== Clearing caches ==="
run_ssh "export PATH='${REMOTE_ROOT}/vendor/bin:/usr/local/bin:\$PATH' && cd '${REMOTE_ROOT}' && ./vendor/bin/drush cr"

echo "=== Deploy complete ==="
echo "Site: https://famtasticdesigns.com"
echo "Admin: https://famtasticdesigns.com/user/login"
echo "Admin user: ${ADMIN_USER}"
echo "Admin pass: ${ADMIN_PASS}"
