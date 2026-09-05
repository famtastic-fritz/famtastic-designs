#!/usr/bin/env bash
set -euo pipefail

# This acceptance fixture intentionally exercises the deterministic local
# placeholder path. Customer outreach remains blocked unless a test opts in.
export FAMTASTIC_ALLOW_STUB_OUTREACH=1

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
PORT="${PORT:-8920}"
PACKAGE="${PACKAGE:-essential_199}"
EXPECTED_AMOUNT="${EXPECTED_AMOUNT:-19900}"
EXPECTED_REVISIONS="${EXPECTED_REVISIONS:-1}"
SECRET="${STRIPE_WEBHOOK_SECRET:-whsec_local_dev_secret}"
export FAMTASTIC_HOSTING_MONTHLY_AMOUNT="${FAMTASTIC_HOSTING_MONTHLY_AMOUNT:-2900}"
export FAMTASTIC_HOSTING_BILLING_PROVIDER="${FAMTASTIC_HOSTING_BILLING_PROVIDER:-memory}"
BASE="http://127.0.0.1:$PORT"
run_id="$(date +%s)-$$"
campaign="journey-$PACKAGE-$run_id"
email="journey-$PACKAGE-$run_id@example.test"
package_slug="${PACKAGE//_/-}"
domain="journey-$package_slug-$run_id.example"
sandbox="$(mktemp -d "${TMPDIR:-/tmp}/famtastic-journey.XXXXXX")"
csv="$sandbox/lead.csv"
import_json="$sandbox/import.json"
headers="$sandbox/click.headers"
server_log="$sandbox/server.log"
mail_capture="$sandbox/transactional-email.jsonl"
cookie_jar="$sandbox/customer.cookies"
customer_password="Synthetic-${run_id}-Pass!"
evidence_root="${EVIDENCE_DIR:-$REPO_ROOT/.artifacts/proof-runs}"
evidence_dir="$evidence_root/$campaign"
server_pid=""

cleanup() {
  if test -n "$server_pid"; then
    kill "$server_pid" 2>/dev/null || true
    wait "$server_pid" 2>/dev/null || true
  fi
  case "$sandbox" in
    "${TMPDIR:-/tmp}"/famtastic-journey.*) rm -rf "$sandbox" ;;
    *) echo "Refusing to remove unexpected sandbox: $sandbox" >&2 ;;
  esac
}
trap cleanup EXIT

http_code() {
  curl -s -o /dev/null -w '%{http_code}' "$@"
}
assert_json() {
  local json="$1"
  shift
  jq -e "$@" <<<"$json" >/dev/null
}

mkdir -p "$sandbox/releases" "$sandbox/sites"
mkdir -p "$evidence_dir"
printf 'source_record_id,business_name,email,website_url,website_quality\n' > "$csv"
if test "$PACKAGE" = "business_499"; then
  printf 'one,Autonomous Journey %s,%s,https://outdated-%s.example,outdated\n' "$run_id" "$email" "$run_id" >> "$csv"
else
  printf 'one,Autonomous Journey %s,%s,,\n' "$run_id" "$email" >> "$csv"
fi

"$DRUSH" famtastic:leads-import "$csv" --source=licensed-e2e --campaign="$campaign" > "$import_json"
prospect_id="$(jq -r '.rows[0].prospect_id' "$import_json")"
test "$prospect_id" != "null"
jq -e --arg package "$PACKAGE" '.rows[0].status == "qualified" and .rows[0].target_offer == $package' "$import_json" >/dev/null

"$DRUSH" famtastic:jobs-run --type=proof.generate --limit=100 >/dev/null
"$DRUSH" famtastic:jobs-run --type=outreach.prepare --limit=100 >/dev/null
"$DRUSH" famtastic:campaign-approve "$campaign" --confirm="$campaign" >/dev/null
FAMTASTIC_EMAIL_TRANSPORT=memory "$DRUSH" famtastic:jobs-run --type=outreach.send --limit=100 >/dev/null

tracking_key="$("$DRUSH" sqlq "SELECT tracking_key FROM famtastic_email_message WHERE prospect_id = $prospect_id ORDER BY id DESC LIMIT 1;" | tr -d '[:space:]')"
test -n "$tracking_key"

caller_dir="$PWD"
cd "$REPO_ROOT/backend"
FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory \
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" \
  "$DRUSH" runserver "127.0.0.1:$PORT" > "$server_log" 2>&1 &
server_pid=$!
cd "$caller_dir"
for _ in $(seq 1 40); do
  test "$(http_code "$BASE/robots.txt")" != "000" && break
  sleep 0.25
done
test "$(http_code "$BASE/robots.txt")" != "000"

curl -s -D "$headers" -o /dev/null "$BASE/api/pipeline/email/click/$tracking_key"
location="$(awk -F': ' 'tolower($1) == "location" {gsub("\r", "", $2); print $2}' "$headers" | tail -1)"
token="$(sed -E 's#^.*/p/([^/?]+).*$#\1#' <<<"$location")"
test -n "$token"
TH=(-H "X-Prospect-Token: $token")
JH=(-H "Content-Type: application/json")

confirm="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
  -d "{\"authorized\":true,\"contact_name\":\"Journey Owner\",\"contact_method\":\"email\",\"contact_value\":\"$email\"}" \
  "$BASE/api/pipeline/confirm")"
assert_json "$confirm" '.ok == true and .status == "lead"'

proof="$(curl -s "${TH[@]}" "$BASE/api/pipeline/proof-campaign")"
assert_json "$proof" '.variants | length == 3'
campaign_id="$(jq -r '.campaign.campaign_id' <<<"$proof")"
selection="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
  -d "{\"variant_id\":\"a\",\"package\":\"$PACKAGE\"}" \
  "$BASE/api/pipeline/proof-campaign/select")"
