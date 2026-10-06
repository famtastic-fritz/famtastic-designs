<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Validates frozen, reviewed sample input; contains no generation or dispatch. */
final class AcquisitionSampleGuard {

  public const NICHES = ['beauty_hair', 'mobile_detailing', 'baking_catering'];
  public const RETURN_PATH = '/portal?start=website&section=projects';

  public static function tokenHash(string $token): string {
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw new \InvalidArgumentException('sample_not_found');
    return hash('sha256', $token);
  }

  /** Bind only verified public facts. Private contact/claims are never inferred. */
  public static function bindings(array $input): array {
    $observation = $input['research_observation'] ?? NULL;
    unset($input['research_observation']);
    $allowed = ['business_name', 'recipient_name', 'locality', 'phone', 'booking_url', 'inquiry_url'];
    if (array_diff(array_keys($input), $allowed)) throw new \InvalidArgumentException('unsupported_sample_binding');
    $result = [];
    foreach ($allowed as $key) {
      $value = trim((string) ($input[$key] ?? ''));
      if (mb_strlen($value) > 300 || preg_match('/[\x00-\x1f<>]/u', $value) || str_contains($value, '{{')) throw new \InvalidArgumentException('unsafe_sample_binding');
      if ($key === 'recipient_name' && $value !== '' && (str_contains($value, '@') || strpbrk($value, '\\/:{}[]=') !== FALSE)) throw new \InvalidArgumentException('verified_recipient_name_required');
      if ($key === 'phone' && $value !== '' && (!preg_match('/^[+0-9(). \-]{7,40}$/D', $value) || strlen(preg_replace('/\D/', '', $value)) < 7 || strlen(preg_replace('/\D/', '', $value)) > 15)) throw new \InvalidArgumentException('verified_phone_binding_required');
      if (str_ends_with($key, '_url') && $value !== '') {
        $parts = parse_url($value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || !empty($parts['query']) || filter_var($parts['host'], FILTER_VALIDATE_IP) || !str_contains($parts['host'], '.') || preg_match('/(?:^|\.)(?:localhost|local|internal|lan|invalid)$/iD', $parts['host']) || in_array(strtolower($parts['host']), ['accounts.google.com'], TRUE)) throw new \InvalidArgumentException('unsafe_sample_url');
      }
      $result[$key] = $value;
    }
    if ($observation !== NULL) {
      if (!is_array($observation) || ($observation['owner_approved'] ?? FALSE) !== TRUE || ($observation['classification'] ?? '') !== 'verified_public_fact' || !is_numeric($observation['checked_at'] ?? NULL) || (int) $observation['checked_at'] < 1 || empty($observation['text']) || mb_strlen((string) $observation['text']) > 500 || preg_match('/[\x00-\x1f<>]/u', (string) $observation['text'])) throw new \InvalidArgumentException('reviewed_public_observation_required');
      self::bindings(['business_name' => 'Observation source', 'inquiry_url' => (string) ($observation['source_url'] ?? '')]);
      if (empty($observation['source_url'])) throw new \InvalidArgumentException('observation_source_required');
      $result['research_observation'] = $observation;
    }
    if ($result['business_name'] === '') throw new \InvalidArgumentException('business_name_required');
    return $result;
  }

  /** Two references only; Component Studio remains the owner of recipes. */
  public static function recipes(array $recipes): array {
    if (count($recipes) !== 2) throw new \InvalidArgumentException('two_reviewed_samples_required');
    $ids = [];
    foreach ($recipes as $recipe) {
      if (preg_match('/^[a-z0-9_-]{3,96}$/D', (string) ($recipe['id'] ?? '')) !== 1 || isset($ids[$recipe['id']]) || (int) ($recipe['version'] ?? 0) < 1 || ($recipe['review']['status'] ?? '') !== 'approved' || empty($recipe['review']['reviewer']) || empty($recipe['review']['receipt']) || ($recipe['recipe_ref']['owner'] ?? '') !== 'component-studio' || empty($recipe['recipe_ref']['id']) || empty($recipe['recipe_ref']['version']) || preg_match('/^[a-f0-9]{64}$/D', (string) ($recipe['sha256'] ?? '')) !== 1 || empty($recipe['artifact_path'])) throw new \InvalidArgumentException('reviewed_recipe_evidence_required');
      if (($recipe['recipe_ref']['status'] ?? '') !== 'registered') throw new \InvalidArgumentException('component_recipe_registration_required');
      $ids[$recipe['id']] = TRUE;
    }
    return array_values($recipes);
  }

