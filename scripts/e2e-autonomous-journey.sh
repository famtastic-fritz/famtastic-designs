#!/usr/bin/env bash
set -euo pipefail

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

echo "PASS: correlated $PACKAGE lead-to-three-proofs-to-sale-to-launch-to-renewal journey verified."