assert_json "$selection" --arg package "$PACKAGE" '.campaign.selected_variant == "a" and .campaign.selected_package == $package'

session="$(curl -s "${TH[@]}" "$BASE/api/pipeline/session")"
terms_checksum="$(jq -r '.terms.checksum' <<<"$session")"
checkout="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
  -d "{\"terms_accepted\":true,\"terms_checksum\":\"$terms_checksum\"}" \
  "$BASE/api/pipeline/checkout")"
checkout_session="$(jq -r '.session_id' <<<"$checkout")"
test -n "$checkout_session"
ts="$(date +%s)"
payload="$(printf '{"id":"evt_journey_%s","type":"checkout.session.completed","data":{"object":{"id":"%s","payment_intent":"pi_journey","payment_status":"paid","amount_total":%s,"currency":"usd","metadata":{"campaign_id":"%s"}}}}' "$run_id" "$checkout_session" "$EXPECTED_AMOUNT" "$campaign_id")"
signature="$(printf '%s.%s' "$ts" "$payload" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')"
webhook="$(curl -s -X POST -H "Stripe-Signature: t=$ts,v1=$signature" "${JH[@]}" -d "$payload" "$BASE/api/pipeline/stripe/webhook")"
assert_json "$webhook" '.paid == true and .newly_processed == true and .campaign_converted == true'

intake="$(curl -s -X POST "${TH[@]}" "${JH[@]}" -d '{
  "ideal_customer":"Local customers",
  "customer_problem":"Needs a stronger web presence",
  "desired_outcome":"More qualified inquiries",
  "primary_goal":"Generate leads",
  "primary_cta":"Request a quote",
  "services":"Primary service",
  "about":"Customer-confirmed business profile",
  "differentiators":"Responsive local service",
  "required_sections":"Home\nServices\nContact",
  "asset_ownership_confirmed":true
}' "$BASE/api/pipeline/intake")"
assert_json "$intake" '.ok == true'
"$DRUSH" famtastic:studio-generate "$prospect_id" >/dev/null
project_id="$("$DRUSH" sqlq "SELECT id FROM famtastic_project WHERE prospect_ref = $prospect_id ORDER BY id DESC LIMIT 1;" | tr -d '[:space:]')"
"$DRUSH" eval "
  \$project = \\Drupal::entityTypeManager()->getStorage('famtastic_project')->load($project_id);
  \$project->set('proof_url', 'https://proof.example/$campaign_id')
    ->set('delivery_status', 'proof_delivered')
    ->save();
"

for revision in $(seq 1 "$EXPECTED_REVISIONS"); do
  response="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
    -d "{\"action\":\"request_revision\",\"note\":\"Included revision $revision\"}" \
    "$BASE/api/pipeline/approval")"
  assert_json "$response" --argjson revision "$revision" '.revision_count == $revision'
done
test "$(http_code -X POST "${TH[@]}" "${JH[@]}" -d '{"action":"request_revision","note":"Extra"}' "$BASE/api/pipeline/approval")" = "402"
addon="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
  -d "{\"terms_accepted\":true,\"terms_checksum\":\"$terms_checksum\"}" \
  "$BASE/api/pipeline/revision-checkout")"
addon_session="$(jq -r '.session_id' <<<"$addon")"
assert_json "$addon" '.amount == 7500'
pending_code="$(http_code -X POST "${TH[@]}" "${JH[@]}" \
  -d "{\"terms_accepted\":true,\"terms_checksum\":\"$terms_checksum\"}" \
  "$BASE/api/pipeline/revision-checkout")"
test "$pending_code" = "409"
addon_ts=$((ts + 1))
addon_payload="$(printf '{"id":"evt_journey_addon_%s","type":"checkout.session.completed","data":{"object":{"id":"%s","payment_intent":"pi_journey_addon","payment_status":"paid","amount_total":7500,"currency":"usd"}}}' "$run_id" "$addon_session")"
addon_signature="$(printf '%s.%s' "$addon_ts" "$addon_payload" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')"
addon_webhook="$(curl -s -X POST -H "Stripe-Signature: t=$addon_ts,v1=$addon_signature" "${JH[@]}" -d "$addon_payload" "$BASE/api/pipeline/stripe/webhook")"
assert_json "$addon_webhook" '.paid == true'
retry_ts=$((addon_ts + 1))
retry_payload="$(printf '{"id":"evt_journey_addon_retry_%s","type":"checkout.session.completed","data":{"object":{"id":"%s","payment_intent":"pi_journey_addon","payment_status":"paid","amount_total":7500,"currency":"usd"}}}' "$run_id" "$addon_session")"
retry_signature="$(printf '%s.%s' "$retry_ts" "$retry_payload" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.*= //')"
retry_webhook="$(curl -s -X POST -H "Stripe-Signature: t=$retry_ts,v1=$retry_signature" "${JH[@]}" -d "$retry_payload" "$BASE/api/pipeline/stripe/webhook")"
assert_json "$retry_webhook" '.paid == true'
purchased_revision="$(curl -s -X POST "${TH[@]}" "${JH[@]}" -d '{"action":"request_revision","note":"Purchased revision"}' "$BASE/api/pipeline/approval")"
assert_json "$purchased_revision" --argjson count "$((EXPECTED_REVISIONS + 1))" \
  '.revision_count == $count and .revision_limit == $count'
approval="$(curl -s -X POST "${TH[@]}" "${JH[@]}" -d '{"action":"approve"}' "$BASE/api/pipeline/approval")"
assert_json "$approval" '.approval_status == "approved"'