  /** A single illustrative recipe, with an explicit internal candidate boundary. */
  public static function preparationRecipe(array $recipe, bool $internalCandidate): array {
    if (preg_match('/^[a-z0-9_-]{3,96}$/D', (string) ($recipe['id'] ?? '')) !== 1 || (int) ($recipe['version'] ?? 0) < 1 || ($recipe['recipe_ref']['owner'] ?? '') !== 'component-studio' || empty($recipe['recipe_ref']['id']) || empty($recipe['recipe_ref']['version']) || preg_match('/^[a-f0-9]{64}$/D', (string) ($recipe['sha256'] ?? '')) !== 1 || empty($recipe['artifact_path'])) throw new \InvalidArgumentException('preparation_recipe_evidence_required');
    if ($internalCandidate) {
      if (($recipe['review']['status'] ?? '') !== 'candidate' || ($recipe['recipe_ref']['status'] ?? '') !== 'import_request_pending' || $recipe['id'] !== 'beauty_soft_power_acquisition' || $recipe['recipe_ref']['id'] !== $recipe['id'] || (int) $recipe['recipe_ref']['version'] !== (int) $recipe['version'] || $recipe['artifact_path'] !== 'marketing/campaigns/acquisition-199/generic-review/beauty-template.html') throw new \InvalidArgumentException('internal_candidate_recipe_required');
    }
    elseif (($recipe['review']['status'] ?? '') === 'approved_campaign_artifact') {
      $record = self::creativeApproval((array) ($recipe['review']['approval_record'] ?? []));
      if ($recipe['id'] !== 'beauty_soft_power_acquisition' || $recipe['artifact_path'] !== 'marketing/campaigns/acquisition-199/generic-review/beauty-template.html' || $recipe['sha256'] !== ($record['artifact_sha256'][$recipe['artifact_path']] ?? '') || !in_array($recipe['recipe_ref']['status'] ?? '', ['import_request_pending', 'registered'], TRUE) || $recipe['recipe_ref']['id'] !== $recipe['id'] || (int) $recipe['recipe_ref']['version'] !== (int) $recipe['version']) throw new \InvalidArgumentException('generic_approved_recipe_binding_required');
    }
    elseif (($recipe['review']['status'] ?? '') !== 'approved' || empty($recipe['review']['reviewer']) || empty($recipe['review']['receipt']) || ($recipe['recipe_ref']['status'] ?? '') !== 'registered') throw new \InvalidArgumentException('reviewed_recipe_evidence_required');
    return $recipe;
  }

