<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;
require_once __DIR__ . '/SelectedCreatorCreditProjection.php';

/** Canonical agency selection authority for first Studio source association. */
final class SelectedSourceAssociation {
  public static function binding(array $row, array $studio): array {
    $intent = $studio['selected_source_intent'] ?? [];
    if (($row['proof_review_status'] ?? '') !== 'selected' || !empty($row['commerce_order_id'])
      || ($intent['request_id'] ?? '') !== (string) $row['public_id'] || ($intent['project_id'] ?? '') !== (string) $row['project_id']
      || ($intent['customer_id'] ?? '') !== (string) $row['customer_id'] || ($intent['selection']['direction_id'] ?? '') !== ($row['selected_proof_direction'] ?? '')) throw new \InvalidArgumentException('source_association_current_selection_required');
    $intake = json_decode((string) ($row['intake_data'] ?? '{}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $scope = SelectedSourceIntent::requestedScope($row, $intake);
    if ($scope !== $intent['scope']['snapshot'] || ($intake['authored_content']['pages'] ?? NULL) !== ($intent['authored_content']['pages'] ?? NULL)) throw new \InvalidArgumentException('source_association_current_input_changed');
    $root = realpath(\Drupal::root() . '/proofs');
    foreach ($intent['source']['artifacts'] as $artifact) {
      $path = realpath(dirname(\Drupal::root()) . '/' . $artifact['path']);
      if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)
        || filesize($path) !== $artifact['bytes'] || hash_file('sha256', $path) !== $artifact['sha256']) throw new \InvalidArgumentException('source_association_current_source_changed');
    }
    return $intent;
  }
  public static function issue(array $row, array $studio, string $secret, int $now, string $schema = 'famtastic.source-association.v2'): array {
    if ($secret === '') throw new \InvalidArgumentException('source_association_authentication_required');
    if (!in_array($schema, ['famtastic.source-association.v1', 'famtastic.source-association.v2'], TRUE)) throw new \InvalidArgumentException('source_association_schema_unsupported');
    $intent = self::binding($row, $studio);
    $payload = ['schema' => $schema, 'association_id' => bin2hex(random_bytes(16)),
      'operation' => 'associate_verified_source', 'audience' => 'site-studio-next', 'issued_at' => $now, 'expires_at' => $now + 3600,
      'project_id' => (string) $row['project_id'], 'customer_id' => (string) $row['customer_id'], 'request_id' => (string) $row['public_id'],
      'selection' => $intent['selection'], 'scope_sha256' => $intent['scope']['snapshot_sha256'],
      'intent_sha256' => hash('sha256', json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'intent' => $intent];
    if ($schema === 'famtastic.source-association.v2') $payload['creator_credit_projection'] = SelectedCreatorCreditProjection::fromStorage(dirname(\Drupal::root()), $intent['source']['artifacts'][0]);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return ['payload_json' => $json, 'signature' => hash_hmac('sha256', $schema . "\n" . $json, $secret)];
  }
  public static function accept(array $row, array $studio, array $envelope, int $now): array {
    $grant = $envelope['association'] ?? [];
    try { $payload = json_decode((string) ($grant['payload_json'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new \InvalidArgumentException('source_association_payload_invalid'); }
    if (!is_array($payload)) throw new \InvalidArgumentException('source_association_payload_invalid');
    $stored = $studio['source_association_grants'][$payload['association_id'] ?? ''] ?? NULL;
    if (!$stored || $stored !== $grant || ($payload['operation'] ?? '') !== 'associate_verified_source' || ($payload['audience'] ?? '') !== 'site-studio-next') throw new \InvalidArgumentException('source_association_grant_unknown');
    if (!in_array($payload['schema'] ?? '', ['famtastic.source-association.v1', 'famtastic.source-association.v2'], TRUE)) throw new \InvalidArgumentException('source_association_schema_unsupported');
    $v2 = $payload['schema'] === 'famtastic.source-association.v2';
    $intent = self::binding($row, $studio);
    $sameIntent = $payload['intent_sha256'] === hash('sha256', json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    // The first source refresh itself advances one revision. Only that exact
    // recorded source may retry through it; customer input must still match.
    if (!$sameIntent && ($studio['selected_source_mapping']['association_id'] ?? '') === $payload['association_id'] && $intent['selection']['revision'] === $payload['selection']['revision'] + 1) {
      $comparison = $intent; $comparison['selection'] = $payload['intent']['selection']; $comparison['intent_id'] = $payload['intent']['intent_id']; $comparison['execution_binding'] = $payload['intent']['execution_binding'];
      $sameIntent = $comparison === $payload['intent'];
    }
    if (($payload['issued_at'] ?? PHP_INT_MAX) > $now || ($payload['expires_at'] ?? 0) < $now || !$sameIntent) throw new \InvalidArgumentException('source_association_stale');
    $mapping = $envelope['source_completion'] ?? [];
    foreach (['project_id', 'customer_id', 'request_id'] as $key) if (($mapping[$key] ?? '') !== $payload[$key] || ($envelope[$key] ?? '') !== $payload[$key]) throw new \InvalidArgumentException('source_association_identity_changed');
    if (($mapping['association_id'] ?? '') !== $payload['association_id'] || ($mapping['association_scope_sha256'] ?? '') !== $payload['scope_sha256'] || ($mapping['originating_system'] ?? '') !== 'studio' || ($mapping['handoff_initiator'] ?? '') !== 'studio') throw new \InvalidArgumentException('source_association_origin_invalid');
    $record = SelectedFinalizedSource::validate($envelope['source_export'] ?? [], $mapping);
    if (($record['run_id'] ?? '') !== ($mapping['run_id'] ?? '') || ($record['scope']['request_scope_sha256'] ?? '') !== $payload['scope_sha256']
      || ($record['scope']['evidence_ref'] ?? '') !== 'association:' . $payload['association_id'] || ($record['review_qa']['source_binding']['site_id'] ?? '') !== $record['site_id']
      || ($record['review_qa']['source_binding']['run_id'] ?? '') !== $record['run_id'] || !empty($record['review_qa']['problems'])
      || array_diff($record['issues'] ?? [], ['required_pages_incomplete'])) throw new \InvalidArgumentException('source_association_scope_changed');
    if (($mapping['source_export'] ?? NULL) !== $envelope['source_export'] || ($record['review_qa']['passed'] ?? FALSE) !== TRUE || ($record['review_qa']['source_binding']['manifest_sha256'] ?? '') !== $record['manifest_sha256'] || !empty($record['use_restrictions'])) throw new \InvalidArgumentException('source_association_verification_required');
    $home = array_values(array_filter($record['files'], static fn(array $f): bool => $f['path'] === 'index.html'));
    if ($v2) {
      $projection = SelectedCreatorCreditProjection::fromStorage(dirname(\Drupal::root()), $intent['source']['artifacts'][0]);
      SelectedCreatorCreditProjection::assertProjection($payload['creator_credit_projection'] ?? NULL, $projection);
      SelectedCreatorCreditProjection::assertProjection($mapping['creator_credit_projection'] ?? NULL, $projection);
      SelectedCreatorCreditProjection::assertHomeAndLogo($record['files'], $projection);
      self::v2Inventory($record, $mapping, $envelope, $intent, (string) $row['customer_id']);
    } else {
      if (count($home) !== 1 || $home[0]['sha256'] !== $intent['source']['artifacts'][0]['sha256'] || $home[0]['bytes'] !== $intent['source']['artifacts'][0]['bytes']) throw new \InvalidArgumentException('source_association_selected_source_changed');
      // Legacy grants remain assetless, without an implicit credit exception.
      foreach ($record['files'] as $file) if (!str_ends_with($file['path'], '.html')) throw new \InvalidArgumentException('source_association_asset_authority_required');
    }
    foreach ($mapping['content_records'] ?? [] as $path => $id) {
      $matches = array_values(array_filter($intent['authored_content']['pages'] ?? [], static fn(array $p): bool => $p['record_id'] === $id));
      $files = array_values(array_filter($record['files'], static fn(array $f): bool => $f['path'] === $path));
      $html = base64_decode($envelope['content_evidence'][$path] ?? '', TRUE);
      if (count($matches) !== 1 || count($files) !== 1 || $html === FALSE || hash('sha256', $html) !== $files[0]['sha256'] || strlen($html) !== $files[0]['bytes']) throw new \InvalidArgumentException('source_association_content_evidence_required');
      $text = $matches[0]['text'];
      $expectedPath = strtolower($text['page_name']) === 'home' ? 'index.html' : strtolower(preg_replace('/ +/', '-', $text['page_name'])) . '.html';
      if ($path !== $expectedPath) throw new \InvalidArgumentException('source_association_content_page_changed');
      $dom = new \DOMDocument(); $prior = libxml_use_internal_errors(TRUE);
      try { $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET); } finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
      $xpath = new \DOMXPath($dom);
      foreach (['title' => '//title', 'heading' => '//main//*[@data-field-type="text" and self::h1]', 'body' => '//main//*[@data-field-type="text" and self::p]', 'description' => '//meta[@name="description"]/@content'] as $field => $query) {
        $nodes = $xpath->query($query);
        if ($nodes->length !== 1 || $nodes->item(0)->textContent !== $text[$field]) throw new \InvalidArgumentException('source_association_completed_content_changed');
      }
    }
    return $mapping;
  }

  private static function v2Inventory(array $record, array $mapping, array $envelope, array $intent, string $customer): void {
    $scope = $intent['scope']['snapshot'];
    $names = array_values(array_filter(array_map('trim', preg_split('/[,\n]/', $scope['page_list'] ?? '')), static fn(string $name): bool => $name !== ''));
    $pages = [];
    foreach ($names as $name) {
      if (!preg_match('/\A[A-Za-z][A-Za-z0-9 -]*\z/', $name)) throw new \InvalidArgumentException('source_association_scope_changed');
      $path = strtolower($name) === 'home' ? 'index.html' : strtolower(preg_replace('/ +/', '-', $name)) . '.html';
      if (isset($pages[$path])) throw new \InvalidArgumentException('source_association_scope_changed');
      $pages[$path] = TRUE;
    }
    if (count($pages) !== (int) ($scope['page_count'] ?? 0) || !isset($pages['index.html']) || ($record['scope']['required_pages'] ?? []) !== array_keys($pages)) throw new \InvalidArgumentException('source_association_scope_changed');
    $present = [];
    foreach ($record['files'] as $file) {
      $path = $file['path'];
      if ($path === SelectedCreatorCreditProjection::ASSET_PATH) continue;
      if (!isset($pages[$path])) throw new \InvalidArgumentException('source_association_asset_authority_required');
      $present[] = $path;
      if ($path === 'index.html') continue;
      $records = array_values(array_filter($intent['authored_content']['pages'] ?? [], static function(array $p) use ($path): bool {
        return strtolower(preg_replace('/ +/', '-', $p['text']['page_name'] ?? '')) . '.html' === $path;
      }));
      if (count($records) !== 1 || (string) ($records[0]['customer_id'] ?? '') !== $customer
        || ($mapping['content_records'][$path] ?? NULL) !== $records[0]['record_id']
        || !isset($envelope['content_evidence'][$path])) throw new \InvalidArgumentException('source_association_content_evidence_required');
    }
    $missing = array_diff(array_keys($pages), $present);
    if (($record['scope_complete'] ?? FALSE) !== !$missing || ($record['issues'] ?? []) !== ($missing ? ['required_pages_incomplete'] : [])) throw new \InvalidArgumentException('source_association_scope_changed');
    $records = array_keys($mapping['content_records'] ?? []); $evidence = array_keys($envelope['content_evidence'] ?? []);
    sort($records); sort($evidence);
    if ($records !== $evidence || array_diff($records, $present)) throw new \InvalidArgumentException('source_association_content_evidence_required');
  }
}