FAMTASTIC_CUSTOMER_RELEASE_ROOT="$sandbox/releases" \
FAMTASTIC_CUSTOMER_DEPLOY_ROOT="$sandbox/sites" \
FAMTASTIC_CUSTOMER_PUBLIC_BASE="https://customer-host.example" \
FAMTASTIC_DEPLOY_TRANSPORT=local \
  "$DRUSH" eval "
    \$service = \\Drupal::service('famtastic_pipeline.customer_deployment');
    \$deployment = \$service->prepare($project_id);
    \$service->apply((int) \$deployment['id']);
  "
deployment_id="$("$DRUSH" sqlq "SELECT id FROM famtastic_deployment WHERE project_id = $project_id ORDER BY id DESC LIMIT 1;" | tr -d '[:space:]')"

"$DRUSH" eval "
  \\Drupal::service('famtastic_pipeline.domain_lifecycle')->register(
    $project_id,
    '$domain',
    'Autonomous Journey Customer',
    'customer-selected',
    'delegated',
    ['method' => 'signed-journey-consent', 'authorized_at' => gmdate(DATE_ATOM)]
  );
"
fixture="$(jq -nc --arg domain "$domain" '{($domain): {expected_target:"customer-host.example", observed_targets:["customer-host.example"], ssl_valid:true, certificate_expires_at:4102444800}}')"
FAMTASTIC_DOMAIN_VERIFY_MODE=fixture \
FAMTASTIC_DOMAIN_VERIFY_FIXTURE="$fixture" \
  "$DRUSH" eval "
    \$db = \\Drupal::database();
    \$domainId = \$db->select('famtastic_domain', 'd')->fields('d', ['id'])->condition('project_id', $project_id)->execute()->fetchField();
    \\Drupal::service('famtastic_pipeline.domain_lifecycle')->verifyDeployment($deployment_id, (int) \$domainId);
    \$ledger = \\Drupal::service('famtastic_pipeline.operational_ledger');
    foreach (['domain.verify:deployment:$deployment_id', 'domain.verify:domain:' . \$domainId] as \$jobKey) {
      \$jobId = \$db->select('famtastic_job', 'j')->fields('j', ['id'])->condition('job_key', \$jobKey)->execute()->fetchField();
      if (\$jobId) {
        \$ledger->completeJob((int) \$jobId, ['status' => 'verified_directly']);
      }
    }
    \\Drupal::service('famtastic_pipeline.hosting_lifecycle')->activate($project_id);
    \$hostingJobId = \$db->select('famtastic_job', 'j')->fields('j', ['id'])->condition('job_key', 'hosting.activate:project:$project_id')->execute()->fetchField();
    if (\$hostingJobId) {
      \$ledger->completeJob((int) \$hostingJobId, ['status' => 'activated_directly']);
    }
  "

hosting_json="$sandbox/hosting.json"
renewal_authorization="$(curl -s -X POST "${TH[@]}" "${JH[@]}" \
  -d "{\"recurring_authorized\":true,\"amount_minor\":$FAMTASTIC_HOSTING_MONTHLY_AMOUNT}" \
  "$BASE/api/pipeline/hosting-renewal")"
assert_json "$renewal_authorization" --argjson amount "$FAMTASTIC_HOSTING_MONTHLY_AMOUNT" \
  '.ok == true and .status == "scheduled" and .amount_minor == $amount'
"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$entitlement = \$db->select('famtastic_hosting_entitlement', 'h')->fields('h')->condition('project_id', $project_id)->execute()->fetchAssoc();
  \$subscription = \$db->select('famtastic_subscription', 's')->fields('s')->condition('entitlement_id', \$entitlement['id'])->execute()->fetchAssoc();
  print json_encode(['entitlement' => \$entitlement, 'subscription' => \$subscription]);
" > "$hosting_json"
subscription_id="$(jq -r '.subscription.id' "$hosting_json")"
renews_at="$(jq -r '.entitlement.renews_at' "$hosting_json")"
"$DRUSH" eval "
  \$renewed = \\Drupal::service('famtastic_pipeline.hosting_lifecycle')->processRenewal($subscription_id, TRUE, $renews_at);
  assert(\$renewed['status'] === 'active');
"

registration="$(curl -s -X POST "${JH[@]}" -d "{
  \"email\":\"$email\",
  \"password\":\"$customer_password\",
  \"name\":\"Synthetic Journey Customer\",
  \"business_name\":\"Autonomous Journey $run_id\",
  \"source\":\"synthetic-proof-agent\",
  \"marketing_opt_out\":true
}" "$BASE/api/customer/register")"
assert_json "$registration" '.ok == true and .verification_required == true'
verification_url="$(jq -rsr --arg email "$email" '[.[] | select(.to == $email and (.subject | test("Verify")))] | last.body' "$mail_capture" | sed -nE 's#.*(https?://[^ ]+/verify-email\?token=[^ ]+).*#\1#p')"
verification_token="${verification_url##*token=}"
test -n "$verification_token"
verified="$(curl -s -X POST "${JH[@]}" -d "{\"token\":\"$verification_token\"}" "$BASE/api/customer/verify")"
assert_json "$verified" '.ok == true'
login="$(curl -s -c "$cookie_jar" -X POST "${JH[@]}" -d "{\"email\":\"$email\",\"password\":\"$customer_password\"}" "$BASE/api/customer/login")"
assert_json "$login" '.customer.verified == true and (.organizations | length) == 1'
organization_id="$(jq -r '.organizations[0].public_id' <<<"$login")"
customer_workspace="$(curl -s -b "$cookie_jar" "$BASE/api/customer/workspace")"
assert_json "$customer_workspace" '
  (.orders | length) == 2 and
  (.projects | length) == 1 and
  ([.entitlements[] | select(.entitlement_type == "website_service" and .status == "active")] | length) == 1 and
  ([.entitlements[] | select(.entitlement_type == "hosting" and .status == "active")] | length) == 1
