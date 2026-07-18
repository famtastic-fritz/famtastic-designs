#!/usr/bin/env bash
#
# FAMtastic Designs v2 — Phase 2 backend bootstrap.
#
# Run from anywhere: ./phase2-setup.sh
# Adds OAuth2 authentication (simple_oauth), the 'famtastic_spa' consumer,
# role-based content permissions, and imports the Phase 2 content types from
# config/phase2/ (Client Project, Service Package, Testimonial).
#
# Prerequisites (this scaffold does NOT run them for you):
#   1. ./setup.sh must have completed (Phase 1 backend live).
#   2. composer require drupal/simple_oauth:^6.0   (declared in composer.json)
#
# The script is idempotent: every step checks before it acts and is safe to
# re-run.
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

DRUSH="vendor/bin/drush"
BACKEND_DIR="$(pwd)"
KEY_DIR="$(cd .. && pwd)/private"
PHASE2_CONFIG="${BACKEND_DIR}/config/phase2"

echo "==> [1/9] Checking composer dependencies and simple_oauth module"
if [ ! -f "vendor/autoload.php" ]; then
  echo "    vendor/ not found. Install dependencies first:"
  echo "      composer install --no-interaction"
  echo "    Then re-run ./phase2-setup.sh"
  exit 1
fi
if [ ! -d "web/modules/contrib/simple_oauth" ]; then
  echo "    ERROR: drupal/simple_oauth is not present in vendor/modules."
  echo "    It is declared in composer.json but not installed yet."
  echo "    Run this first, then re-run ./phase2-setup.sh:"
  echo "      composer require drupal/simple_oauth:^6.0 --no-interaction"
  exit 1
fi

echo "==> [2/9] Verifying the Phase 1 Drupal install"
if ! ${DRUSH} status --field=bootstrap 2>/dev/null | grep -q "Successful"; then
  echo "    Drupal is not installed/bootstrapped yet."
  echo "    Run ./setup.sh first (Phase 1), then re-run ./phase2-setup.sh"
  exit 1
fi
echo "    Drupal bootstrap OK."

echo "==> [3/9] Enabling OAuth2 / consumer modules"
${DRUSH} en -y simple_oauth consumers

echo "==> [4/9] Generating OAuth2 RSA keys in ${KEY_DIR}"
# Keys MUST live outside the docroot (backend/web). v2/private/ is shared
# with the Docker/Platform.sh deployment configs as a read-only mount.
mkdir -p "${KEY_DIR}"
if [ -f "${KEY_DIR}/private.key" ] && [ -f "${KEY_DIR}/public.key" ]; then
  echo "    Keys already exist — skipping generation."
else
  openssl genrsa -out "${KEY_DIR}/private.key" 2048
  openssl rsa -in "${KEY_DIR}/private.key" -pubout -out "${KEY_DIR}/public.key"
  echo "    Generated new 2048-bit RSA keypair."
fi
chmod 600 "${KEY_DIR}/private.key"
chmod 644 "${KEY_DIR}/public.key"

echo "==> [5/9] Pointing simple_oauth at the key files"
${DRUSH} config:set simple_oauth.settings public_key "${KEY_DIR}/public.key" -y
${DRUSH} config:set simple_oauth.settings private_key "${KEY_DIR}/private.key" -y

echo "==> [6/9] Ensuring the 'famtastic_spa' OAuth2 consumer"
# Public client (no secret) for the React SPA — password + refresh_token
# grants. Falls back to a minimal create if the simple_oauth field set
# differs between minor versions.
${DRUSH} eval '
$storage = \Drupal::entityTypeManager()->getStorage("consumer");
if ($storage->load("famtastic_spa")) {
  echo "Consumer famtastic_spa already exists — skipping.\n";
}
else {
  try {
    $consumer = $storage->create([
      "id" => "famtastic_spa",
      "label" => "FAMtastic SPA",
      "description" => "Public OAuth2 client for the React 18 SPA (password + refresh_token grants).",
      "confidential" => FALSE,
      "roles" => ["authenticated"],
    ]);
  }
  catch (\Throwable $e) {
    $consumer = $storage->create([
      "id" => "famtastic_spa",
      "label" => "FAMtastic SPA",
    ]);
  }
  $consumer->save();
  echo "Created consumer famtastic_spa.\n";
}
'

echo "==> [7/9] Applying role-based content permissions"
# anonymous: view published content (Drupal core default — service packages
# and testimonials become readable over JSON:API without a token).
${DRUSH} role:perm:add anonymous 'access content'
# authenticated: access content — clients read their own Client Projects via
# a JSON:API filter on field_client_user (see PHASE2_PLAN.md §2.3).
${DRUSH} role:perm:add authenticated 'access content'
# NOTE: uid 1 (admin Fritz) bypasses the permission system by core design —
# full CRUD with nothing to grant. Admin works in the Drupal UI, not the SPA.
echo "    uid 1 (admin) has all permissions implicitly — nothing to grant."

echo "==> [8/9] Importing Phase 2 content types from config/phase2"
# Same proven pattern setup.sh uses for core recipe config: partial import
# from an absolute path. Idempotent — skipped once the types exist, and
# skipped with a warning if the sibling config scaffold is not in place yet.
if [ ! -d "${PHASE2_CONFIG}" ] || ! ls "${PHASE2_CONFIG}"/*.yml >/dev/null 2>&1; then
  echo "    ${PHASE2_CONFIG} has no YAML yet — skipping import."
  echo "    (Re-run this script after the config/phase2 scaffold lands.)"
elif ! ${DRUSH} eval "exit((int) !\Drupal\node\Entity\NodeType::load('client_project'));" 2>/dev/null; then
  ${DRUSH} config:import --partial --source="${PHASE2_CONFIG}" -y
else
  echo "    Content types already present — skipping config/phase2 import."
fi

echo "==> [9/9] Rebuilding caches"
${DRUSH} cr

echo ""
echo "Phase 2 backend ready:"
echo "  - OAuth token:   POST http://localhost:8080/oauth/token"
echo "                   grant_type=password&client_id=famtastic_spa&username=<email>&password=<pwd>"
echo "  - JSON:API:      http://localhost:8080/jsonapi (Bearer <access_token> for auth'd reads)"
echo "  - OAuth keys:    ${KEY_DIR} (private.key 600 / public.key 644)"
echo "  - Consumer:      famtastic_spa (public client, role: authenticated)"
echo ""
echo "Next manual steps:"
echo "  1. If step 8 skipped: scaffold config/phase2/*.yml, then re-run ./phase2-setup.sh"
echo "  2. Set access-token TTL: drush config:set simple_oauth.settings access_token_expiration 300 -y"
echo "  3. Wire the SPA: frontend/src/context/UserContext.jsx, components/ProtectedRoute.jsx,"
echo "     pages/LoginPage.jsx, pages/AdminDashboardPage.jsx (see PHASE2_PLAN.md §2.5)"
echo "  4. Validate deployment configs: docker compose up --build (v2 root)"
