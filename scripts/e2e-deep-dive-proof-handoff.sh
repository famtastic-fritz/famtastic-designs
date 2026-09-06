#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
email="deep-dive-proof-$run_id@example.test"

created="$($DRUSH --root="$REPO_ROOT/backend/web" php:eval "
  \$deepDives = \Drupal::service('famtastic_pipeline.deep_dive_invitations');
  \$portal = \Drupal::service('famtastic_pipeline.customer_portal');
  \$created = \$deepDives->create('$email', 'Deep Dive Proof $run_id', 'Journey Owner');
  \$record = \$deepDives->activate((string) \$created['record']['public_id']);
  foreach (\Drupal\\famtastic_pipeline\\Service\\DeepDiveInvitationService::questions() as \$question) {
    if (\$question['key'] === 'business_name') {
      continue;
    }
    \$answer = match (\$question['type']) {
      'choice' => (string) array_key_first(\$question['options']),
      'url' => 'booksy.com/example/deep-dive-proof-$run_id',
      default => \$question['key'] === 'business_name' ? 'Deep Dive Proof $run_id' : 'Synthetic answer for ' . \$question['key'],
    };
    \$deepDives->answer((string) \$record['public_id'], (string) \$created['secret'], (string) \$question['key'], \$answer);
  }
  \$storage = \Drupal::entityTypeManager()->getStorage('user');
  \$user = \$storage->create(['name' => '$email', 'mail' => '$email', 'pass' => 'Synthetic-$run_id-Password!', 'status' => TRUE]);
  \$user->save();
  \$customer = \$portal->customerForUid((int) \$user->id()) ?: \$portal->createCustomer(\$user, ['name' => 'Journey Owner', 'business_name' => 'Deep Dive Proof $run_id']);
  \$deepDives->attachPendingCustomer((string) \$record['public_id'], (int) \$customer['id']);
  \$portal->markVerified((int) \$customer['id']);
  \$claimed = \$deepDives->claimForVerifiedCustomer((int) \$customer['id'], '$email');
  assert(count(\$claimed) === 1);
  \$requestId = \$portal->createWebsiteRequestFromDeepDive((int) \$customer['id'], \$claimed[0]);
  \$deepDives->attachWebsiteRequest((int) \$claimed[0]['id'], (int) \$requestId);
  \$request = \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')->condition('id', \$requestId)->execute()->fetchAssoc();
  \$job = \Drupal::database()->select('famtastic_job', 'j')->fields('j')->condition('job_key', 'website_proof.generate.v1:request:' . \$requestId . ':brief:%', 'LIKE')->execute()->fetchAssoc();
  assert(\$request['status'] === 'submitted');
  assert((int) \$request['submitted_at'] > 0);
  assert((json_decode((string) \$request['intake_data'], TRUE)['proof_request']['requested_count'] ?? 0) === 3);
  assert(\$job['status'] === 'queued');
  \Drupal::database()->update('famtastic_job')->fields(['status' => 'failed', 'attempts' => 5, 'changed' => \Drupal::time()->getRequestTime()])->condition('id', \$job['id'])->execute();
  \Drupal::service('famtastic_pipeline.operational_ledger')->openException('job:' . \$job['job_key'], 'proof.generate', 'Synthetic exhausted proof job.', ['test' => TRUE], (int) \$request['prospect_id'], (int) \$job['id'], FALSE);
  print json_encode(['request_id' => (int) \$requestId, 'request_public_id' => (string) \$request['public_id'], 'customer_id' => (int) \$customer['id'], 'organization_id' => (int) \$request['organization_id'], 'prospect_id' => (int) \$request['prospect_id'], 'job_id' => (int) \$job['id']]);
")"

request_id="$(jq -r '.request_id' <<<"$created")"
request_public_id="$(jq -r '.request_public_id' <<<"$created")"
customer_id="$(jq -r '.customer_id' <<<"$created")"
organization_id="$(jq -r '.organization_id' <<<"$created")"
prospect_id="$(jq -r '.prospect_id' <<<"$created")"
job_id="$(jq -r '.job_id' <<<"$created")"

resume="$($DRUSH --root="$REPO_ROOT/backend/web" famtastic:deep-dive-proof-resume "$request_public_id" --confirm="$request_public_id")"
jq -e --argjson job "$job_id" '.request_status == "submitted" and (.proof_job.id | tonumber) == $job and .proof_job.status == "queued" and .exhausted_job_requeued == true' <<<"$resume" >/dev/null

FAMTASTIC_ALLOW_STUB_OUTREACH=1 "$DRUSH" --root="$REPO_ROOT/backend/web" php:eval "
  \$results = \Drupal::service('famtastic_pipeline.automation_worker')->run(1, 'proof.generate', [$prospect_id]);
  assert(count(\$results) === 1 && \$results[0]['status'] === 'completed');
  \$request = \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')->condition('id', $request_id)->execute()->fetchAssoc();
  assert(\$request['proof_review_status'] === 'owner_review');
  \$portal = \Drupal::service('famtastic_pipeline.customer_portal');
  \$portal->saveWebsiteRequestProofResearchSnapshot($request_id, 1, [
    'overview' => 'Synthetic deep-dive research summary.',
    'direction_rationale' => ['a' => 'Safe rationale.', 'b' => 'Wild rationale.', 'c' => 'OMG rationale.'],
    'market_signals' => ['Synthetic signal.'],
    'opportunities' => ['Synthetic opportunity.'],
    'sources' => ['Synthetic fixture.'],
    'researched_at' => '2026-09-05',
  ]);
  \$portal->approveWebsiteRequestProof($request_id, 1);
  \$requests = \$portal->websiteRequests($customer_id, $organization_id);
  \$current = array_values(array_filter(\$requests, static fn(array \$item): bool => \$item['public_id'] === '$request_public_id'))[0];
  assert(\$current['proof_review_status'] === 'customer_ready');
  assert(\$current['proof_handoff']['state'] === 'choose_direction');
  assert(count(\$current['proofs']['variants']) === 3);
  \$selected = \$portal->decideWebsiteRequestProof($customer_id, '$request_public_id', ['action' => 'select', 'direction' => 'b']);
  assert(\$selected['proof_review_status'] === 'selected');
  assert(\$selected['proof_handoff']['state'] === 'direction_selected');
" >/dev/null

echo "PASS: completed deep dive became one submitted request, recovered its exact failed proof job, exposed three owner-approved directions, and recorded a customer selection without sending or charging."