'
csrf="$(curl -s -b "$cookie_jar" "$BASE/session/token")"
catalog="$(curl -s -b "$cookie_jar" "$BASE/api/customer/catalog")"
assert_json "$catalog" '.terms.version == "customer_terms_v4_approved" and ([.products[].sku] | index("FAM-FOOT-199")) != null'
website_draft="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"organization\":\"$organization_id\",
  \"project_name\":\"Bakery website $run_id\",
  \"business_name\":\"Synthetic Sweet Bakery\",
  \"project_type\":\"landing_page\",
  \"domain_choice\":\"existing_domain\",
  \"existing_domain\":\"bakery-$run_id.example\",
  \"primary_goal\":\"Accept cake orders and explain pickup\",
  \"products_services\":\"Cakes, pastries, and custom orders\",
  \"required_features\":\"Lead form and photo gallery\",
  \"recommendation_requested\":false,
  \"action\":\"save\"
}" "$BASE/api/customer/website-requests")"
assert_json "$website_draft" '.ok == true and .website_request.status == "draft" and .website_request.project_type == "landing_page" and .website_request.recommended_sku == "FAM-FOOT-199"'
website_request_id="$(jq -r '.website_request.public_id' <<<"$website_draft")"
website_submitted="$(curl -s -b "$cookie_jar" -X PATCH "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"project_name\":\"Bakery website $run_id\",
  \"business_name\":\"Synthetic Sweet Bakery\",
  \"project_type\":\"landing_page\",
  \"domain_choice\":\"existing_domain\",
  \"existing_domain\":\"bakery-$run_id.example\",
  \"primary_goal\":\"Accept cake orders and explain pickup\",
  \"products_services\":\"Cakes, pastries, and custom orders\",
  \"required_features\":\"Lead form and photo gallery\",
  \"recommendation_requested\":false,
  \"action\":\"submit\"
}" "$BASE/api/customer/website-requests/$website_request_id")"
assert_json "$website_submitted" '.website_request.status == "submitted" and .website_request.submitted_at > 0 and .website_request.direct_checkout_available == false and .website_request.proof_review_status == "not_started" and .website_request.intake.schema_version == "website_discovery_v3" and .website_request.recommended_sku == "FAM-FOOT-199"'
"$DRUSH" eval "\$db = \Drupal::database(); \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$website_request_id')->execute()->fetchAssoc(); \$notice = \$db->select('famtastic_notification_outbox', 'n')->fields('n', ['template_id', 'template_version', 'body'])->condition('notification_key', 'website-request:' . \$request['id'] . ':customer')->execute()->fetchAssoc(); assert(\$notice['template_id'] === 'customer_intake_submitted'); assert((int) \$notice['template_version'] === 1); assert(str_contains((string) \$notice['body'], '/portal/?section=projects&request=$website_request_id'));"
"$DRUSH" eval "
  \$db = \Drupal::database(); \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$website_request_id')->execute()->fetchAssoc();
  \$results = \Drupal::service('famtastic_pipeline.automation_worker')->run(10, 'proof.generate', [(int) \$request['prospect_id']]);
  assert(count(\$results) === 1 && \$results[0]['status'] === 'completed');
  \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('id', \$request['id'])->execute()->fetchAssoc();
  assert(\$request['proof_review_status'] === 'owner_review');
