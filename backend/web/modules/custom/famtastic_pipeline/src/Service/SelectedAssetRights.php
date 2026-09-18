<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Exact reference sharing inside the same protected project, never a license. */
final class SelectedAssetRights {
  private static function matches(array $asset, array $row, string $hash, int $bytes): bool {
    return (string) ($asset['website_request_id'] ?? '') === (string) $row['id']
      && (string) ($asset['customer_id'] ?? '') === (string) $row['customer_id']
      && ($asset['status'] ?? '') === 'active' && !empty($asset['ownership_confirmed'])
      && ($asset['sha256'] ?? '') === $hash && (int) ($asset['size_bytes'] ?? 0) === $bytes;
  }
  public static function bind(array $row, array $records, array $asset): array {
    $matches = array_values(array_filter($records, static fn(array $record): bool => self::matches($record, $row, $asset['sha256'], (int) $asset['size_bytes'])));
    if (count($matches) !== 1) throw new \InvalidArgumentException('selected_continuation_asset_reference_binding_missing:' . $asset['relative_path']);
    $record = $matches[0];
    return ['status' => 'approved', 'scope' => 'protected_review_only', 'usage' => 'exact_original_bytes',
      'evidence_ref' => 'request-asset:' . $record['public_id'] . ':same-project-reference-sharing',
      'request_asset_id' => $record['public_id'], 'website_request_id' => (string) $row['id'], 'customer_id' => (string) $row['customer_id'],
      'sha256' => $asset['sha256'], 'bytes' => (int) $asset['size_bytes'],
      'publication_authorized' => FALSE, 'ai_transformation_authorized' => FALSE, 'legal_license_asserted' => FALSE];
  }
  public static function assertPacket(\Drupal\Core\Database\Connection $database, array $packet): void {
    foreach ($packet['continuation']['files'] ?? [] as $file) {
      $rights = $file['rights'] ?? [];
      if (($rights['scope'] ?? '') !== 'protected_review_only') continue;
      if (($rights['usage'] ?? '') !== 'exact_original_bytes' || ($packet['continuation']['requested_next_action'] ?? '') !== 'protected_review'
        || ($rights['publication_authorized'] ?? NULL) !== FALSE || ($rights['ai_transformation_authorized'] ?? NULL) !== FALSE || ($rights['legal_license_asserted'] ?? NULL) !== FALSE) throw new \InvalidArgumentException('selected_continuation_asset_reference_scope_changed');
      $asset = $database->select('famtastic_request_asset', 'a')->fields('a')->condition('public_id', $rights['request_asset_id'] ?? '')->execute()->fetchAssoc();
      $row = ['id' => $packet['continuation']['website_request_id'], 'customer_id' => $packet['continuation']['customer']['id']];
      $artifacts = array_values(array_filter($packet['artifacts'], static fn(array $a): bool => $a['path'] === $file['source_path']));
      if (!$asset || count($artifacts) !== 1 || !self::matches($asset, $row, $artifacts[0]['sha256'], $artifacts[0]['bytes'])
        || ($rights['sha256'] ?? '') !== $artifacts[0]['sha256'] || ($rights['bytes'] ?? 0) !== $artifacts[0]['bytes']) throw new \InvalidArgumentException('selected_continuation_asset_reference_rights_changed');
    }
  }
  public static function assertExport(array $packet, array $receipt, array $export): void {
    $restricted = array_values(array_filter($packet['continuation']['files'], static fn(array $f): bool => ($f['rights']['scope'] ?? '') === 'protected_review_only'));
    $policy = $export['use_restrictions'] ?? NULL;
    if (!$restricted && !$policy) return;
    if (!$restricted || !is_array($policy) || ($receipt['use_restrictions'] ?? NULL) != $policy
      || ($policy['schema'] ?? '') !== 'famtastic.source-use-restrictions.v1' || ($policy['scope'] ?? '') !== 'protected_review_only'
      || ($policy['project_id'] ?? '') !== $packet['project_id'] || ($policy['customer_id'] ?? '') !== $packet['continuation']['customer']['id'] || ($policy['request_id'] ?? '') !== $packet['request_id']) throw new \InvalidArgumentException('selected_continuation_source_use_restrictions_missing');
    foreach ($restricted as $file) {
      $matches = array_values(array_filter($policy['files'] ?? [], static fn(array $f): bool => $f['path'] === $file['path'] && $f['rights'] == $file['rights'] && $f['sha256'] === $file['rights']['sha256'] && $f['bytes'] === $file['rights']['bytes']));
      if (count($matches) !== 1) throw new \InvalidArgumentException('selected_continuation_source_use_restrictions_changed');
    }
  }
}
