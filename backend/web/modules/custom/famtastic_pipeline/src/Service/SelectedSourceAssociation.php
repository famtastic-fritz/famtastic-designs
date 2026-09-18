<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Canonical agency selection authority for first Studio source association. */
final class SelectedSourceAssociation {
  public static function binding(array $row, array $studio): array {
    $intent = $studio['selected_source_intent'] ?? [];
    if (($row['proof_review_status'] ?? '') !== 'selected' || !empty($row['commerce_order_id'])
      || ($intent['request_id'] ?? '') !== (string) $row['public_id'] || ($intent['project_id'] ?? '') !== (string) $row['project_id']
      || ($intent['customer_id'] ?? '') !== (string) $row['customer_id'] || ($intent['selection']['direction_id'] ?? '') !== ($row['selected_proof_direction'] ?? '')) throw new \InvalidArgumentException('source_association_current_selection_required');
    return $intent;
  }
  public static function issue(array $row, array $studio, string $secret, int $now): array {
    if ($secret === '') throw new \InvalidArgumentException('source_association_authentication_required');
    $intent = self::binding($row, $studio);
    $payload = ['schema' => 'famtastic.source-association.v1', 'association_id' => bin2hex(random_bytes(16)),
      'operation' => 'associate_verified_source', 'audience' => 'site-studio-next', 'issued_at' => $now, 'expires_at' => $now + 3600,
      'project_id' => (string) $row['project_id'], 'customer_id' => (string) $row['customer_id'], 'request_id' => (string) $row['public_id'],
      'selection' => $intent['selection'], 'scope_sha256' => $intent['scope']['snapshot_sha256'],
      'intent_sha256' => hash('sha256', json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'intent' => $intent];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return ['payload_json' => $json, 'signature' => hash_hmac('sha256', "famtastic.source-association.v1\n" . $json, $secret)];
  }
  public static function accept(array $row, array $studio, array $envelope, int $now): array {
    $grant = $envelope['association'] ?? [];
    try { $payload = json_decode((string) ($grant['payload_json'] ?? ''), TRUE, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new \InvalidArgumentException('source_association_payload_invalid'); }
    if (!is_array($payload)) throw new \InvalidArgumentException('source_association_payload_invalid');
    $stored = $studio['source_association_grants'][$payload['association_id'] ?? ''] ?? NULL;
    if (!$stored || $stored !== $grant || ($payload['operation'] ?? '') !== 'associate_verified_source' || ($payload['audience'] ?? '') !== 'site-studio-next') throw new \InvalidArgumentException('source_association_grant_unknown');
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
    if (($mapping['association_id'] ?? '') !== $payload['association_id'] || ($mapping['originating_system'] ?? '') !== 'studio' || ($mapping['handoff_initiator'] ?? '') !== 'studio') throw new \InvalidArgumentException('source_association_origin_invalid');
    $record = SelectedFinalizedSource::validate($envelope['source_export'] ?? [], $mapping);
    if (($record['run_id'] ?? '') !== ($mapping['run_id'] ?? '') || ($record['scope']['request_scope_sha256'] ?? '') !== $payload['scope_sha256']
      || ($record['scope']['evidence_ref'] ?? '') !== 'association:' . $payload['association_id'] || ($record['review_qa']['source_binding']['site_id'] ?? '') !== $record['site_id']
      || ($record['review_qa']['source_binding']['run_id'] ?? '') !== $record['run_id'] || !empty($record['review_qa']['problems'])
      || array_diff($record['issues'] ?? [], ['required_pages_incomplete'])) throw new \InvalidArgumentException('source_association_scope_changed');
    if (($mapping['source_export'] ?? NULL) !== $envelope['source_export'] || ($record['review_qa']['passed'] ?? FALSE) !== TRUE || ($record['review_qa']['source_binding']['manifest_sha256'] ?? '') !== $record['manifest_sha256'] || !empty($record['use_restrictions'])) throw new \InvalidArgumentException('source_association_verification_required');
    $home = array_values(array_filter($record['files'], static fn(array $f): bool => $f['path'] === 'index.html'));
    if (count($home) !== 1 || $home[0]['sha256'] !== $intent['source']['artifacts'][0]['sha256'] || $home[0]['bytes'] !== $intent['source']['artifacts'][0]['bytes']) throw new \InvalidArgumentException('source_association_selected_source_changed');
    // First association is deliberately assetless; private references use their
    // separately verified record/receipt path, never this grant as a license.
    foreach ($record['files'] as $file) if (!str_ends_with($file['path'], '.html')) throw new \InvalidArgumentException('source_association_asset_authority_required');
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
}