"
test "$(http_code -b "$cookie_jar" "$BASE/api/customer/website-requests/$website_request_id/proofs/a")" = "404"
"$DRUSH" eval "\$db = \Drupal::database(); \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$website_request_id')->execute()->fetchAssoc(); \$portal = \Drupal::service('famtastic_pipeline.customer_portal'); \$portal->saveWebsiteRequestProofResearchSnapshot((int) \$request['id'], 1, ['overview' => 'Synthetic research snapshot', 'direction_rationale' => ['a' => 'Safe rationale', 'b' => 'Wild rationale', 'c' => 'OMG rationale'], 'market_signals' => ['Synthetic signal'], 'opportunities' => ['Synthetic opportunity'], 'sources' => ['Synthetic fixture'], 'researched_at' => '2026-09-05']); \$portal->approveWebsiteRequestProof((int) \$request['id'], 1); \$pending = \$db->select('famtastic_notification_outbox', 'n')->condition('notification_key', 'website-request:' . \$request['id'] . ':owner-proof-review:%', 'LIKE')->condition('status', ['queued', 'retry'], 'IN')->countQuery()->execute()->fetchField(); assert((int) \$pending === 0); \$notice = \$db->select('famtastic_notification_outbox', 'n')->fields('n', ['body', 'template_id', 'template_version'])->condition('notification_key', 'website-request:' . \$request['id'] . ':proofs:%', 'LIKE')->execute()->fetchAssoc(); assert(str_contains((string) \$notice['body'], '/portal/?section=projects&request=$website_request_id')); assert(\$notice['template_id'] === 'customer_proof_ready'); assert((int) \$notice['template_version'] === 1);"
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory "$DRUSH" php:eval '\Drupal::service("famtastic_pipeline.lifecycle_operations")->dispatchNotifications(100);' >/dev/null
"$DRUSH" eval "\$db = \Drupal::database(); \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$website_request_id')->execute()->fetchAssoc(); assert(\$request['proof_review_status'] === 'notified');"
test "$(jq -rs --arg email "$email" '[.[] | select(.to == $email and .subject == "Your FAMtastic design review has started" and .template_id == "customer_intake_submitted" and .template_version == 1 and (.html_body | contains("Your design review has started")))] | length == 1' "$mail_capture")" = "true"
test "$(jq -rs --arg email "$email" '[.[] | select(.to == $email and .subject == "Your FAMtastic Studio Review is ready" and (.html_body | contains("FAMtastic Concierge")))] | length == 1' "$mail_capture")" = "true"
test "$(http_code -b "$cookie_jar" "$BASE/api/customer/website-requests/$website_request_id/proofs/a")" = "200"
research_safe_update="$(curl -s -b "$cookie_jar" -X PATCH "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"project_name\":\"Bakery website $run_id\",\"business_name\":\"Synthetic Sweet Bakery\",\"project_type\":\"landing_page\",\"domain_choice\":\"existing_domain\",\"existing_domain\":\"bakery-$run_id.example\",\"primary_goal\":\"Accept cake orders and explain pickup\",\"products_services\":\"Cakes, pastries, and custom orders\",\"required_features\":\"Lead form and photo gallery\",\"recommendation_requested\":false,\"action\":\"save\"
}" "$BASE/api/customer/website-requests/$website_request_id")"
assert_json "$research_safe_update" '.website_request.proof_review_status == "notified" and .website_request.proofs.research_snapshot.overview == "Synthetic research snapshot"'
assert_json "$(curl -s -b "$cookie_jar" "$BASE/api/customer/workspace")" --arg request "$website_request_id" '([.website_requests[] | select(.public_id == $request)][0].proof_share) == {"enabled":false,"url":"","changed_at":null}'
test "$(http_code "$BASE/api/proof-shares/$website_request_id/$(printf '0%.0s' {1..64})")" = "404"
proof_share_enabled="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"enable"}' "$BASE/api/customer/website-requests/$website_request_id/proof-share")"
assert_json "$proof_share_enabled" '.website_request.proof_share.enabled == true and (.website_request.proof_share.url | contains("/proofs/share/"))'
proof_share_url="$(jq -r '.website_request.proof_share.url' <<<"$proof_share_enabled")"
proof_share_signature="${proof_share_url##*/}"
test "${#proof_share_signature}" = "64"
public_proofs="$(curl -s "$BASE/api/proof-shares/$website_request_id/$proof_share_signature")"
assert_json "$public_proofs" '.proof_share.proof_count == 3 and (.proof_share.variants | length) == 3 and (.proof_share | has("customer_id") | not) and (.proof_share | has("email") | not) and (.proof_share | has("intake") | not) and (.proof_share | has("recommended_sku") | not)'
test "$(http_code "$BASE/api/proof-shares/$website_request_id/$proof_share_signature/proofs/a")" = "200"
proof_share_rotated="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"rotate"}' "$BASE/api/customer/website-requests/$website_request_id/proof-share")"
new_proof_share_url="$(jq -r '.website_request.proof_share.url' <<<"$proof_share_rotated")"
new_proof_share_signature="${new_proof_share_url##*/}"
test "$new_proof_share_signature" != "$proof_share_signature"
test "$(http_code "$BASE/api/proof-shares/$website_request_id/$proof_share_signature")" = "404"
test "$(http_code "$BASE/api/proof-shares/$website_request_id/$new_proof_share_signature")" = "200"
proof_share_disabled="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"disable"}' "$BASE/api/customer/website-requests/$website_request_id/proof-share")"
assert_json "$proof_share_disabled" '.website_request.proof_share.enabled == false and .website_request.proof_share.url == "" and .website_request.proof_share.changed_at > 0'
test "$(http_code "$BASE/api/proof-shares/$website_request_id/$new_proof_share_signature")" = "404"
proof_decision="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"select","direction":"a"}' "$BASE/api/customer/website-requests/$website_request_id/proof-decision")"
assert_json "$proof_decision" '.website_request.proof_review_status == "selected" and .website_request.direct_checkout_available == true and (.website_request.proofs.variants | length) == 3'
test "$(http_code -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"select","direction":"b"}' "$BASE/api/customer/website-requests/$website_request_id/proof-decision")" = "404"
second_request="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"organization\":\"$organization_id\",\"project_name\":\"Second independent site $run_id\",\"business_name\":\"Second Business\",\"project_type\":\"new_website\",\"primary_goal\":\"Generate leads\",\"products_services\":\"Consulting\",\"action\":\"save\"
}" "$BASE/api/customer/website-requests")"
assert_json "$second_request" --arg first "$website_request_id" '.website_request.status == "draft" and .website_request.public_id != $first'
review_request="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"organization\":\"$organization_id\",\"project_name\":\"Store requiring review $run_id\",\"business_name\":\"Synthetic Store\",\"project_type\":\"online_store\",\"primary_goal\":\"Sell online\",\"products_services\":\"Baked goods\",\"recommendation_requested\":true,\"action\":\"submit\"
}" "$BASE/api/customer/website-requests")"
assert_json "$review_request" '.website_request.status == "submitted" and .website_request.direct_checkout_available == false'
review_request_id="$(jq -r '.website_request.public_id' <<<"$review_request")"
test "$(http_code -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{\"organization\":\"$organization_id\",\"website_request\":\"$review_request_id\",\"skus\":[\"FAM-FOOT-199\"],\"domain_choice\":\"existing_domain\",\"recurring_authorized\":true,\"accept_terms\":true,\"terms_version\":\"customer_terms_v4_approved\"}" "$BASE/api/customer/checkout")" = "422"
business_request="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{\"organization\":\"$organization_id\",\"project_name\":\"Business site $run_id\",\"business_name\":\"Synthetic Growth Co\",\"project_type\":\"new_website\",\"page_count\":4,\"primary_goal\":\"Generate qualified leads\",\"products_services\":\"Professional services\",\"required_features\":\"Lead form, analytics, and SEO\",\"domain_choice\":\"existing_domain\",\"action\":\"submit\"}" "$BASE/api/customer/website-requests")"
assert_json "$business_request" '.website_request.recommended_sku == "FAM-BUSINESS-499" and .website_request.direct_checkout_available == false'
business_request_id="$(jq -r '.website_request.public_id' <<<"$business_request")"
"$DRUSH" eval "
  \$db = \Drupal::database(); \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$business_request_id')->execute()->fetchAssoc(); \$now = \Drupal::time()->getRequestTime();
  \Drupal::service('famtastic_pipeline.automation_worker')->run(10, 'proof.generate', [(int) \$request['prospect_id']]);
  \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('id', \$request['id'])->execute()->fetchAssoc();
  \$portal = \Drupal::service('famtastic_pipeline.customer_portal');
  \$portal->saveWebsiteRequestProofResearchSnapshot((int) \$request['id'], 1, ['overview' => 'Business research snapshot', 'direction_rationale' => ['a' => 'Safe rationale', 'b' => 'Wild rationale', 'c' => 'OMG rationale'], 'market_signals' => ['Synthetic signal'], 'opportunities' => ['Synthetic opportunity'], 'sources' => ['Synthetic fixture'], 'researched_at' => '2026-09-05']);
  \$portal->approveWebsiteRequestProof((int) \$request['id'], 1);
  \$db->insert('famtastic_private_offer')->fields(['public_id' => \Drupal::service('uuid')->generate(), 'website_request_id' => \$request['id'], 'organization_id' => \$request['organization_id'], 'customer_id' => \$request['customer_id'], 'sku' => 'FAM-BUSINESS-499', 'list_amount_minor' => 49900, 'offered_amount_minor' => 19900, 'currency' => 'usd', 'reason' => 'Approved friend launch price', 'status' => 'active', 'expires_at' => \$now + 86400, 'created_by_uid' => 1, 'created' => \$now, 'changed' => \$now])->execute();
