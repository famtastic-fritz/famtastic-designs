<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Dependency-free producer contract; consumes recorded facts, never guesses. */
final class SelectedStagingContinuation {

  /** The real portal producer and dependency-free transport proof share this serializer. */
  public static function createPacket(array $row, string $projectId, string $direction, array $artifacts, string $sourcePath, string $previewUrl, string $dnaJson, array $contact, int $variantId, int $revision, string $createdAt): array {
    $selected = array_values(array_filter($artifacts, static fn(array $a): bool => $a['path'] === $sourcePath && $a['role'] === 'selected_preview'));
    if (count($selected) !== 1) throw new \InvalidArgumentException('Exactly one selected source artifact is required.');
    $source = $selected[0];
    $packetId = 'staging-packet:request:' . (int) $row['id'] . ':revision:' . $revision;
    $packet = [
      'schema' => 'famtastic.site-studio.build-packet.v1',
      'packet_id' => $packetId, 'idempotency_key' => $packetId,
      'request_id' => (string) $row['public_id'], 'project_id' => $projectId,
      'build_class' => 'prepayment_selected_direction_staging',
      'selected_direction_ids' => ['direction-' . $direction],
      'artifacts' => $artifacts,
      'artifact_manifest_sha256' => SiteStudioBuildPacketService::artifactManifestDigest($artifacts),
      'selected_artifacts' => [[
        'direction_id' => 'direction-' . $direction, 'source_artifact_path' => $sourcePath,
        'source_artifact_sha256' => $source['sha256'], 'source_artifact_bytes' => $source['bytes'],
      ]],
      'source_preview_url' => $previewUrl, 'design_dna_sha256' => hash('sha256', $dnaJson),
      'created_at' => $createdAt,
    ];
    $dna = json_decode($dnaJson, TRUE, 512, JSON_THROW_ON_ERROR);
    return self::attach($packet, $row, $contact, $dna, $variantId, $revision, $createdAt);
  }

