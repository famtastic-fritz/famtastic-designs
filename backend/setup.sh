#!/usr/bin/env bash
#
# FAMtastic Designs — backend post-install bootstrap.
#
# Run from anywhere: ./setup.sh
# Installs Drupal 11 (headless), enables the JSON:API stack and the
# famtastic_admin admin theme. Prerequisite: `composer install` must have
# been run once in this directory (this scaffold does not run it for you).
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

DRUSH="vendor/bin/drush"

echo "==> [1/7] Checking composer dependencies"
if [ ! -f "vendor/autoload.php" ]; then
  echo "    vendor/ not found. Install dependencies first:"
  echo "      composer install --no-interaction"
  echo "    Then re-run ./setup.sh"
  exit 1
fi

echo "==> [2/7] Installing Drupal 11 (standard profile)"
DB_URL="${DB_URL:-sqlite://sites/default/files/.ht.sqlite}"
# Git can't track empty dirs; ensure the SQLite/public-files dir and the
# out-of-docroot private dir exist before install (a fresh checkout lacks them).
mkdir -p web/sites/default/files private
if ${DRUSH} status --field=bootstrap 2>/dev/null | grep -q "Successful"; then
  echo "    Drupal already installed — skipping site:install."
else
  ${DRUSH} site:install standard \
    --db-url="${DB_URL}" \
    --account-name=admin \
    --account-pass=admin \
    --account-mail=admin@famtastic.local \
    --site-name="FAMtastic Designs" \
    --site-mail=no-reply@famtastic.local \
    -y
fi

echo "==> [3/7] Enabling JSON:API / web-services / admin modules"
${DRUSH} en -y jsonapi serialization rest jsonapi_extras consumers admin_toolbar

# Drupal 11.1+ ships the "navigation" module (left sidebar) with hardcoded
# light styling that bypasses theme tokens. Uninstall it so the classic
# toolbar — which famtastic_admin styles fully dark — is used instead.
${DRUSH} pmu -y navigation || true

echo "==> [4/7] Ensuring article/page content types"
# Drupal 11's minimal 'standard' profile ships NO content types — they live in
# core recipes now. Import the recipe config (idempotent) so JSON:API exposes
# node/article and node/page out of the box.
if ! ${DRUSH} eval "exit((int) !\Drupal\node\Entity\NodeType::load('article'));" 2>/dev/null; then
  BACKEND_DIR="$(pwd)"
  ${DRUSH} config:import --partial --source="${BACKEND_DIR}/web/core/recipes/article_content_type/config" -y
  ${DRUSH} config:import --partial --source="${BACKEND_DIR}/web/core/recipes/page_content_type/config" -y
else
  echo "    Content types already present — skipping recipe config import."
fi

echo "==> [5/7] Enabling the FAMtastic Admin theme"
${DRUSH} theme:enable famtastic_admin

echo "==> [6/7] Setting famtastic_admin as the default admin theme"
${DRUSH} config:set system.theme admin famtastic_admin -y

echo "==> [7/7] Rebuilding caches"
${DRUSH} cr

echo ""
echo "Backend ready:"
echo "  - Site:      http://localhost:8080"
echo "  - JSON:API:  http://localhost:8080/jsonapi"
echo "  - Admin:     http://localhost:8080/user (admin / admin — local dev only)"
