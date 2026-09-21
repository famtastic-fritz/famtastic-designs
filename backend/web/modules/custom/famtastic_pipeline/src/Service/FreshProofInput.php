<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/** Versioned database snapshots, not permission to use a provider or publish. */
final class FreshProofInput {
  public static function wire(array $value): string {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
  }

  public static function request(array $row, array $freshness): array {
    $snapshot = ['schema' => 'famtastic.fresh-proof-request.v1'];
    $snapshot['freshness'] = $freshness;
    foreach (['id', 'customer_id', 'organization_id', 'prospect_id', 'proof_campaign_id', 'submitted_at', 'recommendation_requested'] as $field) $snapshot[$field] = (int) $row[$field];
    foreach (['commerce_order_id', 'project_id'] as $field) $snapshot[$field] = $row[$field] === NULL ? NULL : (int) $row[$field];
    foreach (['public_id', 'project_name', 'business_name', 'project_type', 'domain_choice', 'existing_domain', 'status', 'proof_review_status', 'selected_proof_direction'] as $field) $snapshot[$field] = (string) $row[$field];
    $snapshot['intake'] = json_decode($row['intake_data'], TRUE, flags: JSON_THROW_ON_ERROR);
    return $snapshot;
  }

  /** Mutation boundaries lock actual assets; a read-only projection need not. */
  public static function assets(Connection $db, array $request, bool $lock = TRUE): array {
    $query = $db->select('famtastic_request_asset', 'a')->fields('a')
      ->condition('website_request_id', (int) $request['id'])->orderBy('id');
    if ($lock) $query->forUpdate();
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $assets = [];
    foreach ($rows as $row) {
      if ((int) $row['customer_id'] !== (int) $request['customer_id'] || !in_array($row['status'], ['active', 'withdrawn'], TRUE)) throw new \RuntimeException('Proof asset ownership or status is invalid.');
      if ($row['status'] === 'active' && ((int) $row['ownership_confirmed'] !== 1 || (int) $row['file_id'] < 1
        || (int) $row['size_bytes'] < 1 || !preg_match('/^[a-f0-9]{64}$/D', $row['sha256']))) throw new \RuntimeException('Proof asset integrity or rights are missing.');
      $asset = [];
      foreach (['id', 'website_request_id', 'customer_id', 'file_id', 'size_bytes', 'created', 'changed'] as $field) $asset[$field] = (int) $row[$field];
      foreach (['public_id', 'kind', 'role', 'original_name', 'mime_type', 'sha256', 'status', 'likeness_consent_version'] as $field) $asset[$field] = (string) $row[$field];
      foreach (['ownership_confirmed', 'ai_use_consent', 'subject_permission_confirmed', 'ai_transformation_consent'] as $field) $asset[$field] = (bool) $row[$field];
      $asset['likeness_consent_at'] = $row['likeness_consent_at'] === NULL ? NULL : (int) $row['likeness_consent_at'];
      // Preserve distinct recorded consent, never infer transformation/publication.
      $assets[] = $asset;
    }
    return ['schema' => 'famtastic.fresh-proof-assets.v1', 'records' => $assets];
  }
}
