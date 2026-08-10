#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
result="$(mktemp "${TMPDIR:-/tmp}/famtastic-hosting.XXXXXX")"
trap 'rm -f "$result"' EXIT

FAMTASTIC_HOSTING_BILLING_PROVIDER=memory "$DRUSH" eval "
  \$prospect = \\Drupal\\famtastic_pipeline\\Entity\\Prospect::create([
    'business_name' => 'Hosting Fixture $run_id',
    'public_email' => 'hosting-$run_id@example.test',
    'campaign' => 'hosting-e2e',
    'source' => 'synthetic',
    'status' => 'approved',
  ]);
  \$prospect->save();
  \$order = \\Drupal\\famtastic_pipeline\\Entity\\Order::create([
    'prospect_ref' => \$prospect->id(),
    'package' => 'essential_199',
    'amount' => 19900,
    'currency' => 'usd',
    'payment_status' => 'paid',
    'paid_at' => \\Drupal::time()->getRequestTime(),
  ]);
  \$order->save();
  \$project = \\Drupal\\famtastic_pipeline\\Entity\\Project::create([
    'prospect_ref' => \$prospect->id(),
    'order_ref' => \$order->id(),
    'approval_status' => 'approved',
    'delivery_status' => 'deployed',
  ]);
  \$project->save();
  \$now = \\Drupal::time()->getRequestTime();
  \\Drupal::database()->insert('famtastic_domain')->fields([
    'project_id' => \$project->id(),
    'domain_name' => 'hosting-$run_id.example',
    'owner_type' => 'customer',
    'owner_name' => 'Hosting Fixture Customer',
    'registrar' => 'customer-selected',
    'management_mode' => 'customer_managed',
    'authorization_evidence' => '{}',
    'dns_status' => 'verified',
    'ssl_status' => 'verified',
    'last_verified_at' => \$now,
    'created' => \$now,
    'changed' => \$now,
  ])->execute();
  \$service = \\Drupal::service('famtastic_pipeline.hosting_lifecycle');
  \$entitlement = \$service->activate((int) \$project->id());
  assert((int) \$entitlement['included_until'] > \$now + (86400 * 360));
  \$subscription = \$service->authorizeRecurring(
    (int) \$entitlement['id'],
    'hosting-$run_id@example.test',
    999,
    ['method' => 'acceptance-test-checkbox', 'accepted_at' => gmdate(DATE_ATOM)]
  );
  print json_encode([
    'project_id' => (int) \$project->id(),
    'entitlement_id' => (int) \$entitlement['id'],
    'subscription_id' => (int) \$subscription['id'],
    'renews_at' => (int) \$entitlement['renews_at'],
  ]);
" > "$result"

subscription_id="$(jq -r '.subscription_id' "$result")"
entitlement_id="$(jq -r '.entitlement_id' "$result")"
renews_at="$(jq -r '.renews_at' "$result")"

FAMTASTIC_HOSTING_BILLING_PROVIDER=memory "$DRUSH" eval "
  \$service = \\Drupal::service('famtastic_pipeline.hosting_lifecycle');
  \$paid = \$service->processRenewal($subscription_id, TRUE, $renews_at);
  assert(\$paid['status'] === 'active');
  \$firstFailureAt = (int) \$paid['next_attempt_at'];
  assert(\$service->processRenewal($subscription_id, FALSE, \$firstFailureAt)['status'] === 'past_due');
  assert(\$service->processRenewal($subscription_id, FALSE, \$firstFailureAt + 172800)['status'] === 'past_due');
  assert(\$service->processRenewal($subscription_id, FALSE, \$firstFailureAt + 518400)['status'] === 'canceled');
  \$entitlement = \\Drupal::database()->select('famtastic_hosting_entitlement', 'h')->fields('h')->condition('id', $entitlement_id)->execute()->fetchAssoc();
  assert(\$entitlement['status'] === 'suspended');
  assert((int) \$entitlement['suspended_at'] > 0);
  \$consent = \\Drupal::database()->select('famtastic_consent', 'c')->condition('consent_type', 'recurring_hosting')->condition('status', 'accepted')->countQuery()->execute()->fetchField();
  assert((int) \$consent >= 1);
  // Reset the time-compressed failure fixture, then prove customer-initiated
  // cancellation independently from payment-failure cancellation.
  \Drupal::database()->update('famtastic_subscription')->fields(['status' => 'active', 'cancel_at' => NULL])->condition('id', $subscription_id)->execute();
  \Drupal::database()->update('famtastic_hosting_entitlement')->fields(['status' => 'recurring', 'suspended_at' => NULL])->condition('id', $entitlement_id)->execute();
  \$canceled = \$service->cancelRecurring($entitlement_id, 'hosting-$run_id@example.test');
  assert(\$canceled['status'] === 'canceled');
  \$revoked = \Drupal::database()->select('famtastic_consent', 'c')->condition('consent_type', 'recurring_hosting')->condition('status', 'unsubscribed')->countQuery()->execute()->fetchField();
  assert((int) \$revoked >= 1);
"

echo "PASS: included year, separate USD 9.99 recurring consent, month-13 payment, retry, customer cancellation, and suspension verified."
