<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Projects existing selection facts; never promotes a concept to a full site. */
final class SelectedSourceIntent {

  public static function create(array $row, string $projectId, int $variantId, string $direction, int $revision, string $selectedAt, array $artifacts, array $dna, array $assets, ?string $changes): array {
    $intake = json_decode((string) ($row['intake_data'] ?? '{}'), TRUE) ?: [];
    $scope = array_intersect_key($intake, array_flip(['page_count', 'page_list', 'required_features', 'integrations', 'booking_details', 'ecommerce_details', 'custom_needs', 'content_status', 'copywriting_needs', 'products_services', 'desired_actions']));
    return [
      'schema' => 'famtastic.selected-source-intent.v1',
      'intent_id' => 'selected-source:request:' . $row['id'] . ':revision:' . $revision,
      'request_id' => (string) $row['public_id'], 'website_request_id' => (int) $row['id'],
      'customer_id' => (string) $row['customer_id'], 'project_id' => $projectId,
      'proof_campaign_id' => (string) $row['proof_campaign_id'],
      'selection' => ['variant_id' => (string) $variantId, 'direction_id' => $direction, 'revision' => $revision, 'selected_at' => $selectedAt],
      'scope' => ['status' => 'requested', 'source' => 'famtastic_project_request.intake_data', 'snapshot' => $scope],
      'source' => ['kind' => 'selected_proof', 'artifacts' => $artifacts, 'design_dna' => $dna,
        'design_dna_sha256' => hash('sha256', json_encode($dna, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'completion' => 'not_established'],
      // Preserve each distinct statement. These are not output-file approvals.
      'asset_authority' => ['source' => 'famtastic_request_asset', 'records' => $assets, 'output_bindings' => []],
      'requested_changes' => $changes === NULL ? [] : [['text' => $changes, 'status' => 'pending']],
      'operation' => 'plan_remaining_work',
      'issues' => [
        ['stage' => 'plan', 'code' => 'scope_completion_unestablished', 'owner' => 'next', 'source' => 'selected proof and requested scope'],
        ['stage' => 'materialize', 'code' => 'artifact_delivery_binding_missing', 'owner' => 'designs', 'source' => 'protected artifact reader'],
        ['stage' => 'rights', 'code' => 'output_rights_binding_missing', 'owner' => 'designs', 'source' => 'request assets or registered Build DNA'],
        ['stage' => 'host', 'code' => 'review_target_binding_missing', 'owner' => 'designs', 'source' => 'project review-target registry'],
      ],
    ];
  }
}
