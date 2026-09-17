<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

final class SelectedPlanningPacket {
  public static function create(array $intent, ?string $issue = NULL): array {
    $source = array_values(array_filter($intent['source']['artifacts'], static fn(array $a): bool => $a['role'] === 'selected_preview'));
    if (count($source) !== 1) throw new \InvalidArgumentException('Planning requires exactly one selected source.');
    $selected = $source[0]; $direction = 'direction-' . $intent['selection']['direction_id'];
    $id = 'selected-planning:request:' . $intent['website_request_id'] . ':revision:' . $intent['selection']['revision'];
    return ['schema' => 'famtastic.site-studio.planning-packet.v1', 'build_class' => 'selected_direction_remaining_work',
      'packet_id' => $id, 'idempotency_key' => $id, 'request_id' => $intent['request_id'], 'project_id' => $intent['project_id'],
      'selected_direction_ids' => [$direction], 'artifacts' => $intent['source']['artifacts'],
      'artifact_manifest_sha256' => SiteStudioBuildPacketService::artifactManifestDigest($intent['source']['artifacts']),
      'selected_artifacts' => [['direction_id' => $direction, 'source_artifact_path' => $selected['path'], 'source_artifact_sha256' => $selected['sha256'], 'source_artifact_bytes' => $selected['bytes']]],
      'continuation' => ['customer' => ['id' => $intent['customer_id']], 'selection_revision' => $intent['selection']['revision'], 'website_request_id' => $intent['website_request_id']],
      'intent' => $intent, 'dispatch_issue' => $issue];
  }
}
