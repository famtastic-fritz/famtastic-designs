#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"
run_id="$(date +%s)-$$"
domain="customer-$run_id.example"
result="$(mktemp "${TMPDIR:-/tmp}/famtastic-domain.XXXXXX")"
trap 'rm -f "$result"' EXIT

"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$prospect = \\Drupal\\famtastic_pipeline\\Entity\\Prospect::create([
    'business_name' => 'Domain Fixture $run_id',
    'public_email' => 'domain-$run_id@example.test',
    'campaign' => 'domain-e2e',
    'source' => 'synthetic',
    'status' => 'approved',
  ]);
  \$prospect->save();
  \$project = \\Drupal\\famtastic_pipeline\\Entity\\Project::create([
    'prospect_ref' => \$prospect->id(),
    'approval_status' => 'approved',
    'delivery_status' => 'deployed',
    'release_sha' => hash('sha256', 'release-$run_id'),
    'artifact_checksum' => hash('sha256', 'artifact-$run_id'),
  ]);
  \$project->save();
  \$now = \\Drupal::time()->getRequestTime();
  \$deploymentId = \$db->insert('famtastic_deployment')->fields([
    'deployment_key' => 'domain-e2e:$run_id',
    'project_id' => \$project->id(),
    'customer_key' => 'domain-fixture-$run_id',
    'release_sha' => hash('sha256', 'release-$run_id'),
    'artifact_checksum' => hash('sha256', 'artifact-$run_id'),
    'status' => 'deployed',
    'target_path' => '/synthetic/domain-fixture-$run_id',
    'public_url' => 'https://customer-host.example/',
    'deployed_at' => \$now,
    'created' => \$now,
  ])->execute();
  \$service = \\Drupal::service('famtastic_pipeline.domain_lifecycle');
  \$record = \$service->register(
    (int) \$project->id(),
    '$domain',
    'Fixture Customer',
    'customer-selected',
    'delegated',
    ['authorized_at' => gmdate(DATE_ATOM), 'method' => 'signed-test-consent']
  );
  print json_encode(['domain_id' => (int) \$record['id'], 'deployment_id' => (int) \$deploymentId]);
" > "$result"
domain_id="$(jq -r '.domain_id' "$result")"

fixture="$(jq -nc --arg domain "$domain" '{($domain): {expected_target:"customer-host.example", observed_targets:["customer-host.example"], ssl_valid:true, certificate_expires_at:4102444800}}')"
FAMTASTIC_DOMAIN_VERIFY_MODE=fixture \
FAMTASTIC_DOMAIN_VERIFY_FIXTURE="$fixture" \
  "$DRUSH" famtastic:jobs-run --type=domain.verify --limit=100 >/dev/null

"$DRUSH" eval "
  \$db = \\Drupal::database();
  \$domain = \$db->select('famtastic_domain', 'd')->fields('d')->condition('id', $domain_id)->execute()->fetchAssoc();
  assert(\$domain['owner_type'] === 'customer');
  assert(\$domain['management_mode'] === 'delegated');
  assert(\$domain['authorization_evidence'] !== '');
  assert(\$domain['dns_status'] === 'verified');
  assert(\$domain['ssl_status'] === 'verified');
  assert((int) \$domain['last_verified_at'] > 0);
  \$event = \$db->select('famtastic_event', 'e')->condition('event_type', 'domain.verified')->condition('project_id', \$domain['project_id'])->countQuery()->execute()->fetchField();
  assert((int) \$event >= 1);
  \$job = \$db->select('famtastic_job', 'j')->condition('job_type', 'hosting.activate')->condition('status', 'queued')->countQuery()->execute()->fetchField();
  assert((int) \$job >= 1);
"

echo "PASS: customer ownership, delegated authorization, read-only DNS/SSL verification, and hosting handoff verified."