  public static function attach(array $packet, array $request, array $contact, array $dna, int $variantId, int $revision, string $selectedAt): array {
    $evidence = $dna['selected_build_continuation'] ?? NULL;
    if (!is_array($evidence)) {
      throw new \InvalidArgumentException('selected_continuation_evidence_required: record agreed scope, complete files, rights, design contract and research provenance on the selected proof.');
    }
    foreach (['spec', 'required_pages', 'files', 'source', 'selection', 'brand', 'research_packet_ref'] as $field) {
      if (!is_array($evidence[$field] ?? NULL) || !$evidence[$field]) {
        throw new \InvalidArgumentException('selected_continuation_evidence_required: ' . $field);
      }
    }
    if (($evidence['spec']['capability_class'] ?? '') !== 'static' || !empty($evidence['spec']['backend']) || !empty($evidence['spec']['functional_contract'])) {
      throw new \InvalidArgumentException('unsupported_scope: selected staging currently requires an explicitly scoped complete static site.');
    }
    if (empty($evidence['spec']['site_needs']) || empty($evidence['brand']['design_contract']) || empty($evidence['research_packet_ref']['packet_id']) || empty($evidence['research_packet_ref']['brief_hash'])) {
      throw new \InvalidArgumentException('selected_continuation_evidence_required: scope, design or research facts are incomplete.');
    }
    if (!in_array($evidence['operation'] ?? '', ['package_existing', 'continue_build'], TRUE) || !in_array($evidence['initiating_system'] ?? '', ['designs', 'studio'], TRUE) || empty($evidence['correlation_id']) || ($evidence['requested_next_action'] ?? '') !== 'protected_review') {
      throw new \InvalidArgumentException('selected_continuation_operation_required: distinguish packaging from unfinished build work and retain initiation correlation.');
    }
    $target = $evidence['hosting_target'] ?? [];
    $targetUrl = parse_url((string) ($target['staging_url'] ?? ''));
    $host = strtolower((string) ($targetUrl['host'] ?? ''));
    if (($targetUrl['scheme'] ?? '') !== 'https' || !($host === 'famtasticinc.com' || str_ends_with($host, '.famtasticinc.com')) || isset($targetUrl['user']) || isset($targetUrl['pass']) || isset($targetUrl['query']) || isset($targetUrl['fragment']) || empty($target['target_path']) || empty($target['remote_subdirectory']) || str_contains($target['remote_subdirectory'], '..') || str_starts_with($target['remote_subdirectory'], '/')) {
      throw new \InvalidArgumentException('selected_continuation_review_target_required');
    }
    $paths = [];
    foreach ($evidence['files'] as $file) {
      $path = (string) ($file['path'] ?? '');
      $url = parse_url((string) ($file['url'] ?? ''));
      if (!preg_match('#^(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-][a-zA-Z0-9_.-]*\.(?:html|css|js|png|jpg|jpeg|webp|svg|ico|woff2|txt)$#', $path) || str_contains($path, '..') || isset($paths[$path])) {
        throw new \InvalidArgumentException('selected_continuation_unsafe_public_path');
      }
      if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
        throw new \InvalidArgumentException('selected_continuation_unsafe_artifact_url');
      }
      $matches = array_filter($packet['artifacts'], static fn(array $a): bool => $a['path'] === ($file['source_path'] ?? '') && $a['role'] !== 'render_evidence');
      if (count($matches) !== 1 || ($file['rights']['status'] ?? '') !== 'approved' || empty($file['rights']['evidence_ref'])) {
        throw new \InvalidArgumentException('selected_continuation_source_or_rights_missing');
      }
      $paths[$path] = TRUE;
    }
    $recipePaths = in_array(($evidence['recipe']['id'] ?? ''), ['selected-html-slots-v1', 'legacy-shared-shell-v1'], TRUE) ? array_column($evidence['recipe']['steps'] ?? [], 'path') : [];
    foreach ($evidence['required_pages'] as $page) {
      if (!is_string($page) || !str_ends_with($page, '.html') || (!isset($paths[$page]) && !in_array($page, $recipePaths, TRUE))) {
        throw new \InvalidArgumentException('unsupported_scope: required page is not materialized in selected packet.');
      }
    }
    if (!in_array($packet['selected_artifacts'][0]['source_artifact_path'], array_column($evidence['files'], 'source_path'), TRUE)) {
      throw new \InvalidArgumentException('selected_continuation_selected_source_missing');
    }
    if ($revision < 1 || (int) ($request['customer_id'] ?? 0) < 1 || (int) ($request['id'] ?? 0) < 1 || empty($contact['display_name']) || empty($contact['email'])) {
      throw new \InvalidArgumentException('selected_continuation_account_binding_required');
    }
    $evidence['schema_version'] = 1;
    $evidence['customer'] = ['id' => (string) $request['customer_id'], 'name' => $contact['display_name'], 'email' => $contact['email']];
    $evidence['website_request_id'] = (int) $request['id'];
    $evidence['selection_revision'] = $revision;
    $evidence['current_selected_sha256'] = $packet['selected_artifacts'][0]['source_artifact_sha256'];
    $evidence['source']['proof_campaign_id'] = (string) $request['proof_campaign_id'];
    $evidence['selection']['proof_variant_id'] = (string) $variantId;
    $evidence['selection']['proof_version'] = (string) ($evidence['selection']['proof_version'] ?? '');
    $evidence['selection']['approved_at'] = $selectedAt;
    $evidence['origin'] = 'legit';
    $packet['continuation'] = $evidence;
    return $packet;
  }

  /** Explicit local reconciliation: same legacy packet source/tenant, newly recorded evidence. */
  public static function reconcileLegacy(array $legacy, array $request, array $contact, array $dna, string $evidenceRef): array {
    if (isset($legacy['continuation']) || $evidenceRef === '' || (string) ($legacy['request_id'] ?? '') !== (string) ($request['public_id'] ?? '') || (string) ($legacy['project_id'] ?? '') !== (string) ($request['project_id'] ?? '') || count($legacy['selected_artifacts'] ?? []) !== 1) {
      throw new \InvalidArgumentException('legacy_reconciliation_binding_required');
    }
    $packet = $legacy;
    $packet['packet_id'] = 'staging-packet:request:' . (int) $request['id'] . ':revision:1';
    $packet['idempotency_key'] = $packet['packet_id'];
    $packet = self::attach($packet, $request, $contact, $dna, (int) ($dna['selected_build_continuation']['selection']['proof_variant_id'] ?? 0), 1, (string) ($dna['selected_build_continuation']['selection']['approved_at'] ?? ''));
    if ((int) $packet['continuation']['selection']['proof_variant_id'] < 1 || $packet['continuation']['selection']['approved_at'] === '') throw new \InvalidArgumentException('legacy_reconciliation_selection_evidence_required');
    $packet['legacy_upgrade'] = ['previous_packet_sha256' => hash('sha256', json_encode($legacy, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'evidence_ref' => $evidenceRef];
    return $packet;
  }

  /** A new revision must preserve authoritative owner/project/request identity. */
  public static function assertSuccessor(array $current, array $next, ?int $recordedIntentRevision = NULL): void {
    foreach (['request_id', 'project_id'] as $field) {
      if (empty($current[$field]) || (string) $current[$field] !== (string) ($next[$field] ?? '')) throw new \InvalidArgumentException('Selected packet tenant binding changed: ' . $field);
    }
    if (!isset($current['continuation'])) {
      if (($next['continuation']['selection_revision'] ?? NULL) !== 1 || empty($next['legacy_upgrade']['evidence_ref']) || !hash_equals(hash('sha256', json_encode($current, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), (string) ($next['legacy_upgrade']['previous_packet_sha256'] ?? '')) || ($current['selected_artifacts'] ?? NULL) !== ($next['selected_artifacts'] ?? NULL) || ($current['artifact_manifest_sha256'] ?? '') !== ($next['artifact_manifest_sha256'] ?? '')) throw new \InvalidArgumentException('Explicit legacy reconciliation evidence is required.');
      return;
    }
    foreach (['website_request_id'] as $field) {
      if (empty($current['continuation'][$field]) || $current['continuation'][$field] !== ($next['continuation'][$field] ?? NULL)) throw new \InvalidArgumentException('Selected packet request binding changed.');
    }
    if (empty($current['continuation']['customer']['id']) || $current['continuation']['customer']['id'] !== ($next['continuation']['customer']['id'] ?? NULL)) throw new \InvalidArgumentException('Selected packet account binding changed.');
    $nextRevision = $next['continuation']['selection_revision'] ?? NULL;
    $currentRevision = $current['continuation']['selection_revision'] ?? 0;
    $knownIntent = $recordedIntentRevision !== NULL && $nextRevision === $recordedIntentRevision && $nextRevision > $currentRevision;
    if (!is_int($nextRevision) || (!$knownIntent && $nextRevision !== $currentRevision + 1) || $current['packet_id'] === $next['packet_id'] || $current['idempotency_key'] === $next['idempotency_key']) throw new \InvalidArgumentException('Selected packet revision must be the next immutable revision.');
  }
}
