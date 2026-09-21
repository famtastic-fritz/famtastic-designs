<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;
require_once __DIR__ . '/SelectedCreatorCreditProjection.php';

/** Derives internal recipe/permission records from normal saved inputs. */
final class SelectedRecordResolver {
  private static function issue(string $code): never { throw new \InvalidArgumentException('selected_continuation_' . $code); }
  public static function resolve(array $row, array $dna, array $intent, array $artifacts, array $installation, string $storageRoot, ?array $mapping = NULL): array {
    $completed = $mapping ? SelectedFinalizedSource::validate($mapping['source_export'], $mapping) : NULL;
    $capture = $dna['source_capture'] ?? [];
    $selected = $artifacts[0];
    $directory = dirname($storageRoot . '/' . $selected['path']);
    $rawName = $capture['raw_callback_file'] ?? '';
    if ($completed && !$capture) {
      $capture = ['event_id' => $completed['provenance']['packet_id'], 'raw_callback_sha256' => $completed['provenance']['brief_hash']];
    } else {
      if (!preg_match('/^source-callback-[a-f0-9]{64}\.json$/', $rawName)) self::issue('raw_source_capture_missing');
      $raw = @file_get_contents($directory . '/' . $rawName);
      if ($raw === FALSE || hash('sha256', $raw) !== ($capture['raw_callback_sha256'] ?? '') || $selected['sha256'] !== ($capture['selected_sha256'] ?? '')) self::issue('raw_source_capture_changed');
    }
    $html = file_get_contents($storageRoot . '/' . $selected['path']);
    if (hash('sha256', $html) !== $selected['sha256']) self::issue('selected_source_changed');
    $target = $installation['targets'][(string) $row['project_id']] ?? NULL;
    if (!is_array($target) || (string) ($target['customer_id'] ?? '') !== (string) $row['customer_id']) self::issue('review_target_binding_missing');
    $policy = $installation['authored_shell_policy'] ?? [];
    if (($policy['status'] ?? '') !== 'approved' || empty($policy['evidence_ref'])) self::issue('shell_rights_binding_missing');
    $sourceAssets = [];
    foreach (ProofAssetContract::normalizeStoredManifest($dna['asset_manifest'] ?? []) as $asset) {
      $sourceAssets['assets/' . $asset['relative_path']] = ['artifact' => ['path' => $asset['artifact_path'], 'sha256' => $asset['sha256'], 'bytes' => $asset['size_bytes']],
        'rights' => SelectedAssetRights::bind($row, $intent['asset_authority']['records'], $asset)];
    }
    if (count($artifacts) !== 1 + count($sourceAssets)) self::issue('asset_rights_bindings_required');
    $intake = json_decode((string) $row['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    foreach (['required_features', 'integrations', 'booking_details', 'ecommerce_details', 'custom_needs'] as $field) if (trim((string) ($intake[$field] ?? '')) !== '') self::issue('unsupported_feature_' . $field);
    if ($intent['requested_changes']) self::issue('revision_requires_named_page_content');
    $names = array_values(array_filter(array_map('trim', preg_split('/[,\n]/', (string) ($intake['page_list'] ?? '')))));
    if (!$names || count($names) !== (int) ($intake['page_count'] ?? 0)) self::issue('page_scope_incomplete');
    $pages = []; foreach ($names as $name) {
      if (!preg_match('/^[A-Za-z][A-Za-z0-9 -]*$/', $name)) self::issue('page_name_unsupported');
      $path = strtolower($name) === 'home' ? 'index.html' : strtolower(preg_replace('/ +/', '-', $name)) . '.html';
      if (isset($pages[$path])) self::issue('page_scope_ambiguous');
      $pages[$path] = $name;
    }
    if (!isset($pages['index.html'])) self::issue('selected_home_scope_missing');
    $existing = ['index.html' => $selected];
    foreach ($sourceAssets as $path => $asset) $existing[$path] = $asset['artifact'];
    $credit = NULL;
    if ($completed) {
      $home = array_values(array_filter($completed['files'], static fn(array $f): bool => $f['path'] === 'index.html'));
      if (count($home) !== 1) self::issue('source_changed_requires_edit_recipe');
      $logo = array_filter($completed['files'], static fn(array $f): bool => $f['path'] === SelectedCreatorCreditProjection::ASSET_PATH);
      if ($home[0]['sha256'] !== $selected['sha256'] || $home[0]['bytes'] !== $selected['bytes'] || $logo || isset($mapping['creator_credit_projection'])) {
        // V1-associated sources never acquire a new asset/derivative capability.
        if (!empty($mapping['association_id']) && !isset($mapping['creator_credit_projection'])) self::issue('source_changed_requires_edit_recipe');
        foreach (['project_id', 'customer_id', 'request_id'] as $key) {
          $expected = $key === 'request_id' ? (string) $row['public_id'] : (string) $row[$key];
          if (($mapping[$key] ?? '') !== $expected || ($intent[$key] ?? '') !== $expected) self::issue('source_mapping_identity_mismatch');
        }
        $credit = SelectedCreatorCreditProjection::fromStorage($storageRoot, $selected);
        if (isset($mapping['creator_credit_projection'])) SelectedCreatorCreditProjection::assertProjection($mapping['creator_credit_projection'], $credit);
        SelectedCreatorCreditProjection::assertHomeAndLogo($completed['files'], $credit);
        if (isset($sourceAssets[SelectedCreatorCreditProjection::ASSET_PATH])) self::issue('system_asset_authority_conflict');
      }
      foreach ($completed['files'] as $file) {
        if (!isset($pages[$file['path']]) && !isset($sourceAssets[$file['path']]) && !($credit && $file['path'] === SelectedCreatorCreditProjection::ASSET_PATH)) self::issue('existing_page_removal_requires_edit_recipe');
        $existing[$file['path']] = $file;
      }
    }
    $missing = array_diff_key($pages, $existing);
    if ($missing) {
    $dom = new \DOMDocument(); $prior = libxml_use_internal_errors(TRUE);
    try { $dom->loadHTML($html, LIBXML_NONET); } finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    $xpath = new \DOMXPath($dom);
    $components = $xpath->query('//main/*[@data-section-type="intro" or @data-section-type="hero"]');
    if ($components->length !== 1) self::issue('text_component_ambiguous');
    $component = $components->item(0); $componentId = $component->getAttribute('data-section-id');
    $heading = $xpath->query('.//h1[@data-field-type="text"]', $component); $body = $xpath->query('.//p[@data-field-type="text"]', $component);
    if ($heading->length !== 1 || $body->length !== 1 || $xpath->query('.//*[@data-field-type="text"]', $component)->length !== 2) self::issue('text_component_fields_unsupported');
    foreach ([$componentId, $heading->item(0)->getAttribute('data-field-id'), $body->item(0)->getAttribute('data-field-id')] as $id) if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $id)) self::issue('text_component_field_identity_missing');
    }
    $design = ['schema_version' => 1, 'kind' => 'selected-source-preservation-v1', 'source_sha256' => $selected['sha256'],
      'preservation' => 'exact-source-and-marked-shell', 'asset_policy' => ['preserve' => TRUE, 'rights_safe_only' => TRUE]];
    $base = rtrim((string) ($installation['artifact_base_url'] ?? ''), '/');
    if (!str_starts_with($base, 'https://')) self::issue('artifact_reader_unconfigured');
    $url = static fn(string $hash): string => $base . '/api/site-studio/selected-artifacts/' . rawurlencode((string) $row['public_id']) . '/' . $intent['selection']['revision'] . '/' . $hash;
    $steps = []; $changes = [];
    $files = [['path' => 'index.html', 'source_path' => $selected['path'], 'url' => $url($selected['sha256']), 'rights' => $policy]];
    foreach ($sourceAssets as $path => $asset) $files[] = ['path' => $path, 'source_path' => $asset['artifact']['path'], 'url' => $url($asset['artifact']['sha256']), 'rights' => $asset['rights']];
    foreach ($existing as $path => $file) {
      if (isset($sourceAssets[$path])) continue;
      if ($credit && $path === SelectedCreatorCreditProjection::ASSET_PATH) {
        $sourcePath = 'next-source/' . $completed['run_id'] . '/' . $path;
        $artifacts[] = ['role' => 'source_material', 'path' => $sourcePath, 'sha256' => $file['sha256'], 'bytes' => $file['bytes']];
        $files[] = ['path' => $path, 'source_path' => $sourcePath, 'source_origin' => 'mapped_repository',
          'rights' => ['status' => 'approved', 'scope' => 'creator_attribution_only', 'evidence_ref' => 'owner-creator-credit:' . $credit['policy_sha256'], 'publication_authorized' => FALSE]];
        continue;
      }
      $saved = array_values(array_filter($intake['authored_content']['pages'] ?? [], static fn(array $p): bool => strtolower($p['text']['page_name']) === strtolower($pages[$path])));
      if ($saved && ($mapping['content_records'][$path] ?? '') !== $saved[0]['record_id']) self::issue('existing_page_copy_requires_edit_recipe:' . $path);
      if (!$saved && isset($mapping['content_records'][$path])) self::issue('existing_page_copy_removed_requires_review:' . $path);
      if ($path === 'index.html') continue;
      $sourcePath = 'next-source/' . $completed['run_id'] . '/' . $path;
      $artifacts[] = ['role' => 'source_material', 'path' => $sourcePath, 'sha256' => $file['sha256'], 'bytes' => $file['bytes']];
      $files[] = ['path' => $path, 'source_path' => $sourcePath, 'source_origin' => 'mapped_repository', 'rights' => $policy];
    }
    $write = static function(array $record) use (&$artifacts, $directory, $storageRoot, $url): array {
      $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $hash = hash('sha256', $json); $absolute = $directory . '/record-' . $hash . '.json';
      if (is_file($absolute) && file_get_contents($absolute) !== $json) self::issue('record_collision');
      if (!is_file($absolute)) { file_put_contents($absolute, $json, LOCK_EX); chmod($absolute, 0600); }
      $path = substr($absolute, strlen($storageRoot) + 1);
      $artifacts[] = ['role' => 'source_material', 'path' => $path, 'sha256' => $hash, 'bytes' => strlen($json)];
      return ['path' => $path, 'sha256' => $hash, 'url' => $url($hash)];
    };
    foreach ($missing as $path => $name) {
      $matches = array_values(array_filter($intake['authored_content']['pages'] ?? [], static fn(array $p): bool => strtolower($p['text']['page_name']) === strtolower($name) && (int) $p['customer_id'] === (int) $row['customer_id']));
      if (count($matches) !== 1) self::issue('authored_copy_missing_' . $path);
      $authored = $matches[0]; $copy = $authored['text'];
      foreach (['title', 'heading', 'body'] as $field) if (trim($copy[$field] ?? '') === '') self::issue('authored_copy_missing_' . $path . ':' . $field);
      if ($xpath->query('//head/meta[@name="description"]')->length && trim($copy['description'] ?? '') === '') self::issue('authored_copy_missing_' . $path . ':description');
      $fields = ['head/title' => $copy['title'], $componentId . '/' . $heading->item(0)->getAttribute('data-field-id') => $copy['heading'], $componentId . '/' . $body->item(0)->getAttribute('data-field-id') => $copy['body']];
      if ($xpath->query('//head/meta[@name="description"]')->length) $fields['head/description'] = $copy['description'];
      $content = ['schema' => 'famtastic.authored-page-content.v1', 'record_id' => $authored['record_id'], 'revision' => $intent['selection']['revision'], 'output_path' => $path, 'selected_sha256' => $selected['sha256'], 'fields' => $fields];
      $c = $write($content);
      $permission = ['schema' => 'famtastic.page-transformation-permission.v1', 'status' => 'approved', 'action' => 'text_substitution',
        'authority_ref' => $intent['intent_id'] . ':' . $authored['record_id'], 'content_record_id' => $content['record_id'], 'content_revision' => $content['revision'], 'content_sha256' => $c['sha256'],
        'selected_sha256' => $selected['sha256'], 'template_sha256' => $selected['sha256'], 'output_path' => $path, 'reuse_shared_shell' => TRUE, 'component_ids' => [$componentId], 'fields' => array_keys($fields)];
      $p = $write($permission); $change = 'requested-page:' . $path; $changes[] = ['id' => $change, 'source' => $authored['record_id']];
      $steps[] = ['stage' => 'assemble_selected_shell', 'path' => $path, 'selected_source_path' => $selected['path'], 'component_ids' => [$componentId],
        'template_provenance' => 'selected-shell-derivation.v1', 'template_source_path' => $selected['path'], 'template_sha256' => $selected['sha256'], 'template_url' => $url($selected['sha256']),
        'content_source_path' => $c['path'], 'content_sha256' => $c['sha256'], 'content_url' => $c['url'], 'permission_source_path' => $p['path'], 'permission_sha256' => $p['sha256'], 'permission_url' => $p['url'],
        'design_contract_sha256' => hash('sha256', json_encode($design, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'rights' => $policy, 'resolves_change_ids' => [$change]];
    }
    return ['artifacts' => $artifacts, 'continuation' => ['operation' => $steps ? 'continue_build' : 'package_existing', 'initiating_system' => $mapping['handoff_initiator'] ?? $mapping['originating_system'] ?? 'designs', 'correlation_id' => $intent['intent_id'], 'requested_next_action' => 'protected_review', ...($mapping ? ['source_export_sha256' => $mapping['source_export_sha256']] : []),
      'spec' => ['capability_class' => 'static', 'site_needs' => ['pages' => array_keys($pages)]], 'required_pages' => array_keys($pages),
      'files' => $files,
      'source' => ['campaign_id' => (string) $row['proof_campaign_id']], 'selection' => ['direction_name' => $dna['direction_name'] ?? $intent['selection']['direction_id'], 'proof_version' => (string) $intent['selection']['revision'], 'approval_id' => $intent['intent_id']],
      'brand' => ['design_contract' => $design], 'research_packet_ref' => ['packet_id' => $capture['event_id'], 'brief_hash' => $capture['raw_callback_sha256'], 'source_adapter' => 'authenticated_callback_capture'],
      'hosting_target' => $target, 'requested_changes' => $changes, ...($steps ? ['recipe' => ['id' => 'legacy-shared-shell-v1', 'steps' => $steps]] : [])]];
  }
}
