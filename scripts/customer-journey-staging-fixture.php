<?php
declare(strict_types=1);

/** Synthetic producer/host fixtures only; real selection, callback and acceptance writers. */
$root = realpath(\Drupal::root());
$db = \Drupal::database();
$options = $db->getConnectionOptions();
if (!$root || !preg_match('#/(?:famtastic-customer-proof\.[A-Za-z0-9]{6}/repo|famtastic-selected-drupal\.[A-Za-z0-9]{6})/backend/web$#', $root)
  || realpath(__DIR__) !== dirname($root, 2) . '/scripts'
  || ($options['driver'] ?? '') !== 'sqlite'
  || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory'
  || getenv('FAMTASTIC_DEPLOY_TRANSPORT') !== 'local') {
  throw new RuntimeException('Synthetic staging fixtures require the disposable canonical SQLite runtime and memory/local transports.');
}
$id = (string) getenv('FAMTASTIC_FIXTURE_REQUEST');
if (!preg_match('/^[a-f0-9-]{36}$/', $id)) throw new RuntimeException('Exact synthetic request required.');
$row = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $id)->execute()->fetchAssoc();
if (!$row || !empty($row['commerce_order_id'])) throw new RuntimeException('Unpaid disposable request required.');
$customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('id', $row['customer_id'])->execute()->fetchAssoc();
if (!$customer || !str_ends_with((string) $customer['email'], '@example.test')) throw new RuntimeException('Synthetic account required.');
$entities = \Drupal::entityTypeManager();
$target = ['staging_url' => 'https://fixture.famtasticinc.com/' . $id . '/',
  'target_path' => '/synthetic-never-deployed/' . $id, 'remote_subdirectory' => 'fixture-' . $id];

if (getenv('FAMTASTIC_FIXTURE_PHASE') === 'prepare') {
  if (!in_array($row['proof_review_status'], ['customer_ready', 'notified'], TRUE)) throw new RuntimeException('Prepare source before customer selection.');
  $storage = $entities->getStorage('proof_variant');
  $variants = $storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->condition('campaign_id', $row['proof_campaign_id'])->execute());
  if (count($variants) !== 3) throw new RuntimeException('Exact three existing generated variants required.');
  foreach ($variants as $variant) {
    $path = (string) $variant->get('artifact_path')->value;
    $absolute = realpath(dirname($root) . '/' . $path);
    if (!$absolute || !str_starts_with($absolute, $root . '/proofs/') || !is_file($absolute)) throw new RuntimeException('Contained generated proof source required.');
    $dna = json_decode((string) $variant->get('design_dna')->value ?: '{}', TRUE, flags: JSON_THROW_ON_ERROR);
    $files = [['path' => 'index.html', 'source_path' => $path, 'url' => 'https://artifacts.example.test/' . $id . '/index.html',
      'rights' => ['status' => 'approved', 'evidence_ref' => 'synthetic-authored-source']]];
    // The catalog fixture may request several pages. Materialize its remaining
    // synthetic pages; never label one proof page as a complete multipage site.
    $intake = json_decode((string) $row['intake_data'], TRUE) ?: [];
    $pageCount = max(1, min(10, (int) ($intake['page_count'] ?? 1)));
    $extra = [];
    for ($i = 2; $i <= $pageCount; $i++) {
      $name = 'fixture-page-' . $i . '.html';
      $source = dirname($path) . '/' . $name;
      $html = '<!doctype html><html lang="en"><head><title>Synthetic page ' . $i . '</title></head><body><main><h1>Local test page ' . $i . '</h1><p>Fixture content only; no customer or hosting claim.</p></main></body></html>';
      if (file_exists(dirname($root) . '/' . $source)) throw new RuntimeException('Refusing to replace existing fixture page.');
      file_put_contents(dirname($root) . '/' . $source, $html);
      $extra[] = ['path' => $source, 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)];
      $files[] = ['path' => $name, 'source_path' => $source, 'url' => 'https://artifacts.example.test/' . $id . '/' . $name,
        'rights' => ['status' => 'approved', 'evidence_ref' => 'synthetic-authored-source']];
    }
    $dna['selected_build_artifacts'] = $extra;
    $dna['selected_build_continuation'] = [
      'operation' => 'package_existing', 'initiating_system' => 'designs', 'correlation_id' => 'fixture:' . $row['id'], 'requested_next_action' => 'protected_review',
      'spec' => ['capability_class' => 'static', 'site_needs' => ['pages' => array_column($files, 'path')]], 'required_pages' => array_column($files, 'path'),
      'files' => $files, 'source' => ['campaign_id' => (string) $row['proof_campaign_id']],
      'selection' => ['direction_name' => (string) $variant->get('direction_name')->value, 'proof_version' => '1', 'approval_id' => 'fixture-source-review'],
      'brand' => ['design_contract' => ['schema_version' => 1, 'kind' => 'synthetic-static-contract']],
      'research_packet_ref' => ['packet_id' => 'fixture-research', 'brief_hash' => hash('sha256', 'synthetic-research')], 'hosting_target' => $target,
    ];
    $variant->set('design_dna', json_encode($dna, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
  }
  print "Synthetic source evidence prepared; no selection, callback or acceptance fabricated.\n";
  return;
}
if (getenv('FAMTASTIC_FIXTURE_PHASE') !== 'receipt' || $row['proof_review_status'] !== 'selected' || $row['staging_status'] !== 'queued') throw new RuntimeException('Selected queued fixture required.');
$project = $entities->getStorage('famtastic_project')->load((int) $row['project_id']);
$studio = json_decode((string) $project->get('studio_json')->value, TRUE, flags: JSON_THROW_ON_ERROR);
$packet = $studio['site_studio_build_packet'];
\Drupal::service('famtastic_pipeline.customer_portal')->assertCurrentSelectedStagingPacket($packet);
$c = $packet['continuation'];
if ($c['hosting_target'] !== $target || $c['customer']['id'] !== (string) $row['customer_id']) throw new RuntimeException('Fixture packet binding changed.');
print json_encode([
  'schema' => 'famtastic.site-studio.staging-receipt.v1', 'status' => 'deployed', 'event_id' => 'fixture-staging:' . $packet['packet_id'],
  'packet_id' => $packet['packet_id'], 'idempotency_key' => $packet['idempotency_key'], 'request_id' => $id, 'project_id' => $packet['project_id'],
  'website_request_id' => (int) $row['id'], 'customer_id' => $c['customer']['id'], 'selection_revision' => $c['selection_revision'],
  'selected_direction_id' => $packet['selected_direction_ids'][0], 'selected_artifact_sha256' => $packet['selected_artifacts'][0]['source_artifact_sha256'],
  'artifact_manifest_sha256' => $packet['artifact_manifest_sha256'], 'artifact_sha256' => hash('sha256', 'synthetic-output:' . $packet['packet_id']),
  'completed_at' => gmdate(DATE_ATOM), 'repository' => ['mode' => 'isolated_test_fixture', 'branch' => 'fixture-only'],
  'qa' => [['name' => 'synthetic-host-receipt-contract-only', 'status' => 'passed']],
] + $target, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
