<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Maps a real Next export and separate agency authority into the existing contract. */
final class SelectedFinalizedSource {
  /** Persist the source mapping emitted by a verified, packet-matched worker. */
  public static function recordCompletion(object $project, array $packet, array $receipt): void {
    $mapping = $receipt['source_completion'] ?? NULL;
    if ($mapping === NULL) {
      if (($packet['continuation']['brand']['design_contract']['kind'] ?? '') === 'selected-source-preservation-v1') throw new \InvalidArgumentException('selected_continuation_source_completion_required');
      return;
    }
    if (!is_array($mapping) || ($mapping['project_id'] ?? '') !== $packet['project_id'] || ($mapping['customer_id'] ?? '') !== $packet['continuation']['customer']['id'] || ($mapping['request_id'] ?? '') !== $packet['request_id'] || ($mapping['source_export_sha256'] ?? '') !== ($receipt['source_export_sha256'] ?? '')) throw new \InvalidArgumentException('selected_continuation_source_completion_identity');
    $record = self::validate($mapping['source_export'] ?? [], $mapping);
    SelectedAssetRights::assertExport($packet, $receipt, $record);
    if (empty($record['scope_complete']) || ($record['scope']['required_pages'] ?? []) !== $packet['continuation']['required_pages'] || ($record['run_id'] ?? '') !== ($mapping['run_id'] ?? '') || ($record['repository']['commit'] ?? '') !== ($receipt['repository']['commit'] ?? '')) throw new \InvalidArgumentException('selected_continuation_source_completion_scope');
    $studio = json_decode((string) $project->get('studio_json')->value ?: '{}', TRUE, 512, JSON_THROW_ON_ERROR);
    $prior = $studio['selected_source_mapping'] ?? NULL;
    if ($prior && (($prior['site_id'] ?? '') !== $mapping['site_id'] || ($prior['repository_path'] ?? '') !== $mapping['repository_path'])) throw new \InvalidArgumentException('selected_continuation_source_mapping_identity_changed');
    if (isset($mapping['originating_system']) && $mapping['originating_system'] !== ($prior['originating_system'] ?? $packet['continuation']['initiating_system'])) throw new \InvalidArgumentException('selected_continuation_source_origin_changed');
    if ($prior === $mapping) return;
    if (isset($studio['next_source_export'])) $studio['next_source_export_history'][] = $studio['next_source_export'];
    $studio['selected_source_mapping'] = $mapping;
    $studio['next_source_export'] = $mapping['source_export'];
    $project->set('studio_json', json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
  }

  public static function validate(array $export, array $authority): array {
    $keys = array_keys($export); sort($keys);
    if (($export['schema'] ?? '') !== 'famtastic.finalized-source-wire.v2' || !is_string($export['payload_json'] ?? NULL) || $keys !== ['payload_json', 'schema', 'sha256']) throw new \InvalidArgumentException('selected_continuation_source_export_digest_mismatch');
    $hash = hash('sha256', "famtastic.finalized-source-wire.v2\n" . $export['payload_json']);
    if (!hash_equals($hash, (string) ($export['sha256'] ?? ''))) throw new \InvalidArgumentException('selected_continuation_source_export_digest_mismatch');
    // Keep the wire payload unchanged in storage. Decode only after byte verification.
    $export = json_decode($export['payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($export) || ($export['schema'] ?? '') !== 'famtastic.finalized-source.v1' || array_key_exists('sha256', $export)) throw new \InvalidArgumentException('selected_continuation_source_export_payload_invalid');
    if (empty($authority['evidence_ref']) || ($authority['site_id'] ?? '') !== ($export['site_id'] ?? '') || ($authority['repository_path'] ?? '') !== ($export['repository']['repository_path'] ?? '') || ($authority['source_export_sha256'] ?? '') !== $hash) throw new \InvalidArgumentException('selected_continuation_source_mapping_required');
    return $export + ['sha256' => $hash];
  }

  public static function continuation(array $export, array $intent, array $authority): array {
    if (!empty(self::validate($export, $authority)['use_restrictions'])) throw new \InvalidArgumentException('selected_continuation_protected_source_requires_current_asset_bindings');
    $export = self::validate($export, $authority);
    if ((string) ($authority['project_id'] ?? '') !== $intent['project_id'] || (string) ($authority['customer_id'] ?? '') !== $intent['customer_id'] || ($authority['request_id'] ?? '') !== $intent['request_id']) throw new \InvalidArgumentException('selected_continuation_source_mapping_identity_mismatch');
    if (($export['scope_complete'] ?? FALSE) !== TRUE || !empty($export['issues']) || !empty($intent['requested_changes']) || ($export['review_qa']['source_binding']['manifest_sha256'] ?? '') !== ($export['manifest_sha256'] ?? '') || ($export['review_qa']['source_binding']['site_id'] ?? '') !== $export['site_id'] || ($export['review_qa']['source_binding']['run_id'] ?? '') !== $export['run_id']) throw new \InvalidArgumentException('selected_continuation_source_scope_incomplete');
    if (($authority['scope_evidence_ref'] ?? '') !== ($export['scope']['evidence_ref'] ?? '') || empty($authority['scope_evidence_ref'])) throw new \InvalidArgumentException('selected_continuation_scope_binding_required');
    $requested = $intent['scope']['snapshot'];
    $scopeJson = $intent['scope']['snapshot_json'] ?? '';
    if (($intent['scope']['digest_strategy'] ?? '') !== 'sha256-json-utf8-bytes.v1' || !is_string($scopeJson) || json_decode($scopeJson, TRUE, 512, JSON_THROW_ON_ERROR) !== $requested) throw new \InvalidArgumentException('selected_continuation_requested_scope_changed');
    $requestedHash = hash('sha256', $scopeJson);
    if (!hash_equals($requestedHash, (string) ($intent['scope']['snapshot_sha256'] ?? '')) || !hash_equals($requestedHash, (string) ($authority['request_scope_sha256'] ?? ''))) throw new \InvalidArgumentException('selected_continuation_requested_scope_changed');
    if ((int) ($requested['page_count'] ?? 0) !== count($export['scope']['required_pages'])) throw new \InvalidArgumentException('selected_continuation_requested_pages_incomplete');
    foreach (['required_features', 'integrations', 'booking_details', 'ecommerce_details', 'custom_needs'] as $field) {
      if (trim((string) ($requested[$field] ?? '')) !== '') throw new \InvalidArgumentException('unsupported_scope: requested features require an executable recipe: ' . $field);
    }
    $files = [];
    foreach ($export['files'] as $file) {
      $delivery = $authority['files'][$file['path']] ?? [];
      $matches = array_values(array_filter($intent['source']['artifacts'], static fn(array $a): bool => $a['path'] === ($delivery['source_path'] ?? '') && $a['sha256'] === $file['sha256'] && $a['bytes'] === $file['bytes']));
      if (count($matches) !== 1 || empty($delivery['url']) || ($delivery['rights']['status'] ?? '') !== 'approved' || empty($delivery['rights']['evidence_ref'])) throw new \InvalidArgumentException('selected_continuation_file_authority_binding_required');
      $files[] = ['path' => $file['path'], 'source_path' => $delivery['source_path'], 'url' => $delivery['url'], 'rights' => $delivery['rights']];
    }
    $design = $export['spec_snapshot']['brand']['design_contract'] ?? NULL;
    if (!is_array($design)) throw new \InvalidArgumentException('selected_continuation_export_design_missing');
    return [
      'operation' => 'package_existing', 'initiating_system' => 'studio',
      'correlation_id' => $intent['intent_id'], 'requested_next_action' => 'protected_review',
      'source_export_sha256' => $export['sha256'],
      'spec' => ['capability_class' => 'static', 'site_needs' => ['pages' => $export['scope']['required_pages']]],
      'required_pages' => $export['scope']['required_pages'], 'files' => $files,
      'source' => ['campaign_id' => $intent['proof_campaign_id']],
      'selection' => ['direction_name' => $intent['source']['design_dna']['direction_name'] ?? $intent['selection']['direction_id'], 'proof_version' => (string) $intent['selection']['revision'], 'approval_id' => $intent['intent_id']],
      'brand' => ['design_contract' => $design],
      'research_packet_ref' => array_intersect_key($export['provenance'], array_flip(['packet_id', 'brief_hash', 'source_adapter'])),
      'hosting_target' => $authority['hosting_target'] ?? [],
    ];
  }
}
