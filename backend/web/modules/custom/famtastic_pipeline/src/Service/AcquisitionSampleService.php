<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;

/** Reviewed Sample Lab invitations, separate from immutable public-proof delivery. */
final class AcquisitionSampleService {

  private array $registrationIntents = [];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly OperationalLedger $ledger,
    private readonly ?\Drupal\Core\Config\ConfigFactoryInterface $configFactory = NULL,
  ) {}

  /** Staff/tool-only creation. No public issuance endpoint, sending or generation. */
  public function issue(string $key, string $email, int $prospectId, int $campaignId, string $niche, array $recipes, array $bindings, array $qualification, int $expires, string $arm = 'tailored'): array {
    $now = $this->time->getRequestTime();
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $this->ledger->isSuppressed($email) || $prospectId < 1 || $campaignId < 1 || preg_match('/^[a-zA-Z0-9:_-]{8,128}$/D', $key) !== 1 || !in_array($niche, AcquisitionSampleGuard::NICHES, TRUE) || !in_array($arm, ['generic', 'tailored'], TRUE) || $expires <= $now || $expires > $now + 30 * 86400) throw new \InvalidArgumentException('invalid_sample_invitation');
    foreach (['business_verified', 'contact_owned', 'jurisdiction_eligible', 'provider_eligible', 'history_reconciled', 'bindings_verified'] as $gate) {
      if (($qualification[$gate] ?? FALSE) !== TRUE) throw new \InvalidArgumentException('recipient_qualification_required:' . $gate);
    }
    if (empty($qualification['receipt']) || strlen((string) $qualification['receipt']) > 128 || preg_match('/^[a-f0-9]{64}$/D', (string) ($qualification['sha256'] ?? '')) !== 1) throw new \InvalidArgumentException('qualification_receipt_required');
    $prospect = $this->database->select('famtastic_prospect', 'p')->fields('p', ['id', 'public_email', 'campaign'])->condition('id', $prospectId)->execute()->fetchAssoc();
    $campaign = $this->database->select('famtastic_campaign', 'c')->fields('c', ['campaign_key'])->condition('id', $campaignId)->execute()->fetchField();
    if (!$prospect || !hash_equals($this->ledger->contactHash((string) $prospect['public_email']), $this->ledger->contactHash($email)) || !$campaign || !hash_equals((string) $campaign, (string) $prospect['campaign'])) throw new \InvalidArgumentException('recipient_campaign_binding_required');
    if (($qualification['niche_confirmed'] ?? FALSE) !== TRUE || ($qualification['confirmed_niche'] ?? '') !== $niche) throw new \InvalidArgumentException('verified_niche_binding_required');
    $recipes = AcquisitionSampleGuard::recipes($recipes);
    foreach ($recipes as &$recipe) {
      $stored = (string) $recipe['artifact_path'];
      $path = AcquisitionSampleArtifacts::path($stored);
      if (!$path || !is_file($path) || filesize($path) > 262144 || !hash_equals((string) $recipe['sha256'], (string) hash_file('sha256', $path))) throw new \InvalidArgumentException('reviewed_sample_artifact_required');
      $recipe['html_snapshot'] = (string) file_get_contents($path);
    }
    unset($recipe);
    $bindings = AcquisitionSampleGuard::bindings($bindings);
    $evidence = hash('sha256', json_encode([$email, $prospectId, $campaignId, $niche, $arm, $recipes, $bindings, $qualification, $expires], JSON_THROW_ON_ERROR));
    $transaction = $this->database->startTransaction();
    $old = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('invitation_key', $key)->forUpdate()->execute()->fetchAssoc();
    if ($old) {
      if (!hash_equals((string) $old['evidence_hash'], $evidence)) throw new \InvalidArgumentException('invitation_replay_changed');
      return ['id' => (int) $old['id'], 'duplicate' => TRUE, 'token' => NULL];
    }
    $token = bin2hex(random_bytes(32));
    $id = (int) $this->database->insert('famtastic_acquisition_sample')->fields([
      'invitation_key' => $key, 'token_hash' => AcquisitionSampleGuard::tokenHash($token), 'context_id' => bin2hex(random_bytes(16)),
      'recipient_hash' => $this->ledger->contactHash($email), 'campaign_id' => $campaignId, 'prospect_id' => $prospectId,
      'niche' => $niche, 'experiment_arm' => $arm, 'recipe_snapshot' => json_encode($recipes, JSON_THROW_ON_ERROR),
      'bindings' => json_encode($bindings, JSON_THROW_ON_ERROR), 'evidence_hash' => $evidence,
      'qualification_ref' => (string) $qualification['receipt'], 'eligible_at' => $now,
      'expires' => $expires, 'created' => $now, 'changed' => $now,
    ])->execute();
    unset($transaction);
    return ['id' => $id, 'duplicate' => FALSE, 'token' => $token];
  }

  /** Internal preparation from stored supplied facts; confers no send eligibility. */
  public function prepareGeneric(string $key, int $prospectId, int $campaignId, array $recipe, int $expires, bool $internalCandidate = FALSE): array {
    $now = $this->time->getRequestTime();
    if ($prospectId < 1 || preg_match('/^[a-zA-Z0-9:_-]{8,128}$/D', $key) !== 1 || $expires <= $now || $expires > $now + 30 * 86400) throw new \InvalidArgumentException('invalid_sample_preparation');
    $prospect = $this->database->select('famtastic_prospect', 'p')->fields('p', ['id', 'business_name', 'business_category', 'public_email', 'campaign'])->condition('id', $prospectId)->execute()->fetchAssoc();
    if (!$prospect) throw new \InvalidArgumentException('stored_sample_prospect_required');
    if ($campaignId > 0) {
      $campaign = $this->database->select('famtastic_campaign', 'c')->fields('c', ['campaign_key'])->condition('id', $campaignId)->execute()->fetchField();
      if (!$campaign || !hash_equals((string) $campaign, (string) $prospect['campaign'])) throw new \InvalidArgumentException('recipient_campaign_binding_required');
    }
    $suppliedBusinessName = trim((string) $prospect['business_name']);
    $bindings = AcquisitionSampleGuard::bindings(['business_name' => $suppliedBusinessName === '' ? 'Your business' : $suppliedBusinessName]);
    $industry = trim((string) $prospect['business_category']);
    if (mb_strlen($industry) > 255 || preg_match('/[\x00-\x1f<>]/u', $industry)) throw new \InvalidArgumentException('unsafe_supplied_industry');
    // Exact display categories only. Broad or unknown categories stay generic;
    // neither this map nor a supplied category is a qualification assertion.
    $family = match (mb_strtolower($industry)) {
      'beauty_hair', 'hair salon', 'beauty salon', 'beauty', 'beauty services', 'beauty, hair styling & braiding' => 'hair_beauty',
      'barber', 'barber shop' => 'barber',
      'mobile_detailing', 'mobile detailing', 'auto detailing' => 'mobile_detailing',
      'baking_catering', 'bakery', 'catering' => 'baking_catering',
      default => 'general_service',
    };
    $niche = match ($family) { 'hair_beauty', 'barber' => 'beauty_hair', 'general_service' => 'generic', default => $family };
    $recipe = AcquisitionSampleGuard::preparationRecipe($recipe, $internalCandidate);
    $recipeFamily = $recipe['id'] === 'beauty_soft_power_acquisition' ? 'hair_beauty' : ($recipe['industry_family'] ?? '');
    if ($recipeFamily !== $family || ($recipe['niche'] ?? '') !== ($niche === 'generic' ? 'general_service' : $niche)) throw new \InvalidArgumentException('preparation_recipe_industry_mismatch');
    if ($internalCandidate && Settings::get('famtastic_acquisition_internal_preparation', FALSE) !== TRUE) throw new \InvalidArgumentException('internal_sample_preparation_disabled');
    $path = AcquisitionSampleArtifacts::path((string) $recipe['artifact_path']);
    if (!is_file($path) || filesize($path) > 262144 || !hash_equals((string) $recipe['sha256'], (string) hash_file('sha256', $path))) throw new \InvalidArgumentException('reviewed_sample_artifact_required');
    $recipe['html_snapshot'] = (string) file_get_contents($path);
    $email = mb_strtolower(trim((string) $prospect['public_email']));
    $emailAvailable = (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    $context = ['classification' => 'supplied_generic_preparation', 'industry' => $industry, 'business_name_provenance' => $suppliedBusinessName === '' ? 'unknown' : 'stored_supplied', 'industry_provenance' => $industry === '' ? 'unknown' : 'stored_supplied', 'niche_verified' => FALSE, 'owner_name_provenance' => 'unknown', 'contact_ownership' => 'unknown', 'delivery_eligibility' => 'unassessed', 'account_continuation_available' => $emailAvailable, 'recipe_review' => $internalCandidate ? 'internal_candidate_owner_pending' : (($recipe['review']['status'] ?? '') === 'approved_campaign_artifact' ? 'owner_approved_campaign_artifact' : 'approved'), 'component_studio_registration_proved' => ($recipe['recipe_ref']['status'] ?? '') === 'registered'];
    $bindings['_preparation'] = $context;
    $evidence = hash('sha256', json_encode([$prospectId, $campaignId, $email, $niche, $recipe, $bindings, $expires], JSON_THROW_ON_ERROR));
    $transaction = $this->database->startTransaction();
    $old = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('invitation_key', $key)->forUpdate()->execute()->fetchAssoc();
    if ($old) {
      if (!hash_equals((string) $old['evidence_hash'], $evidence)) throw new \InvalidArgumentException('invitation_replay_changed');
      return ['id' => (int) $old['id'], 'duplicate' => TRUE, 'token' => NULL];
    }
    $token = bin2hex(random_bytes(32));
    $id = (int) $this->database->insert('famtastic_acquisition_sample')->fields([
      'invitation_key' => $key, 'token_hash' => AcquisitionSampleGuard::tokenHash($token), 'context_id' => bin2hex(random_bytes(16)),
      'recipient_hash' => $this->ledger->contactHash($emailAvailable ? $email : 'unclaimable-prospect:' . $prospectId), 'campaign_id' => $campaignId > 0 ? $campaignId : NULL, 'prospect_id' => $prospectId,
      'niche' => $niche, 'experiment_arm' => 'generic', 'recipe_snapshot' => json_encode([$recipe], JSON_THROW_ON_ERROR), 'bindings' => json_encode($bindings, JSON_THROW_ON_ERROR), 'evidence_hash' => $evidence,
      'qualification_ref' => '', 'eligible_at' => NULL, 'expires' => $expires, 'created' => $now, 'changed' => $now,
    ])->execute();
    unset($transaction);
    return ['id' => $id, 'duplicate' => FALSE, 'token' => $token];
  }

  /** Explicit internal delivery-evidence transition; never stages or sends. */
  public function authorizeGeneric(int $invitationId, array $authorization, string $signature): array {
    $secret = (string) getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');
    $json = json_encode($authorization, JSON_THROW_ON_ERROR);
    if (strlen($secret) < 32 || !hash_equals(hash_hmac('sha256', $json, $secret), $signature)) throw new \InvalidArgumentException('acquisition_owner_signature_invalid');
    $transaction = $this->database->startTransaction();
    $sample = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitationId)->forUpdate()->execute()->fetchAssoc();
    $context = $sample ? json_decode((string) $sample['bindings'], TRUE) : [];
    if (!$sample || ($context['_preparation']['classification'] ?? '') !== 'supplied_generic_preparation' || !AcquisitionSampleGuard::live($sample, $this->time->getRequestTime())) throw new \InvalidArgumentException('generic_prepared_invitation_required');
    $prospectEmail = $this->database->select('famtastic_prospect', 'p')->fields('p', ['public_email'])->condition('id', (int) $sample['prospect_id'])->execute()->fetchField();
    if (!filter_var($prospectEmail, FILTER_VALIDATE_EMAIL) || !hash_equals($sample['recipient_hash'], $this->ledger->contactHash((string) $prospectEmail)) || $this->ledger->isSuppressed((string) $prospectEmail)) throw new \InvalidArgumentException('sample_recipient_unavailable');
    AcquisitionSampleGuard::genericAuthorization($authorization, $sample, AcquisitionSampleGuard::senderAccount($this->configFactory), $this->time->getRequestTime());
    $hash = hash('sha256', $json);
    if (!empty($sample['eligible_at'])) {
      if (!hash_equals($sample['qualification_ref'], $hash)) throw new \InvalidArgumentException('generic_authorization_replay_changed');
      return ['invitation_id' => $invitationId, 'authorization_hash' => $hash, 'duplicate' => TRUE, 'staged' => FALSE];
    }
    $this->ledger->recordEvent('acquisition:generic:authorization:' . $invitationId . ':' . $hash, 'acquisition.generic_authorized', ['authorization' => $authorization, 'signature' => $signature], (int) $sample['prospect_id'], (int) $sample['campaign_id']);
    $this->database->update('famtastic_acquisition_sample')->fields(['qualification_ref' => $hash, 'eligible_at' => $this->time->getRequestTime()])->condition('id', $invitationId)->isNull('eligible_at')->execute();
    unset($transaction);
    return ['invitation_id' => $invitationId, 'authorization_hash' => $hash, 'duplicate' => FALSE, 'staged' => FALSE];
  }

  /** Strictly read-only: scanners cannot register, prefer, claim or create jobs. */
  public function resolve(string $token): ?array {
    $row = $this->liveRow($token);
    if (!$row) return NULL;
    $recipes = json_decode((string) $row['recipe_snapshot'], TRUE, 32, JSON_THROW_ON_ERROR);
    $bindings = json_decode((string) $row['bindings'], TRUE, 32, JSON_THROW_ON_ERROR);
    $context = $bindings['_preparation'] ?? NULL;
    unset($bindings['_preparation']);
    return [
      'context_classification' => $context['classification'] ?? 'verified_outreach_sample',
      'industry' => $context['industry'] ?? NULL, 'context_provenance' => $context,
      'schema' => 'famtastic.acquisition-sample.v1', 'illustrative' => TRUE,
      'niche' => $row['niche'], 'business_name' => $bindings['business_name'], 'bindings' => $bindings,
      'recipes' => array_map(static fn(array $recipe): array => [
        'id' => $recipe['id'], 'version' => (int) $recipe['version'], 'title' => (string) ($recipe['title'] ?? $recipe['id']),
        'summary' => (string) ($recipe['summary'] ?? ''), 'sha256' => $recipe['sha256'],
        'preview_path' => '/web/api/acquisition/samples/' . $token . '/preview/' . $recipe['id'],
      ], $recipes),
      'preference' => $row['preferred_recipe'] ?: NULL,
      'registration_path' => '/login?mode=register&sample_continuation=' . $token,
    ];
  }

  public function preview(string $token, string $recipeId): ?string {
    $row = $this->liveRow($token);
    if (!$row) return NULL;
    foreach (json_decode((string) $row['recipe_snapshot'], TRUE, 32, JSON_THROW_ON_ERROR) as $recipe) {
      if ($recipe['id'] !== $recipeId) continue;
      $html = (string) ($recipe['html_snapshot'] ?? '');
      if ($html === '' || !hash_equals((string) $recipe['sha256'], hash('sha256', $html))) throw new \RuntimeException('sample_artifact_unavailable');
      $bindings = json_decode((string) $row['bindings'], TRUE, 32, JSON_THROW_ON_ERROR);
      unset($bindings['_preparation']);
      return AcquisitionSampleGuard::render($html, $bindings);
    }
    return NULL;
  }

  /** Preference is deliberately unrelated to ProofCampaign/checkout selection. */
  public function preference(string $token, string $recipeId): array {
    $transaction = $this->database->startTransaction();
    $row = $this->liveRow($token, TRUE);
    if (!$row) throw new \InvalidArgumentException('sample_not_found');
    $ids = array_column(json_decode((string) $row['recipe_snapshot'], TRUE, 32, JSON_THROW_ON_ERROR), 'id');
    if (!in_array($recipeId, $ids, TRUE)) throw new \InvalidArgumentException('recipe_not_in_invitation');
    if ($row['preferred_recipe'] !== $recipeId) {
      $now = $this->time->getRequestTime();
      $this->database->update('famtastic_acquisition_sample')->fields(['preferred_recipe' => $recipeId, 'changed' => $now])->condition('id', (int) $row['id'])->execute();
      $this->ledger->recordEvent('acquisition:sample:' . $row['id'] . ':preference:' . $recipeId, 'acquisition.sample_preference', ['invitation_id' => (int) $row['id'], 'recipe_id' => $recipeId, 'niche' => $row['niche'], 'formal_proof_selection' => FALSE], (int) $row['prospect_id'], (int) $row['campaign_id']);
    }
    unset($transaction);
    return ['recipe_id' => $recipeId, 'formal_proof_selection' => FALSE];
  }

  /** Validates intent BEFORE user save/hook; durable pending binding follows save. */
  public function beginRegistration(string $email, string $token): bool {
    if ($token === '') return FALSE;
    $row = $this->liveRow($token);
    if (!$row || !hash_equals((string) $row['recipient_hash'], $this->ledger->contactHash($email))) throw new \InvalidArgumentException('sample_continuation_invalid');
    $this->registrationIntents[$this->ledger->contactHash($email)] = (int) $row['id'];
    return TRUE;
  }

  public function hasRegistration(string $email): bool {
    return isset($this->registrationIntents[$this->ledger->contactHash($email)]);
  }

  public function endRegistration(string $email): void {
    unset($this->registrationIntents[$this->ledger->contactHash($email)]);
  }

  public function attachPending(string $email, int $customerId): void {
    $id = $this->registrationIntents[$this->ledger->contactHash($email)] ?? NULL;
    if (!$id) return;
    $customer = $this->customer($customerId);
    if (!$customer || !hash_equals($this->ledger->contactHash((string) $customer['email']), $this->ledger->contactHash($email))) throw new \InvalidArgumentException('sample_customer_mismatch');
    $this->database->update('famtastic_acquisition_sample')->fields(['pending_customer_id' => $customerId, 'changed' => $this->time->getRequestTime()])->condition('id', $id)->condition('recipient_hash', $this->ledger->contactHash($email))->isNull('customer_id')->isNull('revoked_at')->condition('expires', $this->time->getRequestTime(), '>')->execute();
  }

  /** Verified same-email ownership, across devices; replay cannot duplicate claims. */
  public function claim(int $customerId, ?string $token = NULL): ?array {
    $customer = $this->customer($customerId);
    if (!$customer || empty($customer['verified_at'])) throw new \InvalidArgumentException('verification_required');
    $hash = $this->ledger->contactHash((string) $customer['email']);
    $transaction = $this->database->startTransaction();
    $query = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('recipient_hash', $hash)->forUpdate();
    if ($token !== NULL) $query->condition('token_hash', AcquisitionSampleGuard::tokenHash($token))->isNull('revoked_at')->condition('expires', $this->time->getRequestTime(), '>');
    else $query->condition('pending_customer_id', $customerId);
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if ($token !== NULL && !$rows) throw new \InvalidArgumentException('sample_not_found');
    foreach ($rows as $row) {
      if (!empty($row['customer_id']) && (int) $row['customer_id'] !== $customerId) throw new \InvalidArgumentException('sample_customer_mismatch');
      $this->database->update('famtastic_acquisition_sample')->fields(['customer_id' => $customerId, 'pending_customer_id' => $customerId, 'changed' => $this->time->getRequestTime()])->condition('id', (int) $row['id'])->execute();
      $this->ledger->recordEvent('acquisition:sample:' . $row['id'] . ':claimed', 'acquisition.sample_claimed', ['invitation_id' => (int) $row['id'], 'customer_id' => $customerId, 'niche' => $row['niche']], (int) $row['prospect_id'], (int) $row['campaign_id']);
    }
    unset($transaction);
    return $this->continuation($customerId);
  }

  /** Durable, verified-account-only routing. Never disclose invitation bearer. */
  public function continuation(int $customerId): ?array {
    $customer = $this->customer($customerId);
    if (!$customer || empty($customer['verified_at'])) return NULL;
    // Saved account context survives public-link expiry/revocation. It grants no
    // sample/proof access and carries no token, checkout or approval authority.
    $row = $this->database->select('famtastic_acquisition_sample', 's')->fields('s', ['id', 'context_id', 'niche', 'preferred_recipe', 'bindings'])->condition('customer_id', $customerId)->condition('recipient_hash', $this->ledger->contactHash((string) $customer['email']))->orderBy('changed', 'DESC')->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) return NULL;
    $mapping = $this->database->select('famtastic_acquisition_request', 'm')->fields('m', ['request_id'])->condition('invitation_id', (int) $row['id'])->condition('customer_id', $customerId)->execute()->fetchField();
    $requestPublic = $mapping ? $this->database->select('famtastic_project_request', 'r')->fields('r', ['public_id'])->condition('id', $mapping)->condition('customer_id', $customerId)->execute()->fetchField() : NULL;
    return $this->knownContext($row) + ['kind' => 'acquisition_sample', 'context_id' => $row['context_id'], 'request_public_id' => $requestPublic ?: NULL, 'niche' => $row['niche'], 'recipe_id' => $row['preferred_recipe'] ?: NULL, 'return_path' => AcquisitionSampleGuard::RETURN_PATH];
  }

  /** Server-owned intake context for an explicit request, never formal selection. */
  public function requestContext(int $customerId, string $contextId): ?array {
    if ($contextId === '') return NULL;
    $customer = $this->customer($customerId);
    if (!$customer || empty($customer['verified_at']) || preg_match('/^[a-f0-9]{32}$/D', $contextId) !== 1) throw new \InvalidArgumentException('sample_request_context_invalid');
    $row = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('context_id', $contextId)->condition('customer_id', $customerId)->condition('recipient_hash', $this->ledger->contactHash((string) $customer['email']))->execute()->fetchAssoc();
    if (!$row) throw new \InvalidArgumentException('sample_request_context_invalid');
    return $this->knownContext($row) + ['context_id' => $contextId, 'invitation_id' => (int) $row['id'], 'niche' => $row['niche'], 'preferred_recipe' => $row['preferred_recipe'] ?: NULL, 'formal_proof_selection' => FALSE, 'source_campaign_id' => (int) $row['campaign_id']];
  }

  public function associatedRequest(int $customerId, string $contextId): ?int {
    $context = $this->requestContext($customerId, $contextId);
    if (!$context) return NULL;
    $id = $this->database->select('famtastic_acquisition_request', 'm')->fields('m', ['request_id'])->condition('invitation_id', $context['invitation_id'])->condition('customer_id', $customerId)->execute()->fetchField();
    return $id ? (int) $id : NULL;
  }

  public function attachRequest(int $customerId, string $contextId, int $requestId, int $prospectId): void {
    $context = $this->requestContext($customerId, $contextId);
    if (!$context) return;
    $request = $this->database->select('famtastic_project_request', 'r')->fields('r', ['customer_id', 'prospect_id'])->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$request || (int) $request['customer_id'] !== $customerId || (int) $request['prospect_id'] !== $prospectId) throw new \InvalidArgumentException('sample_request_binding_invalid');
    if ($existing = $this->associatedRequest($customerId, $contextId)) {
      if ($existing !== $requestId) throw new \InvalidArgumentException('sample_context_already_attached');
      return;
    }
    $this->database->insert('famtastic_acquisition_request')->fields(['request_id' => $requestId, 'invitation_id' => $context['invitation_id'], 'customer_id' => $customerId, 'request_prospect_id' => $prospectId, 'created' => $this->time->getRequestTime()])->execute();
  }

  public function revoke(int $id): void {
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_acquisition_sample')->fields(['revoked_at' => $now, 'changed' => $now])->condition('id', $id)->isNull('revoked_at')->execute();
    if ($this->database->schema()->tableExists('famtastic_acquisition_sequence')) $this->database->update('famtastic_acquisition_sequence')->fields(['status' => 'stopped', 'stop_reason' => 'revoked', 'stopped_at' => $now, 'changed' => $now])->condition('invitation_id', $id)->condition('status', ['held', 'active'], 'IN')->execute();
  }

  /** Expose known facts only through verified account continuation/intake. */
  private function knownContext(array $row): array {
    $bindings = json_decode((string) $row['bindings'], TRUE, 32, JSON_THROW_ON_ERROR);
    $context = $bindings['_preparation'] ?? NULL;
    return ['context_classification' => $context['classification'] ?? 'verified_outreach_sample', 'known_information' => ['business_name' => ($context['business_name_provenance'] ?? '') === 'unknown' ? '' : $bindings['business_name'], 'industry' => $context['industry'] ?? '', 'business_category' => $context['industry'] ?? ''], 'context_provenance' => $context];
  }

  private function customer(int $id): ?array {
    return $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $id)->execute()->fetchAssoc() ?: NULL;
  }

  private function liveRow(string $token, bool $lock = FALSE): ?array {
    try { $hash = AcquisitionSampleGuard::tokenHash($token); }
    catch (\InvalidArgumentException) { return NULL; }
    $query = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('token_hash', $hash)->isNull('revoked_at')->condition('expires', $this->time->getRequestTime(), '>');
    if ($lock) $query->forUpdate();
    return $query->execute()->fetchAssoc() ?: NULL;
  }

}