"
assert_json "$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"select","direction":"b"}' "$BASE/api/customer/website-requests/$business_request_id/proof-decision")" '.website_request.proof_review_status == "selected" and .website_request.direct_checkout_available == true'
business_checkout="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{\"organization\":\"$organization_id\",\"website_request\":\"$business_request_id\",\"skus\":[\"FAM-BUSINESS-499\"],\"domain_choice\":\"existing_domain\",\"recurring_authorized\":true,\"accept_terms\":true,\"terms_version\":\"customer_terms_v4_approved\"}" "$BASE/api/customer/checkout")"
assert_json "$business_checkout" '.ok == true and .order_id > 0'
business_order_id="$(jq -r '.order_id' <<<"$business_checkout")"
"$DRUSH" eval "\$order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($business_order_id); assert((float) \$order->getTotalPrice()->getNumber() === 199.0); \$context = \$order->getData('famtastic_checkout'); assert(\$context['private_offer']['list_amount_minor'] === 49900); assert(\$context['private_offer']['offered_amount_minor'] === 19900);"
grant_request="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{\"organization\":\"$organization_id\",\"project_name\":\"Sponsored site $run_id\",\"business_name\":\"Synthetic Grant Customer\",\"project_type\":\"landing_page\",\"primary_goal\":\"Generate calls\",\"products_services\":\"Local services\",\"domain_choice\":\"existing_domain\",\"action\":\"submit\"}" "$BASE/api/customer/website-requests")"
grant_request_id="$(jq -r '.website_request.public_id' <<<"$grant_request")"
grant_code="$("$DRUSH" eval "
  \$db = \Drupal::database(); \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$grant_request_id')->execute()->fetchAssoc();
  \Drupal::service('famtastic_pipeline.automation_worker')->run(10, 'proof.generate', [(int) \$request['prospect_id']]);
  \$request = \$db->select('famtastic_project_request', 'r')->fields('r')->condition('id', \$request['id'])->execute()->fetchAssoc();
  \$portal = \Drupal::service('famtastic_pipeline.customer_portal');
  \$portal->saveWebsiteRequestProofResearchSnapshot((int) \$request['id'], 1, ['overview' => 'Grant research snapshot', 'direction_rationale' => ['a' => 'Safe rationale', 'b' => 'Wild rationale', 'c' => 'OMG rationale'], 'market_signals' => ['Synthetic signal'], 'opportunities' => ['Synthetic opportunity'], 'sources' => ['Synthetic fixture'], 'researched_at' => '2026-09-05']);
  \$portal->approveWebsiteRequestProof((int) \$request['id'], 1);
  \$grant = \Drupal::service('famtastic_pipeline.grant_codes')->create(['grant_class' => 'CUSTOMER_GRANT', 'label' => 'Synthetic exact-request grant', 'customer_id' => (int) \$request['customer_id'], 'organization_id' => (int) \$request['organization_id'], 'website_request_id' => (int) \$request['id'], 'sku' => 'FAM-FOOT-199', 'discount_type' => 'free', 'max_redemptions' => 1, 'expires_at' => \Drupal::time()->getRequestTime() + 3600], 1);
  print \$grant['code'];
" | tr -d '\r\n')"
test -n "$grant_code"
assert_json "$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"action":"select","direction":"c"}' "$BASE/api/customer/website-requests/$grant_request_id/proof-decision")" '.website_request.proof_review_status == "selected"'
grant_checkout="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{\"organization\":\"$organization_id\",\"website_request\":\"$grant_request_id\",\"skus\":[\"FAM-FOOT-199\"],\"domain_choice\":\"existing_domain\",\"recurring_authorized\":true,\"accept_terms\":true,\"terms_version\":\"customer_terms_v4_approved\",\"grant_code\":\"$grant_code\"}" "$BASE/api/customer/checkout")"
assert_json "$grant_checkout" '.ok == true and .completed == true and .order_id > 0 and (.checkout_url | contains("grant=applied"))'
grant_order_id="$(jq -r '.order_id' <<<"$grant_checkout")"
"$DRUSH" eval "
  \$order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($grant_order_id); assert(\$order->getState()->value === 'completed' && \$order->getTotalPrice()->isZero());
  \$redemption = \Drupal::database()->select('famtastic_grant_redemption', 'r')->condition('commerce_order_id', $grant_order_id)->countQuery()->execute()->fetchField(); assert((int) \$redemption === 1);
  \$fulfillment = \Drupal::database()->select('famtastic_commerce_fulfillment', 'f')->fields('f')->condition('commerce_order_id', $grant_order_id)->execute()->fetchAssoc(); assert(\$fulfillment['status'] === 'fulfilled' && (int) \$fulfillment['amount_minor'] === 0);
