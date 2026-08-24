<?php

/**
 * Imports marketing/campaigns/55-cents-17-day/manifest.json into
 * famtastic_social_record so the admin command center reflects real state.
 *
 * Idempotent: merges on content_id, preserving any approvals already set in
 * the database (the database is authoritative for gate decisions).
 *
 * Run: drush -r <root> php:script backend/scripts/import-social-records.php <path-to-manifest.json>
 */

$manifestPath = $extra[0] ?? (dirname(\Drupal::root(), 2) . '/marketing/campaigns/55-cents-17-day/manifest.json');
if (!is_file($manifestPath)) {
  print "SKIP: manifest not found at {$manifestPath}\n";
  return;
}
if (!$manifestPath || !is_file($manifestPath)) {
  print "SKIP: manifest.json not found (pass path as argument)\n";
  return;
}
$manifest = json_decode((string) file_get_contents($manifestPath), TRUE);
$database = \Drupal::database();
$time = \Drupal::time()->getRequestTime();
$count = 0;
foreach ($manifest['records'] ?? [] as $record) {
  $existing = $database->select('famtastic_social_record', 'r')
    ->fields('r', ['id', 'approval_content', 'approval_media', 'approval_publish'])
    ->condition('content_id', $record['content_id'])
    ->execute()->fetchAssoc();
  $fields = [
    'day' => (int) ($record['day'] ?? 0),
    'moment' => (string) ($record['moment'] ?? ''),
    'theme' => (string) ($record['theme'] ?? ''),
    'promise' => (string) ($record['promise'] ?? ''),
    'scheduled_time_et' => (string) ($record['suggested_time_et'] ?? ''),
    'state' => (string) ($record['state'] ?? 'idea'),
    'postiz_draft_id' => (string) ($record['provider_ids']['postiz_draft_id'] ?? ''),
    'asset_variants' => json_encode($record['asset_variants'] ?? [], JSON_THROW_ON_ERROR),
    'changed' => $time,
  ];
  if ($existing) {
    // DB is authoritative for gates — never overwrite an owner decision.
    $fields['approval_content'] = (int) $existing['approval_content'];
    $fields['approval_media'] = (int) $existing['approval_media'];
    $fields['approval_publish'] = (int) $existing['approval_publish'];
    $database->update('famtastic_social_record')->fields($fields)->condition('id', $existing['id'])->execute();
  }
  else {
    $fields += [
      'content_id' => $record['content_id'],
      'approval_content' => (int) (!empty($record['approval']['content'])),
      'approval_media' => (int) (!empty($record['approval']['media'])),
      'approval_publish' => (int) (!empty($record['approval']['publish'])),
    ];
    $database->insert('famtastic_social_record')->fields($fields)->execute();
  }
  $count++;
}
print "imported {$count} records from {$manifestPath}\n";