  /** Non-secret identity of the one existing SMTP account; never includes password. */
  public static function senderAccount(?\Drupal\Core\Config\ConfigFactoryInterface $factory): array {
    if (!$factory) throw new \InvalidArgumentException('acquisition_native_account_required');
    $smtp = $factory->get('smtp.settings');
    $account = ['host' => strtolower(trim((string) $smtp->get('smtp_host'))), 'port' => (int) $smtp->get('smtp_port'), 'username' => trim((string) $smtp->get('smtp_username')), 'protocol' => (string) $smtp->get('smtp_protocol'), 'from' => trim((string) ($smtp->get('smtp_from') ?: $smtp->get('smtp_username') ?: $factory->get('famtastic_pipeline.settings')->get('support_from_email')))];
    if (!$smtp->get('smtp_on') || !$account['host'] || $account['port'] < 1 || $account['port'] > 65535 || !$account['username'] || !filter_var($account['from'], FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('acquisition_native_account_required');
    return ['transport' => 'native_smtp', 'provider' => 'godaddy_cpanel', 'from' => $account['from'], 'account_sha256' => hash('sha256', json_encode($account, JSON_THROW_ON_ERROR))];
  }

  /** Verify owner-approved campaign bytes without claiming registry registration. */
  public static function creativeApproval(array $receipt, ?string $frozen = NULL): array {
    if (($receipt['reference'] ?? '') !== 'docs/research/acquisition-199/CREATIVE-APPROVAL.json' || preg_match('/^[a-f0-9]{64}$/D', (string) ($receipt['sha256'] ?? '')) !== 1) throw new \InvalidArgumentException('generic_creative_approval_required');
    $bytes = $frozen ?? (string) file_get_contents(AcquisitionSampleArtifacts::path($receipt['reference']));
    if (!hash_equals($receipt['sha256'], hash('sha256', $bytes))) throw new \InvalidArgumentException('generic_creative_approval_changed');
    $record = json_decode($bytes, TRUE, 32, JSON_THROW_ON_ERROR);
    if (($record['schema'] ?? '') !== 'famtastic.acquisition-creative-approval.v1' || ($record['status'] ?? '') !== 'approved' || ($record['approved_by'] ?? '') !== 'Fritz Medine') throw new \InvalidArgumentException('generic_creative_approval_required');
    return $record;
  }

  /** Row/account-bound evidence; supplied identity is deliberately not a gate. */
  public static function genericAuthorization(array $authorization, array $sample, array $account, int $now, ?string $frozenCreative = NULL): void {
    if (($authorization['schema'] ?? '') !== 'famtastic.acquisition-generic-authorization.v1' || (int) ($authorization['issued_at'] ?? 0) > $now || (int) ($authorization['issued_at'] ?? 0) < $now - 3600 || (int) ($authorization['expires'] ?? 0) <= $now || (int) $authorization['expires'] > $now + 3600 || ($authorization['sender'] ?? []) !== $account) throw new \InvalidArgumentException('generic_fresh_sender_authorization_required');
    $binding = ['invitation_id' => (int) $sample['id'], 'prospect_id' => (int) $sample['prospect_id'], 'campaign_id' => (int) $sample['campaign_id'], 'recipient_hash' => (string) $sample['recipient_hash'], 'invitation_evidence_hash' => (string) $sample['evidence_hash'], 'account_sha256' => $account['account_sha256'], 'from' => $account['from']];
    if (($authorization['binding'] ?? []) !== $binding || $binding['prospect_id'] < 1 || $binding['campaign_id'] < 1) throw new \InvalidArgumentException('generic_exact_row_binding_required');
    $basis = self::authorizationBasis($authorization);
    $permissionKey = $basis === 'owner_authorized_cold_outreach' ? 'owner_authorization_receipt' : 'provider_permission_receipt';
    foreach ([$permissionKey, 'history_receipt'] as $key) {
      $receipt = $authorization[$key] ?? [];
      if (($receipt['status'] ?? '') !== 'owner_reviewed' || empty($receipt['reference']) || preg_match('/^[a-f0-9]{64}$/D', (string) ($receipt['sha256'] ?? '')) !== 1 || ($receipt['binding'] ?? []) !== $binding || (int) ($receipt['checked_at'] ?? 0) > $now || (int) ($receipt['checked_at'] ?? 0) < $now - 3600) throw new \InvalidArgumentException('generic_row_receipt_required:' . $key);
    }
    if ($basis === 'owner_authorized_cold_outreach') {
      if (($authorization['owner_authorization_receipt']['approved_by'] ?? '') !== 'Fritz Medine') throw new \InvalidArgumentException('generic_actual_owner_authorization_required');
      $ownerReceipt = $authorization['owner_authorization_receipt'];
      $ownerBytes = $authorization['owner_authorization_record'] ?? '';
      if ($ownerReceipt['reference'] !== 'docs/research/acquisition-199/OWNER-COLD-SEND-AUTHORIZATION.json' || !is_string($ownerBytes) || strlen($ownerBytes) > 32768 || !hash_equals($ownerReceipt['sha256'], hash('sha256', $ownerBytes))) throw new \InvalidArgumentException('generic_owner_authorization_artifact_required');
      $ownerRecord = json_decode($ownerBytes, TRUE, 32, JSON_THROW_ON_ERROR);
      if (($ownerRecord['schema'] ?? '') !== 'famtastic.acquisition-owner-cold-send-authorization.v1' || ($ownerRecord['status'] ?? '') !== 'authorized' || ($ownerRecord['approved_by'] ?? '') !== 'Fritz Medine' || ($ownerRecord['customer_send_authorized'] ?? FALSE) !== TRUE || ($ownerRecord['provider'] ?? '') !== $account['provider'] || ($ownerRecord['sender'] ?? '') !== $account['from'] || self::authorizationBasis($ownerRecord) !== $basis) throw new \InvalidArgumentException('generic_owner_authorization_artifact_binding_required');
    }
    else {
      $permission = $authorization['provider_permission_receipt'];
      if (($permission['provider'] ?? '') !== $account['provider'] || ($permission['policy'] ?? '') !== 'opt_in_only' || ($permission['permitted_use'] ?? FALSE) !== TRUE || empty($permission['written_opt_in_reference']) || preg_match('/^[a-f0-9]{64}$/D', (string) ($permission['written_opt_in_sha256'] ?? '')) !== 1) throw new \InvalidArgumentException('generic_actual_sender_use_required');
    }
    if (($authorization['history_receipt']['classification'] ?? '') !== 'actual_native_history_reconciled' || ($authorization['history_receipt']['coverage_complete'] ?? FALSE) !== TRUE || ($authorization['history_receipt']['eligible_for_new_outreach'] ?? FALSE) !== TRUE || ($authorization['history_receipt']['known_stop_reasons'] ?? NULL) !== []) throw new \InvalidArgumentException('generic_actual_history_required');
    $creative = self::creativeApproval((array) ($authorization['creative_approval'] ?? []), $frozenCreative);
    $recipes = json_decode((string) $sample['recipe_snapshot'], TRUE, 32, JSON_THROW_ON_ERROR);
    if (count($recipes) !== 1 || $recipes[0]['id'] !== 'beauty_soft_power_acquisition' || $recipes[0]['sha256'] !== ($creative['artifact_sha256'][$recipes[0]['artifact_path']] ?? '') || !hash_equals($recipes[0]['sha256'], hash('sha256', (string) $recipes[0]['html_snapshot']))) throw new \InvalidArgumentException('generic_approved_recipe_binding_required');
    $approvedContent = $creative['content_id'] ?? 'acquisition-199:beauty_soft_power_generic_d0:v1';
    if (!in_array($approvedContent, ['acquisition-199:beauty_soft_power_generic_d0:v1', 'acquisition-199:beauty_soft_power_generic_d0:v2'], TRUE) || ($authorization['content_id'] ?? '') !== $approvedContent) throw new \InvalidArgumentException('generic_approved_content_identity_required');
  }

  /** Owner intent is distinct from recipient consent and provider permission. */
  public static function authorizationBasis(array $value): string {
    $basis = $value['authorization_basis'] ?? 'provider_permitted_opt_in';
    if ($basis === 'owner_authorized_cold_outreach') {
      if (($value['provider_policy_conflict'] ?? NULL) !== TRUE || ($value['recipient_opt_in'] ?? NULL) !== FALSE || ($value['provider_permission_proved'] ?? NULL) !== FALSE || array_key_exists('provider_permission_receipt', $value)) throw new \InvalidArgumentException('acquisition_cold_basis_truth_required');
    }
    elseif ($basis !== 'provider_permitted_opt_in' || array_key_exists('owner_authorization_receipt', $value) || array_key_exists('owner_authorization_record', $value)) throw new \InvalidArgumentException('acquisition_authorization_basis_invalid');
    return $basis;
  }

  public static function live(array $row, int $now): bool {
    return empty($row['revoked_at']) && (int) ($row['expires'] ?? 0) > $now;
  }

  /** Strict replacement, including attribute contexts. Never execute bindings. */
  public static function render(string $html, array $bindings): string {
    $verified = self::bindings($bindings);
    // The shared contact paragraphs are optional. Unknown contact facts must
    // not become blank links or imply an approved business contact path.
    $optionalParagraphs = [
      'phone' => '<p>Contact number: {{phone}}</p>',
      'booking_url' => '<p><a href="{{booking_url}}" rel="noreferrer">Approved booking path</a></p>',
      'inquiry_url' => '<p><a href="{{inquiry_url}}" rel="noreferrer">Approved inquiry path</a></p>',
    ];
    foreach ($optionalParagraphs as $key => $paragraph) {
      if ($verified[$key] === '') $html = str_replace($paragraph, '', $html);
    }
    $replace = [];
    foreach ($verified as $key => $value) if (is_string($value)) $replace['{{' . $key . '}}'] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = strtr($html, $replace);
    if (preg_match('/\{\{[^}]+\}\}/', $html)) throw new \RuntimeException('unknown_sample_placeholder');
    return $html;
  }

}