"
other_email="other-$email"
other_password="Other-$customer_password"
other_cookie_jar="$sandbox/other-customer.cookies"
other_registration="$(curl -s -X POST "${JH[@]}" -d "{\"email\":\"$other_email\",\"password\":\"$other_password\",\"name\":\"Other Customer\",\"business_name\":\"Unrelated Organization\",\"marketing_opt_out\":true}" "$BASE/api/customer/register")"
assert_json "$other_registration" '.ok == true and .verification_required == true'
other_verification_url="$(jq -rsr --arg email "$other_email" '[.[] | select(.to == $email and (.subject | test("Verify")))] | last.body' "$mail_capture" | sed -nE 's#.*(https?://[^ ]+/verify-email\?token=[^ ]+).*#\1#p')"
other_verification_token="${other_verification_url##*token=}"
test -n "$other_verification_token"
assert_json "$(curl -s -X POST "${JH[@]}" -d "{\"token\":\"$other_verification_token\"}" "$BASE/api/customer/verify")" '.ok == true'
assert_json "$(curl -s -c "$other_cookie_jar" -X POST "${JH[@]}" -d "{\"email\":\"$other_email\",\"password\":\"$other_password\"}" "$BASE/api/customer/login")" '.customer.verified == true'
other_csrf="$(curl -s -b "$other_cookie_jar" "$BASE/session/token")"
test "$(http_code -b "$other_cookie_jar" -X PATCH "${JH[@]}" -H "X-CSRF-Token: $other_csrf" -d '{"project_name":"Stolen request","action":"save"}' "$BASE/api/customer/website-requests/$website_request_id")" = "404"
test "$(http_code -b "$other_cookie_jar" "$BASE/api/customer/website-requests/$website_request_id/proofs/a")" = "404"
test "$(http_code -b "$other_cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $other_csrf" -d '{"action":"enable"}' "$BASE/api/customer/website-requests/$website_request_id/proof-share")" = "404"
commerce_checkout="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"organization\":\"$organization_id\",
  \"website_request\":\"$website_request_id\",
  \"skus\":[\"FAM-FOOT-199\",\"FAM-REVISION-75\"],
  \"domain_choice\":\"existing_domain\",
  \"recurring_authorized\":true,
  \"accept_terms\":true,
  \"terms_version\":\"customer_terms_v4_approved\",
  \"marketing_opt_in\":false
}" "$BASE/api/customer/checkout")"
assert_json "$commerce_checkout" '.ok == true and .order_id > 0 and (.checkout_url | test("/web/checkout/[0-9]+$"))'
commerce_order_id="$(jq -r '.order_id' <<<"$commerce_checkout")"
"$DRUSH" eval "
  \$order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($commerce_order_id);
  assert(\$order && \$order->getState()->value === 'draft');
  assert((float) \$order->getTotalPrice()->getNumber() === 274.0);
  \$context = \$order->getData('famtastic_checkout');
  assert(\$context['organization_public_id'] === '$organization_id');
  assert(\$context['domain_choice'] === 'existing_domain');
  assert(\$context['recurring_authorized'] === TRUE);
  assert(\$context['website_request_public_id'] === '$website_request_id');
  \$request = \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$website_request_id')->execute()->fetchAssoc();
  assert(\$request['status'] === 'checkout_started' && (int) \$request['commerce_order_id'] === $commerce_order_id);
  \$order->getState()->applyTransitionById('place');
  \$order->save();
  \$result = \Drupal::service('famtastic_pipeline.commerce_lifecycle')->fulfill(\$order);
  assert(\$result['fulfilled'] === TRUE);
  \$request = \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', '$website_request_id')->execute()->fetchAssoc();
  assert(\$request['status'] === 'converted' && (int) \$request['project_id'] > 0 && (int) \$request['intake_id'] > 0);
  \$intake = \Drupal::entityTypeManager()->getStorage('famtastic_intake')->load((int) \$request['intake_id']);
  assert(str_contains((string) \$intake->get('services')->value, 'Cakes, pastries'));
"
converted_workspace="$(curl -s -b "$cookie_jar" "$BASE/api/customer/workspace")"
assert_json "$converted_workspace" --arg request "$website_request_id" '
  ([.website_requests[] | select(.public_id == $request and .status == "converted")] | length) == 1 and
  ([.website_requests[] | select(.status == "draft")] | length) >= 1 and
  (.projects | length) >= 2
