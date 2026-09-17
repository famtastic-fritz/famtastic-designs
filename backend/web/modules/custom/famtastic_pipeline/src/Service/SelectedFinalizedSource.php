<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Maps a real Next export and separate agency authority into the existing contract. */
final class SelectedFinalizedSource {
  public static function validate(array $export, array $authority): void {
    $body = $export; unset($body['sha256']);
    $hash = hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    if (($export['schema'] ?? '') !== 'famtastic.finalized-source.v1' || !hash_equals($hash, (string) ($export['sha256'] ?? ''))) throw new \InvalidArgumentException('selected_continuation_source_export_digest_mismatch');
    if (empty($authority['evidence_ref']) || ($authority['site_id'] ?? '') !== ($export['site_id'] ?? '') || ($authority['repository_path'] ?? '') !== ($export['repository']['repository_path'] ?? '') || ($authority['source_export_sha256'] ?? '') !== $hash) throw new \InvalidArgumentException('selected_continuation_source_mapping_required');
  }

  public static function continuation(array $export, array $intent, array $authority): array {
    self::validate($export, $authority);
    if ((string) ($authority['project_id'] ?? '') !== $intent['project_id'] || (string) ($authority['customer_id'] ?? '') !== $intent['customer_id'] || ($authority['request_id'] ?? '') !== $intent['request_id']) throw new \InvalidArgumentException('selected_continuation_source_mapping_identity_mismatch');
    if (($export['scope_complete'] ?? FALSE) !== TRUE || !empty($export['issues']) || !empty($intent['requested_changes']) || ($export['review_qa']['source_binding']['manifest_sha256'] ?? '') !== ($export['manifest_sha256'] ?? '') || ($export['review_qa']['source_binding']['site_id'] ?? '') !== $export['site_id'] || ($export['review_qa']['source_binding']['run_id'] ?? '') !== $export['run_id']) throw new \InvalidArgumentException('selected_continuation_source_scope_incomplete');
    if (($authority['scope_evidence_ref'] ?? '') !== ($export['scope']['evidence_ref'] ?? '') || empty($authority['scope_evidence_ref'])) throw new \InvalidArgumentException('selected_continuation_scope_binding_required');
    $requested = $intent['scope']['snapshot'];
    $requestedHash = hash('sha256', json_encode($requested, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    if (!hash_equals($requestedHash, (string) ($authority['request_scope_sha256'] ?? ''))) throw new \InvalidArgumentException('selected_continuation_requested_scope_changed');
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