'
owner_site_key="synthetic-owner-$run_id"
"$DRUSH" eval "\$request = \Drupal::database()->select('famtastic_project_request', 'r')->fields('r', ['id'])->condition('public_id', '$website_request_id')->execute()->fetchAssoc(); \Drupal::service('famtastic_pipeline.booking_site_owners')->bindToConvertedRequest('$owner_site_key', (int) \$request['id'], 1);"
assert_json "$(curl -s -b "$cookie_jar" "$BASE/api/customer/owner-sites/$owner_site_key/booking-requests")" '.ok == true and .site_key != "" and (.requests | type == "array")'
test "$(http_code -b "$other_cookie_jar" "$BASE/api/customer/owner-sites/$owner_site_key/booking-requests")" = "404"
owner_window="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{"label":"Synthetic request window","starts_at":1893456000,"ends_at":1893459600,"service_keys":["consultation"],"status":"draft"}' "$BASE/api/customer/owner-sites/$owner_site_key/availability")"
assert_json "$owner_window" '.ok == true and .window.status == "draft"'
assert_json "$(curl -s -b "$cookie_jar" "$BASE/api/customer/owner-sites/$owner_site_key/availability")" '.ok == true and (.windows | length) == 1'
test "$(http_code -b "$other_cookie_jar" "$BASE/api/customer/owner-sites/$owner_site_key/availability")" = "404"
preferences="$(curl -s -b "$cookie_jar" -X PATCH "${JH[@]}" -H "X-CSRF-Token: $csrf" -d '{
  "project_email":true,
  "support_email":true,
  "billing_email":true,
  "analytics_digest":"monthly",
  "product_education":false,
  "deals_promotions":false,
  "topics":["websites"]
}' "$BASE/api/customer/preferences")"
assert_json "$preferences" '.preferences.support_email == true and .preferences.deals_promotions == false'
support="$(curl -s -b "$cookie_jar" -X POST "${JH[@]}" -H "X-CSRF-Token: $csrf" -d "{
  \"organization\":\"$organization_id\",
  \"kind\":\"support\",
  \"subject\":\"Synthetic proof request $run_id\",
  \"body\":\"Please confirm that the complete customer-support notification path works.\"
}" "$BASE/api/customer/threads")"
assert_json "$support" '.ok == true and .thread.status == "open"'
FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE="$mail_capture" FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory "$DRUSH" php:eval '\Drupal::service("famtastic_pipeline.lifecycle_operations")->dispatchNotifications(100);' >/dev/null
test "$(jq -rs --arg email "$email" '[.[] | select(.to == $email)] | length >= 2' "$mail_capture")" = "true"

portal="$(curl -s "${TH[@]}" "$BASE/api/pipeline/session")"
assert_json "$portal" --arg package "$PACKAGE" '
  .order.payment_status == "paid" and
  .order.package == $package and
  (any(.add_ons[]; .package == "revision_addon_75" and .payment_status == "paid")) and
  .project.approval_status == "approved" and
  .deployment.status == "deployed" and
  .domain.owner_type == "customer" and
  .domain.dns_status == "verified" and
  .domain.ssl_status == "verified" and
  .hosting.status == "recurring" and
  .subscription.status == "active"
'

"$DRUSH" eval "
  \$db = \\Drupal::database();
  foreach ([
    'lead.imported',
    'proof.ready',
    'email.sent',
    'email.clicked',
    'proof.selected',
    'payment.verified',
    'proof.converted',
    'revision_addon.fulfilled',
    'project.approved',
  ] as \$type) {
    \$count = \$db->select('famtastic_event', 'e')
      ->condition('event_type', \$type)
      ->condition('prospect_id', $prospect_id)
      ->countQuery()->execute()->fetchField();
    assert((int) \$count >= 1, \$type);
  }
  foreach ([
    'deployment.deployed',
    'domain.verified',
    'hosting.included_started',
    'hosting.renewal_paid',
  ] as \$type) {
    \$count = \$db->select('famtastic_event', 'e')
      ->condition('event_type', \$type)
      ->condition('project_id', $project_id)
      ->countQuery()->execute()->fetchField();
    assert((int) \$count >= 1, \$type);
  }
  \$recurringConsent = \$db->select('famtastic_consent', 'c')
    ->condition('prospect_id', $prospect_id)
    ->condition('consent_type', 'recurring_hosting')
    ->condition('status', 'accepted')
    ->countQuery()->execute()->fetchField();
  assert((int) \$recurringConsent === 1);
"

jq -n \
  --arg run_id "$run_id" \
  --arg campaign "$campaign" \
  --arg package "$PACKAGE" \
  --arg email_hash "$(printf '%s' "$email" | shasum -a 256 | awk '{print $1}')" \
  --argjson prospect_id "$prospect_id" \
  --argjson project_id "$project_id" \
  --argjson deployment_id "$deployment_id" \
  --arg organization "$organization_id" \
  --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --argjson captured_messages "$(jq -s 'length' "$mail_capture")" \
  '{
    schema:"famtastic.synthetic-proof.v1",
    status:"passed",
    run_id:$run_id,
    campaign:$campaign,
    package:$package,
    synthetic_customer_email_sha256:$email_hash,
    records:{prospect_id:$prospect_id,project_id:$project_id,deployment_id:$deployment_id,organization_public_id:$organization},
    checks:{proofs:3,payment_verified:true,intake:true,revision_add_on:true,approval:true,deployment:true,domain:true,hosting_renewal:true,account_verified:true,portal_ownership:true,website_request_draft:true,repeat_website_requests:true,review_required_for_complex_scope:true,owner_proof_gate:true,account_proof_selection:true,cross_account_proof_isolation:true,unlisted_proof_share:true,proof_share_revocation:true,proof_share_privacy:true,website_request_commerce_binding:true,scoped_zero_dollar_grant:true,cross_customer_request_isolation:true,preferences:true,support_notifications:true},
    captured_transactional_messages:$captured_messages,
    generated_at:$generated_at
  }' > "$evidence_dir/evidence.json"
printf '# FAMtastic synthetic customer proof\n\n- Status: PASS\n- Run: `%s`\n- Package: `%s`\n- Evidence: `evidence.json`\n- Safety: memory email, signed local payment webhook, isolated deployment root, fixture DNS, memory renewal provider.\n' \
  "$campaign" "$PACKAGE" > "$evidence_dir/README.md"

echo "PASS: correlated $PACKAGE lead-to-proof-to-sale-to-account-to-portal-to-launch-to-renewal journey verified."
echo "Evidence: $evidence_dir/evidence.json"
