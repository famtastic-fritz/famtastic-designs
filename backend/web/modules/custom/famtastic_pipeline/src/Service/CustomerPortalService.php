<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\UserInterface;
use Drupal\famtastic_pipeline\Entity\Order;

/**
 * Owns durable customer identity and organization-scoped portal data.
 */
final class CustomerPortalService {

  /**
   * Request-scoped, already-validated public-preview registration intents.
   *
   * Drupal invokes hook_user_insert() during the user save in the same
   * request. The hook needs this small, non-persistent handoff to avoid
   * treating a public-preview signup as a fully submitted website request.
   * Durable preview history is still recorded by PublicPreviewDeliveryService
   * and is claimed only after email verification.
   *
   * @var array<string, array{public_id:string, continuation:string}>
   */
  private array $pendingPreviewRegistrations = [];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly OperationalLedger $ledger,
    private readonly AttributionService $attribution,
    private readonly PublicPreviewDeliveryService $previews,
  ) {}

  public function customerForUid(int $uid): ?array {
    $row = $this->database->select('famtastic_customer', 'c')
      ->fields('c')->condition('uid', $uid)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  /** Returns one durable customer record by its internal identifier. */
  public function customerForId(int $customerId): ?array {
    $row = $this->database->select('famtastic_customer', 'c')
      ->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function customerForEmail(string $email): ?array {
    $row = $this->database->select('famtastic_customer', 'c')->fields('c')
      ->condition('email', mb_strtolower(trim($email)))->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  /**
   * Starts the one-request registration handoff for a valid public preview.
   *
   * The continuation has to be validated before User::save(), because
   * hook_user_insert() is what otherwise turns same-email discovery notes
   * into a submitted website request and queues the generic proof job. This
   * method deliberately mirrors the acceptance checks in
   * PublicPreviewDeliveryService::markSignupStarted(); it does not claim the
   * preview, create a request, or change its delivery state.
   */
  public function beginPublicPreviewRegistration(string $email, string $continuation): ?string {
    $email = mb_strtolower(trim($email));
    [$publicId, $signature] = array_pad(explode('.', trim($continuation), 2), 2, '');
    $publicId = mb_strtolower(trim($publicId));
    $signature = trim($signature);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
      || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1
      || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
      return NULL;
    }

    $delivery = $this->database->select('famtastic_preview_delivery', 'p')->fields('p')
      ->condition('public_id', $publicId)->range(0, 1)->execute()->fetchAssoc();
    $expectedSignature = $delivery
      ? hash_hmac('sha256', 'public-preview-continuation-v1|' . (string) $delivery['public_id'], Settings::getHashSalt())
      : '';
    if (!$delivery
      || !hash_equals($expectedSignature, $signature)
      || !hash_equals((string) $delivery['recipient_hash'], $this->ledger->contactHash($email))
      || in_array((string) $delivery['state'], ['share_revoked', 'expired'], TRUE)) {
      return NULL;
    }

    $this->pendingPreviewRegistrations[$this->previewRegistrationKey($email)] = [
      'public_id' => (string) $delivery['public_id'],
      'continuation' => (string) $delivery['public_id'] . '.' . $signature,
    ];
    // This is a non-sensitive, non-advancing audit event. The delivery still
    // has no customer_id until markVerified() runs after token verification.
    $this->previews->markSignupStarted((string) $delivery['public_id'] . '.' . $signature, $email);
    return (string) $delivery['public_id'];
  }

  /** True only during the controller request that supplied a valid handoff. */
  public function hasPendingPublicPreviewRegistration(string $email): bool {
    return isset($this->pendingPreviewRegistrations[$this->previewRegistrationKey($email)]);
  }

  /** Clears the request-scoped hook handoff after registration returns. */
  public function endPublicPreviewRegistration(string $email): void {
    unset($this->pendingPreviewRegistrations[$this->previewRegistrationKey($email)]);
  }

  /** Returns a claimed preview's research snapshot to its verified owner only. */
  public function previewResearchForCustomer(int $customerId, string $previewDelivery): ?array {
    return $this->previews->researchForVerifiedCustomer($customerId, $previewDelivery);
  }

  public function createCustomer(UserInterface $user, array $input = []): array {
    if ($existing = $this->customerForUid((int) $user->id())) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $email = mb_strtolower(trim($user->getEmail()));
    $this->previews->markSignupStarted((string) ($input['preview_continuation'] ?? ''), $email);
    $prospectId = $this->findProspect($email);
    $customerId = (int) $this->database->insert('famtastic_customer')->fields([
      'public_id' => $this->uuid->generate(), 'uid' => (int) $user->id(),
      'prospect_id' => $prospectId,
      'display_name' => trim((string) ($input['name'] ?? $user->getDisplayName())) ?: $email,
      'email' => $email, 'phone' => trim((string) ($input['phone'] ?? '')),
      'acquisition_source' => preg_replace('/[^a-z0-9_.-]/', '', strtolower((string) ($input['source'] ?? 'direct'))) ?: 'direct',
      'marketing_status' => !empty($input['marketing_opt_out']) ? 'unsubscribed' : 'subscribed',
      'created' => $now, 'changed' => $now,
    ])->execute();
    $business = trim((string) ($input['business_name'] ?? ''));
    $organizationId = (int) $this->database->insert('famtastic_organization')->fields([
      'public_id' => $this->uuid->generate(), 'type' => $business === '' ? 'individual' : 'business',
      'name' => $business ?: (trim((string) ($input['name'] ?? '')) ?: $email),
      'status' => 'active', 'created' => $now, 'changed' => $now,
    ])->execute();
    $this->database->insert('famtastic_membership')->fields([
      'organization_id' => $organizationId, 'customer_id' => $customerId,
      'role' => 'owner', 'status' => 'active', 'created' => $now, 'changed' => $now,
    ])->execute();
    if ($prospectId) {
      $this->claimResource($organizationId, 'prospect', $prospectId);
      $this->claimProspectResources($organizationId, $prospectId);
      $orderStorage = $this->entities->getStorage('famtastic_order');
      $paidOrderIds = $orderStorage->getQuery()->accessCheck(FALSE)
        ->condition('prospect_ref', $prospectId)->condition('payment_status', 'paid')->execute();
      foreach ($orderStorage->loadMultiple($paidOrderIds) as $paidOrder) {
        $this->syncPaidOrder($paidOrder);
      }
    }
    $this->activity($organizationId, 'account.created', 'Your FAMtastic customer workspace was created.');
    return $this->customerForUid((int) $user->id()) ?? [];
  }

  public function markVerified(int $customerId): void {
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_customer')->fields(['verified_at' => $now, 'changed' => $now])
      ->condition('id', $customerId)->execute();
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c', ['email'])
      ->condition('id', $customerId)->execute()->fetchAssoc();
    if ($customer) {
      $this->previews->claimVerifiedCustomer($customerId, (string) $customer['email']);
    }
  }

  /** Idempotently claims same-email previews for an already verified account. */
  public function claimPreviewsForVerifiedCustomer(int $customerId): void {
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c', ['email', 'verified_at'])
      ->condition('id', $customerId)->range(0, 1)->execute()->fetchAssoc();
    if (!$customer || empty($customer['verified_at'])) {
      return;
    }
    $this->previews->claimVerifiedCustomer($customerId, (string) $customer['email']);
  }

  /**
   * Claims a verified paid order and grants its durable service entitlements.
   */
  public function syncPaidOrder(Order $order): void {
    $prospectId = (int) $order->get('prospect_ref')->target_id;
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')
      ->condition('prospect_id', $prospectId)->execute()->fetchAssoc();
    if (!$customer) return;
    $organizations = $this->organizations((int) $customer['id']);
    if (!$organizations) return;
    $organizationId = (int) $organizations[0]['id'];
    $this->claimResource($organizationId, 'order', (int) $order->id());
    $projectIds = $this->entities->getStorage('famtastic_project')->getQuery()->accessCheck(FALSE)
      ->condition('prospect_ref', $prospectId)->execute();
    foreach ($projectIds as $projectId) $this->claimResource($organizationId, 'project', (int) $projectId);
    $package = (string) $order->get('package')->value;
    if (!in_array($package, ['essential_199', 'business_499'], TRUE)) {
      $this->activity($organizationId, 'purchase.fulfilled', 'Your add-on purchase was verified and added to your order history.');
      return;
    }
    $now = $this->time->getRequestTime();
    $includedUntil = strtotime('+1 year', $now);
    foreach ([
      ['website_service', 0, 'none'],
      ['hosting', 999, 'month'],
    ] as [$type, $amount, $interval]) {
      $exists = $this->database->select('famtastic_entitlement', 'e')->condition('organization_id', $organizationId)
        ->condition('order_id', (int) $order->id())->condition('entitlement_type', $type)->countQuery()->execute()->fetchField();
      if (!$exists) {
        $this->database->insert('famtastic_entitlement')->fields([
          'public_id' => $this->uuid->generate(), 'organization_id' => $organizationId,
          'order_id' => (int) $order->id(), 'entitlement_type' => $type, 'status' => 'active',
          'starts_at' => $now, 'included_until' => $includedUntil,
          'renews_at' => $type === 'hosting' ? $includedUntil : NULL,
          'amount_minor' => $amount, 'billing_interval' => $interval,
          'created' => $now, 'changed' => $now,
        ])->execute();
      }
    }
    $this->activity($organizationId, 'purchase.fulfilled', 'Your purchase was verified and added to your services.');
  }

  public function organizations(int $customerId): array {
    $query = $this->database->select('famtastic_membership', 'm');
    $query->join('famtastic_organization', 'o', 'o.id = m.organization_id');
    $query->fields('o', ['id', 'public_id', 'type', 'name', 'status']);
    $query->addField('m', 'role');
    $query->condition('m.customer_id', $customerId)->condition('m.status', 'active');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function workspace(int $customerId, ?string $organizationPublicId = NULL): array {
    $organizations = $this->organizations($customerId);
    $organization = $organizationPublicId ? NULL : ($organizations[0] ?? NULL);
    if ($organizationPublicId) {
      foreach ($organizations as $candidate) {
        if (hash_equals($candidate['public_id'], $organizationPublicId)) {
          $organization = $candidate;
          break;
        }
      }
    }
    if (!$organization) {
      throw new \RuntimeException('No active customer workspace exists.');
    }
    $organizationId = (int) $organization['id'];
    $resourceIds = function (string $type) use ($organizationId): array {
      return $this->database->select('famtastic_customer_resource', 'r')->fields('r', ['resource_id'])
        ->condition('organization_id', $organizationId)->condition('resource_type', $type)
        ->execute()->fetchCol();
    };
    $orders = $this->serializeEntities('famtastic_order', $resourceIds('order'), [
      'uuid', 'label', 'package', 'amount', 'currency', 'payment_status', 'paid_at', 'created',
    ]);
    foreach ($this->entities->getStorage('commerce_order')->loadMultiple($resourceIds('commerce_order')) as $commerceOrder) {
      $orders[] = [
        'uuid' => $commerceOrder->uuid(),
        'label' => 'Order ' . $commerceOrder->getOrderNumber(),
        'package' => implode(', ', array_map(static fn($item): string => $item->getTitle(), $commerceOrder->getItems())),
        'amount' => (int) round((float) $commerceOrder->getTotalPrice()->getNumber() * 100),
        'currency' => strtolower($commerceOrder->getTotalPrice()->getCurrencyCode()),
        'payment_status' => $commerceOrder->getState()->value === 'completed' ? 'paid' : $commerceOrder->getState()->value,
        'paid_at' => $commerceOrder->getPlacedTime(),
        'created' => $commerceOrder->getCreatedTime(),
        'source' => 'commerce',
      ];
    }
    $projects = $this->serializeEntities('famtastic_project', $resourceIds('project'), [
      'uuid', 'label', 'proof_url', 'live_url', 'delivery_status', 'approval_status', 'revision_count', 'revision_limit', 'created', 'changed',
    ]);
    $projectEntities = array_values($this->entities->getStorage('famtastic_project')->loadMultiple($resourceIds('project')));
    foreach ($projects as $index => &$projectRow) {
      $project = $projectEntities[$index] ?? NULL;
      $projectRow['proofs'] = $project ? $this->projectProofs($project) : NULL;
    }
    unset($projectRow);
    $entitlements = $this->database->select('famtastic_entitlement', 'e')->fields('e')
      ->condition('organization_id', $organizationId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $threadQuery = $this->database->select('famtastic_portal_thread', 't');
    $threadQuery->leftJoin('famtastic_support_case', 's', 's.thread_id = t.id');
    $threadQuery->fields('t', ['public_id', 'project_id', 'kind', 'subject', 'status', 'created', 'changed'])
      ->condition('t.organization_id', $organizationId)->orderBy('t.changed', 'DESC');
    foreach (['case_number', 'category', 'priority', 'response_due', 'resolved_at'] as $field) $threadQuery->addField('s', $field, $field);
    $threadQuery->addField('s', 'status', 'case_status');
    $threads = $threadQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $activity = $this->database->select('famtastic_portal_activity', 'a')
      ->fields('a', ['event_type', 'summary', 'metadata', 'created'])
      ->condition('organization_id', $organizationId)->orderBy('created', 'DESC')->range(0, 20)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $memberQuery = $this->database->select('famtastic_membership', 'm');
    $memberQuery->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $memberQuery->fields('m', ['role', 'status', 'created'])->fields('c', ['public_id', 'display_name', 'email']);
    $memberQuery->condition('m.organization_id', $organizationId);
    $members = $memberQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $analytics = array_values(array_filter($entitlements, fn(array $e): bool => $e['entitlement_type'] === 'customer_analytics' && $e['status'] === 'active'));
    return [
      'organization' => array_diff_key($organization, ['id' => TRUE]),
      'organizations' => array_map(fn(array $o): array => array_diff_key($o, ['id' => TRUE]), $organizations),
      'orders' => $orders, 'projects' => $projects, 'entitlements' => $entitlements,
      'website_requests' => $this->websiteRequests($customerId, $organizationId),
      'threads' => $threads, 'activity' => $activity, 'members' => $members,
      'analytics' => ['entitled' => (bool) $analytics],
      'offers' => $this->contextualOffers($entitlements, $projects),
      'preferences' => $this->preferences($customerId),
      'topics' => $this->topics(),
      'articles' => $this->articles(),
      'faqs' => $this->faqs(),
      'referrals' => $this->referrals($customerId),
    ];
  }

  /** Returns resumable pre-purchase website requests for one owned workspace. */
  public function websiteRequests(int $customerId, int $organizationId): array {
    if (!$this->isMember($customerId, $organizationId)) throw new \RuntimeException('Workspace not found.');
    $rows = $this->database->select('famtastic_project_request', 'r')->fields('r')
      ->condition('organization_id', $organizationId)->orderBy('changed', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map([$this, 'serializeWebsiteRequest'], $rows);
  }

  /** Creates a draft or submitted request and its distinct Drupal lead record. */
  public function createWebsiteRequest(int $customerId, string $organizationPublicId, array $input): array {
    $organization = $this->authorizedOrganization($customerId, $organizationPublicId);
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
    $clean = $this->validateWebsiteRequest($input);
    $now = $this->time->getRequestTime();
    $attribution = $this->attribution->snapshotFromArray($input, 'customer_portal');
    $claimedProspectId = $this->previews->claimedProspectId($customerId);
    $prospect = $claimedProspectId ? $this->entities->getStorage('famtastic_prospect')->load($claimedProspectId) : NULL;
    if (!$prospect) {
      $prospect = $this->entities->getStorage('famtastic_prospect')->create([
        'business_name' => $clean['business_name'] ?: $clean['project_name'],
        'public_email' => (string) $customer['email'], 'contact_name' => (string) $customer['display_name'],
        'contact_method' => 'email', 'contact_value' => (string) $customer['email'],
        'campaign' => 'customer_portal', 'source' => 'customer_portal', 'authorized' => TRUE,
        'confirmed_at' => $now, 'status' => $clean['status'] === 'submitted' ? 'lead' : 'new', 'owner_uid' => 1,
        'utm_json' => $this->attribution->toJson($attribution),
      ]);
      $prospect->save();
    }
    if (!empty($attribution['utm_content'])) {
      $this->attribution->recordSocialLead((string) $attribution['utm_content']);
    }
    $publicId = $this->uuid->generate();
    $id = (int) $this->database->insert('famtastic_project_request')->fields([
      'public_id' => $publicId, 'organization_id' => (int) $organization['id'], 'customer_id' => $customerId,
      'prospect_id' => (int) $prospect->id(), 'status' => $clean['status'],
      'project_name' => $clean['project_name'], 'business_name' => $clean['business_name'],
      'project_type' => $clean['project_type'], 'domain_choice' => $clean['domain_choice'],
      'existing_domain' => $clean['existing_domain'], 'recommendation_requested' => $clean['recommendation_requested'],
      'intake_data' => json_encode($clean['intake'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'submitted_at' => $clean['status'] === 'submitted' ? $now : NULL, 'created' => $now, 'changed' => $now,
    ])->execute();
    $this->claimResource((int) $organization['id'], 'prospect', (int) $prospect->id());
    $this->previews->attachClaimedRequest($customerId, $id, $clean['status']);
    $this->activity((int) $organization['id'], 'website_request.created', $clean['status'] === 'submitted' ? 'A new website request was submitted.' : 'A website request draft was saved.');
    if ($clean['status'] === 'submitted') {
      $this->queueWebsiteRequestNotifications($id, $customer, $clean);
      $this->queueWebsiteRequestProofJob($id, (int) $prospect->id(), $publicId, $clean['intake']);
    }
    return $this->serializeWebsiteRequest($this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc());
  }

  /** Saves or submits an existing request, enforcing customer and organization ownership. */
  public function updateWebsiteRequest(int $customerId, string $publicId, array $input): array {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId)->execute()->fetchAssoc();
    if (!$row || (int) $row['customer_id'] !== $customerId || !$this->isMember($customerId, (int) $row['organization_id'])) throw new \RuntimeException('Website request not found.');
    if (in_array($row['status'], ['converted', 'cancelled'], TRUE)) throw new \InvalidArgumentException('This request can no longer be edited.');
    $clean = $this->validateWebsiteRequest($input);
    $wasSubmitted = $row['status'] === 'submitted';
    if (in_array($row['status'], ['submitted', 'checkout_started'], TRUE) && $clean['status'] === 'draft') {
      $clean['status'] = $row['status'];
    }
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_project_request')->fields([
      'status' => $clean['status'], 'project_name' => $clean['project_name'], 'business_name' => $clean['business_name'],
      'project_type' => $clean['project_type'], 'domain_choice' => $clean['domain_choice'], 'existing_domain' => $clean['existing_domain'],
      'recommendation_requested' => $clean['recommendation_requested'],
      'intake_data' => json_encode($clean['intake'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'submitted_at' => $clean['status'] === 'submitted' ? ((int) $row['submitted_at'] ?: $now) : NULL, 'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    if ($clean['status'] === 'submitted' && !$wasSubmitted) {
      $prospect = $this->entities->getStorage('famtastic_prospect')->load((int) $row['prospect_id']);
      if ($prospect) {
        $prospect->set('status', 'lead');
        $prospect->save();
      }
      $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
      $this->queueWebsiteRequestNotifications((int) $row['id'], $customer, $clean);
      $this->queueWebsiteRequestProofJob((int) $row['id'], (int) $row['prospect_id'], (string) $row['public_id'], $clean['intake']);
      $this->activity((int) $row['organization_id'], 'website_request.submitted', 'A website request was submitted for review.');
    }
    $updated = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $row['id'])->execute()->fetchAssoc();
    return $this->serializeWebsiteRequest($updated);
  }

  /**
   * Creates a submitted website request from a prospect's discovery notes.
   *
   * Used when a prospect with discovery data registers and becomes a customer.
   * Links to the existing prospect rather than creating a new one.
   */
  public function createWebsiteRequestFromProspectDiscovery(int $customerId, int $prospectId, int $organizationId): ?array {
    // Check if a request already exists for this prospect.
    $existing = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['id'])
      ->condition('prospect_id', $prospectId)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($existing) {
      return NULL;
    }

    $prospect = $this->entities->getStorage('famtastic_prospect')->load($prospectId);
    if (!$prospect) {
      return NULL;
    }

    $discoveryNotes = (string) $prospect->get('discovery_notes')->value;
    $discovery = $this->parseDiscoveryNotes($discoveryNotes);
    $answers = is_array($discovery['answers'] ?? NULL) ? $discovery['answers'] : [];

    // Only auto-create if there are actual discovery answers or discovery notes.
    if (empty($answers) && $discoveryNotes === '') {
      return NULL;
    }

    $businessName = trim((string) ($answers['businessName'] ?? $answers['business_name'] ?? $prospect->label()));
    if ($businessName === '') {
      $businessName = 'My Business Website';
    }

    $projectName = $businessName . ' Website';
    $projectType = 'new_website';
    $domainChoice = 'undecided';
    $existingDomain = '';

    $setup = (string) ($answers['setup'] ?? '');
    if ($setup === 'none') {
      $domainChoice = 'new_domain';
    }
    elseif ($setup === 'existing') {
      $domainChoice = 'existing_domain';
      $existingDomain = (string) ($answers['domainDetails'] ?? $answers['existing_domain'] ?? '');
    }

    $pageCount = max(1, min(100, (int) ($answers['pages'] ?? $answers['page_count'] ?? 1)));

    $intake = [
      'schema_version' => 'website_discovery_v3',
      'primary_goal' => 'Build a professional website for ' . $businessName,
      'products_services' => (string) ($answers['industry'] ?? $answers['business_category'] ?? ''),
      'service_locations' => (string) ($answers['location'] ?? $answers['service_area'] ?? ''),
      'budget_context' => (string) ($answers['budget'] ?? ''),
      'page_count' => $pageCount,
      'launch_timing' => (string) ($answers['timeline'] ?? ''),
      'notes' => 'Auto-created from prospect discovery notes during registration. Source: ' . ($discovery['source'] ?? 'unknown'),
      'business_model' => (string) ($answers['businessDescription'] ?? $answers['business_description'] ?? ''),
      'industry' => (string) ($answers['industry'] ?? ''),
      'reference_sites' => (string) ($answers['referenceSites'] ?? ''),
    ];

    // Map AI features from discovery answers.
    $aiFeatures = $answers['aiFeatures'] ?? [];
    if (is_array($aiFeatures)) {
      $intake['required_features'] = implode(', ', $aiFeatures);
      if (in_array('automation', $aiFeatures, TRUE)) {
        $intake['ai_agent_goals'] = 'Automate business workflows and customer interactions';
      }
      if (in_array('chat', $aiFeatures, TRUE)) {
        $intake['ai_agent_goals'] = ($intake['ai_agent_goals'] ?? '') . '; AI chatbot for customer support';
      }
    }

    $intake['recommendation'] = $this->recommendWebsitePackage($projectType, $intake);

    $now = $this->time->getRequestTime();
    $publicId = $this->uuid->generate();

    $id = (int) $this->database->insert('famtastic_project_request')->fields([
      'public_id' => $publicId,
      'organization_id' => $organizationId,
      'customer_id' => $customerId,
      'prospect_id' => $prospectId,
      'status' => 'submitted',
      'project_name' => $projectName,
      'business_name' => $businessName,
      'project_type' => $projectType,
      'domain_choice' => $domainChoice,
      'existing_domain' => $existingDomain,
      'recommendation_requested' => 1,
      'intake_data' => json_encode($intake, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'submitted_at' => $now,
      'created' => $now,
      'changed' => $now,
    ])->execute();

    $this->claimResource($organizationId, 'prospect', $prospectId);
    $this->activity($organizationId, 'website_request.created', 'A new website request was submitted from discovery notes.');

    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
    $clean = [
      'status' => 'submitted',
      'project_name' => $projectName,
      'business_name' => $businessName,
      'project_type' => $projectType,
      'domain_choice' => $domainChoice,
      'existing_domain' => $existingDomain,
      'recommendation_requested' => TRUE,
      'intake' => $intake,
    ];
    $this->queueWebsiteRequestNotifications($id, $customer, $clean);
    $this->queueWebsiteRequestProofJob($id, $prospectId, $publicId, $intake);

    return $this->serializeWebsiteRequest($this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc());
  }

  /**
   * Parses discovery notes JSON from various storage formats.
   */
  private function parseDiscoveryNotes(string $notes): array {
    $notes = trim($notes);
    if ($notes === '') {
      return [];
    }
    // Try direct JSON first.
    $decoded = json_decode($notes, TRUE);
    if (is_array($decoded)) {
      return $decoded;
    }
    // Try extracting JSON from text (e.g. "QUOTE request\n{...}").
    if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $notes, $matches)) {
      $decoded = json_decode($matches[0], TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }
    return [];
  }

  /** Loads one request only when it belongs to the signed-in customer workspace. */
  public function ownedWebsiteRequest(int $customerId, string $publicId): ?array {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId)->execute()->fetchAssoc();
    return $row && (int) $row['customer_id'] === $customerId && $this->isMember($customerId, (int) $row['organization_id']) ? $row : NULL;
  }

  /** Atomically reserves a submitted request for one Commerce order. */
  public function bindWebsiteRequestToOrder(int $customerId, string $publicId, int $commerceOrderId): void {
    $row = $this->ownedWebsiteRequest($customerId, $publicId);
    if (!$row || !in_array($row['status'], ['submitted', 'checkout_started'], TRUE) || !empty($row['commerce_order_id'])) {
      throw new \RuntimeException('Website request is not available for checkout.');
    }
    $updated = $this->database->update('famtastic_project_request')->fields([
      'commerce_order_id' => $commerceOrderId, 'status' => 'checkout_started', 'changed' => $this->time->getRequestTime(),
    ])->condition('id', $row['id'])->isNull('commerce_order_id')->execute();
    if ($updated !== 1) throw new \RuntimeException('Website request checkout was already started.');
  }

  private function validateWebsiteRequest(array $input): array {
    $projectName = mb_substr(trim(strip_tags((string) ($input['project_name'] ?? ''))), 0, 255);
    if ($projectName === '') throw new \InvalidArgumentException('Give this website request a name so you can find it later.');
    $status = ($input['action'] ?? 'save') === 'submit' ? 'submitted' : 'draft';
    $type = in_array($input['project_type'] ?? '', ['new_website', 'landing_page', 'redesign', 'online_store'], TRUE) ? $input['project_type'] : 'new_website';
    $domain = in_array($input['domain_choice'] ?? '', ['undecided', 'new_domain', 'existing_domain'], TRUE) ? $input['domain_choice'] : 'undecided';
    $text = fn(string $key, int $max = 5000): string => mb_substr(trim(strip_tags((string) ($input[$key] ?? ''))), 0, $max);
    $intake = ['schema_version' => 'website_discovery_v3'];
    foreach ([
      'primary_goal', 'secondary_goals', 'success_metrics', 'ideal_customer', 'customer_pain_points',
      'products_services', 'desired_actions', 'required_features', 'integrations', 'page_list',
      'content_status', 'copywriting_needs', 'photo_asset_status', 'brand_status', 'style_preferences',
      'reference_sites', 'competitors', 'seo_keywords', 'service_locations', 'business_hours',
      'contact_details', 'social_profiles', 'accessibility_needs', 'privacy_legal_needs',
      'ecommerce_details', 'product_count', 'shipping_pickup', 'booking_details', 'ai_agent_goals',
      'maintenance_needs', 'launch_timing', 'budget_context', 'decision_makers', 'notes',
      'business_model', 'industry', 'research_context', 'reference_site_reasons',
      'existing_technology', 'desired_domains', 'domain_fallback', 'business_email_needs',
      'custom_needs',
       'preferred_colors', 'colors_to_avoid', 'desired_feeling', 'styles_to_avoid',
       'visual_reference_notes', 'ai_context_notes',
    ] as $key) $intake[$key] = $text($key);
    $intake['page_count'] = max(1, min(100, (int) ($input['page_count'] ?? 1)));
    $intake['famtastic_level'] = max(0, min(10, (int) ($input['famtastic_level'] ?? 5)));
    $intake['allow_bolder_direction'] = !empty($input['allow_bolder_direction']);
    $intake['life_path_opt_in'] = !empty($input['life_path_opt_in']);
    $intake['ai_enrichment_mode'] = in_array($input['ai_enrichment_mode'] ?? '', ['none', 'famtastic_managed', 'customer_managed'], TRUE)
      ? (string) $input['ai_enrichment_mode'] : 'none';
    $intake['recommendation'] = $this->recommendWebsitePackage($type, $intake);
    if ($status === 'submitted' && ($intake['primary_goal'] === '' || $intake['products_services'] === '')) throw new \InvalidArgumentException('Add the primary goal and what the business sells before submitting.');
    return [
      'status' => $status, 'project_name' => $projectName, 'business_name' => $text('business_name', 255),
      'project_type' => $type, 'domain_choice' => $domain, 'existing_domain' => $text('existing_domain', 255),
      'recommendation_requested' => !empty($input['recommendation_requested']) ? 1 : 0, 'intake' => $intake,
    ];
  }

  /** Creates an explainable recommendation without turning every intake into $199. */
  public function recommendWebsitePackage(string $type, array $intake): array {
    $features = mb_strtolower(implode(' ', [
      $intake['required_features'], $intake['integrations'], $intake['ecommerce_details'],
      $intake['booking_details'], $intake['ai_agent_goals'],
      $intake['custom_needs'],
    ]));
    $complexTerms = ['shop', 'cart', 'checkout', 'ecommerce', 'membership', 'portal', 'custom api', 'hipaa', 'inventory', 'subscription'];
    $complex = $type === 'online_store';
    foreach ($complexTerms as $term) $complex = $complex || str_contains($features, $term);
    $pages = (int) ($intake['page_count'] ?? 1);
    $reasons = [];
    $addons = [];
    if ($complex || $pages > 5) {
      $reasons[] = $complex ? 'The requested functionality needs scope and integration review.' : 'The requested page count exceeds the packaged five-page scope.';
      return ['recommended_sku' => '', 'label' => 'Custom scope review', 'complexity_score' => 100, 'review_required' => TRUE, 'reasons' => $reasons, 'suggested_addon_skus' => []];
    }
    $score = $pages > 1 ? 35 : 0;
    if ($type === 'redesign') $score += 20;
    foreach (['lead', 'quote', 'form', 'gallery', 'analytics', 'seo'] as $term) if (str_contains($features, $term)) $score += 8;
    if ($intake['content_status'] === 'help_needed' || $intake['copywriting_needs'] !== '') $addons[] = 'FAM-COPY';
    if ($intake['brand_status'] === 'help_needed') $addons[] = 'FAM-BRAND';
    if ($intake['business_email_needs'] !== '') $addons[] = 'FAM-BUSINESS-EMAIL';
    if ($intake['ai_agent_goals'] !== '') $addons[] = 'FAM-AI-AGENT';
    if ($intake['booking_details'] !== '') $addons[] = 'FAM-SCHEDULING';
    if ($intake['custom_needs'] !== '') {
      $reasons[] = 'An unlisted product, service, or workflow request needs human scope review.';
      return ['recommended_sku' => '', 'label' => 'Custom scope review', 'complexity_score' => 100, 'review_required' => TRUE, 'reasons' => $reasons, 'suggested_addon_skus' => array_values(array_unique($addons))];
    }
    if ($pages > 1 || $score >= 30) {
      $reasons[] = 'A multi-page business presence benefits from structured navigation, lead capture, SEO, and analytics.';
      return ['recommended_sku' => 'FAM-BUSINESS-499', 'label' => 'Business Website Bundle', 'complexity_score' => min(99, $score), 'review_required' => FALSE, 'reasons' => $reasons, 'suggested_addon_skus' => array_values(array_unique($addons))];
    }
    $reasons[] = 'The stated need fits a focused one-page website or landing page.';
    return ['recommended_sku' => 'FAM-FOOT-199', 'label' => 'Web Basics Bundle', 'complexity_score' => $score, 'review_required' => FALSE, 'reasons' => $reasons, 'suggested_addon_skus' => array_values(array_unique($addons))];
  }

  private function serializeWebsiteRequest(array $row): array {
    $row['intake'] = json_decode((string) $row['intake_data'], TRUE) ?: [];
    $recommendation = (array) ($row['intake']['recommendation'] ?? []);
    $offer = $this->database->select('famtastic_private_offer', 'o')->fields('o', ['public_id', 'sku', 'list_amount_minor', 'offered_amount_minor', 'currency', 'reason', 'expires_at'])
      ->condition('website_request_id', (int) $row['id'])->condition('status', 'active')
      ->condition('expires_at', $this->time->getRequestTime(), '>')->orderBy('created', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    $row['private_offer'] = $offer ?: NULL;
    $row['proofs'] = $this->serializeRequestProof($row);
    $row['proof_share'] = $this->proofSharePayload($row);
    $row['assets'] = $this->requestAssets((int) $row['id']);
    $row['recommended_sku'] = (string) ($recommendation['recommended_sku'] ?? '');
    if ($offer) $row['recommended_sku'] = (string) $offer['sku'];
    $row['direct_checkout_available'] = $row['status'] === 'submitted'
      && $row['proof_review_status'] === 'selected'
      && empty($recommendation['review_required'])
      && in_array($row['recommended_sku'], ['FAM-FOOT-199', 'FAM-BUSINESS-499'], TRUE);
    foreach (['id', 'organization_id', 'customer_id', 'prospect_id', 'commerce_order_id', 'intake_id', 'project_id', 'intake_data', 'proof_campaign_id', 'proof_approved_by_uid', 'proof_share_enabled', 'proof_share_version', 'proof_share_changed_at', 'proof_share_changed_by_uid'] as $key) unset($row[$key]);
    return $row;
  }

  /** Changes the unlisted proof link for one account-owned request. */
  public function updateWebsiteProofShare(int $customerId, string $publicId, string $action, int $uid): array {
    $row = $this->ownedWebsiteRequest($customerId, $publicId);
    if (!$row) throw new \RuntimeException('Website proofs are not available.');
    $this->changeWebsiteProofShare($row, $action, $uid);
    $updated = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $row['id'])->execute()->fetchAssoc();
    return $this->serializeWebsiteRequest($updated ?: $row);
  }

  /** Changes an unlisted proof link from the staff proof-review screen. */
  public function manageWebsiteProofShare(int $requestId, string $action, int $uid): array {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$row) throw new \RuntimeException('Website proofs are not available.');
    $this->changeWebsiteProofShare($row, $action, $uid);
    $updated = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    return $this->proofSharePayload($updated ?: $row);
  }

  /** Returns staff-safe share status for one request. */
  public function websiteProofShareStatus(int $requestId): array {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    return $row ? $this->proofSharePayload($row) : ['enabled' => FALSE, 'url' => ''];
  }

  /** Resolves a valid, enabled share token to its request row. */
  public function sharedWebsiteRequest(string $publicId, string $signature): ?array {
    if (!preg_match('/^[0-9a-f]{64}$/', $signature)) return NULL;
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId)->execute()->fetchAssoc();
    if (!$row || empty($row['proof_share_enabled']) || !$this->requestProofsAreCustomerVisible($row)) return NULL;
    return hash_equals($this->proofShareSignature($row), $signature) ? $row : NULL;
  }

  /** Returns the deliberately minimal, anonymous proof-share payload. */
  public function publicWebsiteProofShare(string $publicId, string $signature): ?array {
    $row = $this->sharedWebsiteRequest($publicId, $signature);
    if (!$row) return NULL;
    $proofs = $this->serializeRequestProof($row);
    if (!$proofs) return NULL;
    $base = '/web/api/proof-shares/' . rawurlencode($publicId) . '/' . rawurlencode($signature) . '/proofs/';
    $variants = array_map(static fn(array $variant): array => [
      'direction_id' => (string) $variant['direction_id'],
      'direction_name' => (string) $variant['direction_name'],
      'preview_url' => $base . rawurlencode((string) $variant['direction_id']),
    ], $proofs['variants']);
    return [
      'project_name' => (string) $row['project_name'],
      'business_name' => (string) ($row['business_name'] ?: $row['project_name']),
      'proof_count' => count($variants),
      'variants' => $variants,
    ];
  }

  private function changeWebsiteProofShare(array $row, string $action, int $uid): void {
    if (!$this->requestProofsAreCustomerVisible($row) || !$this->serializeRequestProof($row)) {
      throw new \RuntimeException('Only a complete owner-approved proof set can be shared.');
    }
    if (!in_array($action, ['enable', 'disable', 'rotate'], TRUE)) {
      throw new \InvalidArgumentException('Choose a valid proof-sharing action.');
    }
    $enabled = !empty($row['proof_share_enabled']);
    if ($action === 'rotate' && !$enabled) throw new \InvalidArgumentException('Enable sharing before creating a new link.');
    $version = max(1, (int) ($row['proof_share_version'] ?? 1));
    if (($action === 'disable' && $enabled) || $action === 'rotate') $version++;
    $newEnabled = $action !== 'disable';
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_project_request')->fields([
      'proof_share_enabled' => $newEnabled ? 1 : 0,
      'proof_share_version' => $version,
      'proof_share_changed_at' => $now,
      'proof_share_changed_by_uid' => $uid,
      'changed' => $now,
    ])->condition('id', $row['id'])->execute();
    $event = $action === 'rotate' ? 'website_request.proof_share_rotated' : 'website_request.proof_share_' . ($newEnabled ? 'enabled' : 'disabled');
    $summary = $action === 'rotate' ? 'A new unlisted website-proof link was created.' : 'Unlisted website-proof sharing was turned ' . ($newEnabled ? 'on.' : 'off.');
    $this->activity((int) $row['organization_id'], $event, $summary);
  }

  private function requestProofsAreCustomerVisible(array $row): bool {
    return in_array((string) ($row['proof_review_status'] ?? ''), ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE);
  }

  private function proofSharePayload(array $row): array {
    $enabled = !empty($row['proof_share_enabled']) && $this->requestProofsAreCustomerVisible($row) && (bool) $this->serializeRequestProof($row);
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
    return [
      'enabled' => $enabled,
      'url' => $enabled ? $base . '/proofs/share/' . rawurlencode((string) $row['public_id']) . '/' . $this->proofShareSignature($row) : '',
      'changed_at' => !empty($row['proof_share_changed_at']) ? (int) $row['proof_share_changed_at'] : NULL,
    ];
  }

  private function proofShareSignature(array $row): string {
    $version = max(1, (int) ($row['proof_share_version'] ?? 1));
    return hash_hmac('sha256', 'website-proof-share-v1|' . (string) $row['public_id'] . '|' . $version, Settings::getHashSalt());
  }

  /** Returns customer-safe proof metadata only after explicit owner approval. */
  private function serializeRequestProof(array $row): ?array {
    if (!in_array((string) ($row['proof_review_status'] ?? ''), ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) return NULL;
    $campaignId = (int) ($row['proof_campaign_id'] ?? 0);
    if (!$campaignId) return NULL;
    $campaign = $this->entities->getStorage('proof_campaign')->load($campaignId);
    if (!$campaign || $campaign->get('generation_status')->value !== 'ready') return NULL;
    $ids = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', $campaignId)->sort('direction_id')->execute();
    $variants = [];
    foreach ($this->entities->getStorage('proof_variant')->loadMultiple($ids) as $variant) {
      $direction = (string) $variant->get('direction_id')->value;
      $variants[] = [
        'direction_id' => $direction,
        'direction_name' => (string) $variant->get('direction_name')->value,
        'preview_url' => '/web/api/customer/website-requests/' . rawurlencode((string) $row['public_id']) . '/proofs/' . rawurlencode($direction),
      ];
    }
    $directions = array_column($variants, 'direction_id');
    $validSet = $directions === ['a', 'b', 'c'] || $directions === ['a', 'b', 'c', 'd', 'e', 'f'];
    return $validSet ? [
      'status' => (string) $row['proof_review_status'],
      'selected_variant' => (string) ($row['selected_proof_direction'] ?? ''),
      'variants' => $variants,
    ] : NULL;
  }

  /** Returns integrity metadata for request-owned private reference files. */
  private function requestAssets(int $requestId): array {
    return array_map(static fn(array $row): array => [
      'public_id' => $row['public_id'], 'kind' => $row['kind'], 'name' => $row['original_name'],
      'mime_type' => $row['mime_type'], 'size_bytes' => (int) $row['size_bytes'],
      'ownership_confirmed' => (bool) $row['ownership_confirmed'], 'ai_use_consent' => (bool) $row['ai_use_consent'],
    ], $this->database->select('famtastic_request_asset', 'a')->fields('a')
      ->condition('website_request_id', $requestId)->condition('status', 'active')->orderBy('created')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  private function queueWebsiteRequestNotifications(int $id, array $customer, array $request): void {
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    $subject = 'Website request received — ' . $request['project_name'];
    $this->queueNotification("website-request:{$id}:customer", 'transactional', (string) $customer['email'], $subject,
      "We received your website request for {$request['project_name']}. Fritz will review it within 3 business days. You can continue or review it from your customer portal.");
    $this->queueNotification("website-request:{$id}:staff", 'operational', $admin, 'New portal website request — ' . $request['project_name'],
      "Customer: {$customer['display_name']}\nEmail: {$customer['email']}\nType: {$request['project_type']}\nProof generation has been queued.\nOwner review queue: https://famtasticdesigns.com/web/admin/famtastic/metric/website-requests");
  }

  /** Queues one owner alert for a newly created customer account. */
  public function queueRegistrationNotification(array $customer): void {
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    $this->queueNotification('customer:' . (int) $customer['id'] . ':staff-registration', 'operational', $admin,
      'New FAMtastic customer registration — ' . (string) $customer['display_name'],
      "Customer: {$customer['display_name']}\nEmail: {$customer['email']}\nVerification is pending.\nOpen customers: https://famtasticdesigns.com/web/admin/famtastic/metric/customers");
  }

  /** Queues a truthful owner alert only after a preview signup is verified. */
  public function queueVerifiedPreviewRegistrationNotification(int $customerId): void {
    $customer = $this->customerForId($customerId);
    if (!$customer) {
      return;
    }
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    $this->queueNotification('customer:' . (int) $customer['id'] . ':staff-registration', 'operational', $admin,
      'Verified FAMtastic customer registration — ' . (string) $customer['display_name'],
      "Customer: {$customer['display_name']}\nEmail: {$customer['email']}\nEmail verification completed.\nOpen customers: https://famtasticdesigns.com/web/admin/famtastic/metric/customers");
  }

  /** Enqueues the canonical pre-purchase proof routine exactly once. */
  private function queueWebsiteRequestProofJob(int $requestId, int $prospectId, string $publicId, array $intake): void {
    $this->ledger->enqueue(
      'website_proof.generate.v1:request:' . $requestId,
      'proof.generate',
      [
        'routine' => 'website_proof.generate.v1',
        'prospect_id' => $prospectId,
        'website_request_id' => $requestId,
        'website_request_public_id' => $publicId,
        'website_discovery_v3' => $intake,
        'website_discovery_v2' => $intake,
      ],
      $prospectId,
    );
  }

  /** Returns the current normalized brief for a request-bound proof worker. */
  public function websiteRequestProofContext(int $requestId): array {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$row || $row['status'] === 'draft') {
      throw new \RuntimeException('Submitted website request not found.');
    }
    $intake = json_decode((string) $row['intake_data'], TRUE, flags: JSON_THROW_ON_ERROR);
    return [
      'routine' => 'website_proof.generate.v1',
      'website_request_id' => (int) $row['id'],
      'website_request_public_id' => (string) $row['public_id'],
      'website_discovery_v3' => $intake,
      'website_discovery_v2' => $intake,
    ];
  }

  /** Returns the one proof campaign explicitly bound to a submitted request. */
  public function websiteRequestProofCampaignId(int $requestId): ?int {
    $value = $this->database->select('famtastic_project_request', 'r')->fields('r', ['proof_campaign_id'])
      ->condition('id', $requestId)->range(0, 1)->execute()->fetchField();
    return $value ? (int) $value : NULL;
  }

  /** Binds a fresh campaign before a request-specific remote callback can arrive. */
  public function bindWebsiteRequestProofCampaign(int $requestId, int $prospectId, int $campaignId): void {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r', ['id', 'prospect_id', 'status', 'proof_campaign_id'])
      ->condition('id', $requestId)->range(0, 1)->execute()->fetchAssoc();
    if (!$row || (int) $row['prospect_id'] !== $prospectId || $row['status'] === 'draft') {
      throw new \RuntimeException('A submitted website request is required before its proof campaign can be bound.');
    }
    $bound = (int) ($row['proof_campaign_id'] ?? 0);
    if ($bound && $bound !== $campaignId) {
      throw new \RuntimeException('This website request is already bound to a different proof campaign.');
    }
    if ($bound === $campaignId) {
      return;
    }
    $this->database->update('famtastic_project_request')->fields([
      'proof_campaign_id' => $campaignId,
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', $requestId)->isNull('proof_campaign_id')->execute();
    $actual = $this->websiteRequestProofCampaignId($requestId);
    if ($actual !== $campaignId) {
      throw new \RuntimeException('The website request changed before its proof campaign could be bound.');
    }
  }

  /** Owner approval reveals proofs and queues one transactional customer email. */
  public function approveWebsiteRequestProof(int $requestId, int $uid): array {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$row || !$row['proof_campaign_id'] || $row['proof_review_status'] !== 'owner_review') throw new \RuntimeException('Website proofs are not awaiting owner review.');
    $campaign = $this->entities->getStorage('proof_campaign')->load((int) $row['proof_campaign_id']);
    $variantCount = (int) $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $row['proof_campaign_id'])->count()->execute();
    $directions = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $row['proof_campaign_id'])->sort('direction_id')->execute();
    $directionValues = array_map(static fn(object $variant): string => (string) $variant->get('direction_id')->value,
      array_values($this->entities->getStorage('proof_variant')->loadMultiple($directions)));
    $validSet = $directionValues === ['a', 'b', 'c'] || $directionValues === ['a', 'b', 'c', 'd', 'e', 'f'];
    if (!$campaign || $campaign->get('generation_status')->value !== 'ready' || !in_array($variantCount, [3, 6], TRUE) || !$validSet) throw new \RuntimeException('A complete three- or six-direction proof set is required.');
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_project_request')->fields([
      'proof_review_status' => 'customer_ready', 'proof_approved_by_uid' => $uid, 'proof_approved_at' => $now, 'changed' => $now,
    ])->condition('id', $requestId)->condition('proof_review_status', 'owner_review')->execute();
    $this->database->update('famtastic_notification_outbox')->fields(['status' => 'superseded', 'changed' => $now])
      ->condition('notification_key', 'website-request:' . $requestId . ':owner-proof-review:%', 'LIKE')
      ->condition('status', ['queued', 'retry'], 'IN')->execute();
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', (int) $row['customer_id'])->execute()->fetchAssoc();
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
    $count = count($directionValues);
    $setLabel = $count === 6 ? 'six website concepts—including three fully FAMtastic directions—' : 'Safe, Wild, and OMG concepts';
    $reviewUrl = $base . '/portal/?section=projects&request=' . rawurlencode((string) $row['public_id']);
    $this->queueNotification('website-request:' . $requestId . ':proofs:' . (int) $row['proof_campaign_id'] . ':' . $count, 'transactional', (string) $customer['email'],
      'Your FAMtastic website concepts are ready', "Your {$setLabel} are ready. Sign in to compare them and choose your direction:\n{$reviewUrl}\n\nUse the same email address that received this message.");
    $this->activity((int) $row['organization_id'], 'website_request.proofs_approved', ucfirst($setLabel) . ' were approved for customer review.');
    return $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc() ?: [];
  }

  /**
   * Attaches a complete core or core-plus-showcase set for owner review.
   *
   * This is the shared terminal step for remote callbacks, local imports, and
   * an idempotent worker that discovers an already-ready campaign. It never
   * exposes the proofs to the customer and never queues a customer email.
   */
  public function attachWebsiteRequestProof(int $requestId, object $campaign, array $variants): void {
    $row = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $requestId)->execute()->fetchAssoc();
    if (!$row || (int) $row['prospect_id'] !== (int) $campaign->get('prospect_id')->target_id) {
      throw new \RuntimeException('Proof campaign does not belong to this website request.');
    }
    if (!empty($row['proof_campaign_id']) && (int) $row['proof_campaign_id'] !== (int) $campaign->id()) {
      throw new \RuntimeException('Proof campaign does not match the campaign bound to this website request.');
    }
    $directions = [];
    foreach ($variants as $variant) {
      $directions[] = (string) $variant->get('direction_id')->value;
    }
    sort($directions);
    $validSet = $directions === ['a', 'b', 'c'] || $directions === ['a', 'b', 'c', 'd', 'e', 'f'];
    if (!$validSet || $campaign->get('generation_status')->value !== 'ready') {
      throw new \RuntimeException('A complete Safe/Wild/OMG set or six-direction showcase set is required.');
    }
    $campaignEntityId = (int) $campaign->id();
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_project_request')->fields([
      'proof_campaign_id' => $campaignEntityId,
      'proof_review_status' => 'owner_review',
      'changed' => $now,
    ])->condition('id', $requestId)->execute();
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    $count = count($directions);
    if ($count === 6) {
      $this->database->update('famtastic_notification_outbox')->fields(['status' => 'superseded', 'changed' => $now])
        ->condition('notification_key', 'website-request:' . $requestId . ':owner-proof-review:%', 'LIKE')
        ->condition('status', ['queued', 'retry'], 'IN')->execute();
    }
    $description = $count === 6 ? 'Safe, Wild, OMG, Royal Current, Crownverse, and Shay Live' : 'Safe, Wild, and OMG';
    $this->queueNotification('website-request:' . $requestId . ':owner-proof-review:' . $campaignEntityId . ':' . $count, 'operational', $admin,
      $count . ' website proofs need your approval — ' . (string) $row['project_name'],
      "{$description} are ready for owner review. Nothing has been sent to the customer.\nReview: https://famtasticdesigns.com/web/admin/famtastic/website-request/{$requestId}/proof-review");
    $this->activity((int) $row['organization_id'], 'website_request.proofs_owner_review', $count . ' website concepts are awaiting FAMtastic owner review.');
    if (!empty($row['project_id'])) {
      $this->markProjectProofReady((int) $row['project_id'], $campaign, $variants);
    }
  }

  /** Records one account-owned selection or revision request. */
  public function decideWebsiteRequestProof(int $customerId, string $publicId, array $input): array {
    $row = $this->ownedWebsiteRequest($customerId, $publicId);
    if (!$row || !in_array($row['proof_review_status'], ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) throw new \RuntimeException('Website proofs are not available.');
    $action = (string) ($input['action'] ?? 'select');
    $now = $this->time->getRequestTime();
    if ($action === 'revision') {
      $notes = mb_substr(trim(strip_tags((string) ($input['notes'] ?? ''))), 0, 5000);
      if ($notes === '') throw new \InvalidArgumentException('Tell us what you want adjusted.');
      $intake = json_decode((string) $row['intake_data'], TRUE) ?: [];
      $intake['proof_revision_request'] = ['notes' => $notes, 'requested_at' => gmdate(DATE_ATOM, $now)];
      $this->database->update('famtastic_project_request')->fields(['proof_review_status' => 'revision_requested', 'intake_data' => json_encode($intake, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'changed' => $now])->condition('id', $row['id'])->execute();
      $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
      $notesHash = hash('sha256', $notes);
      $this->queueNotification('website-request:' . $row['id'] . ':proof-revision:' . $notesHash, 'operational', $admin, 'Website proof revision requested — ' . $row['project_name'], "Customer request:\n{$notes}\nReview: https://famtasticdesigns.com/web/admin/famtastic/website-request/{$row['id']}/proof-review");
      $customer = $this->customerContact((int) $row['customer_id']);
      if ($customer['email'] !== '') {
        $this->queueNotification('website-request:' . $row['id'] . ':customer-revision-ack:' . $notesHash, 'transactional', $customer['email'],
          'We received your website revision request',
          "Hi {$customer['display_name']},\n\nFAMtastic Concierge has your revision notes for {$row['project_name']}. Fritz will review them and follow up with the next step.\n" . $this->portalLink((string) $row['public_id']) . "\n\n— FAMtastic Concierge");
      }
    }
    else {
      $direction = strtolower((string) ($input['direction'] ?? ''));
      if (!in_array($direction, ['a', 'b', 'c', 'd', 'e', 'f'], TRUE)) throw new \InvalidArgumentException('Choose one available website direction.');
      $exists = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)->condition('campaign_id', (int) $row['proof_campaign_id'])->condition('direction_id', $direction)->count()->execute();
      if ((int) $exists !== 1) throw new \InvalidArgumentException('That proof direction is unavailable.');
      $this->database->update('famtastic_project_request')->fields(['proof_review_status' => 'selected', 'selected_proof_direction' => $direction, 'selected_proof_at' => $now, 'changed' => $now])->condition('id', $row['id'])->execute();
      $campaign = $this->entities->getStorage('proof_campaign')->load((int) $row['proof_campaign_id']);
      if ($campaign) $campaign->set('selected_variant', $direction)->set('selected_at', $now)->save();
      $this->activity((int) $row['organization_id'], 'website_request.proof_selected', 'A website concept was selected and is ready for purchase.');
      $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
      $customer = $this->customerContact((int) $row['customer_id']);
      foreach (['owner-proof-selected', 'customer-proof-selected'] as $supersedeKey) {
        $this->database->update('famtastic_notification_outbox')->fields(['status' => 'superseded', 'changed' => $now])
          ->condition('notification_key', 'website-request:' . $row['id'] . ':' . $supersedeKey . ':%', 'LIKE')
          ->condition('status', ['queued', 'retry'], 'IN')->execute();
      }
      $this->queueNotification('website-request:' . $row['id'] . ':owner-proof-selected:' . $direction, 'operational', $admin,
        'Customer selected proof ' . strtoupper($direction) . ' — ' . $row['project_name'],
        "Customer: {$customer['display_name']} ({$customer['email']})\nSelected direction: " . strtoupper($direction) . "\nNext step: confirm the selection, then prepare the private offer or checkout.\nReview: https://famtasticdesigns.com/web/admin/famtastic/website-request/{$row['id']}/proof-review");
      if ($customer['email'] !== '') {
        $this->queueNotification('website-request:' . $row['id'] . ':customer-proof-selected:' . $direction, 'transactional', $customer['email'],
          'We received your website direction choice',
          "Hi {$customer['display_name']},\n\nThanks for choosing direction " . strtoupper($direction) . " for {$row['project_name']}. FAMtastic Concierge recorded your choice and Fritz will follow up with the next step.\n" . $this->portalLink((string) $row['public_id']) . "\n\n— FAMtastic Concierge");
      }
    }
    $updated = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $row['id'])->execute()->fetchAssoc();
    return $this->serializeWebsiteRequest($updated);
  }

  /** Returns one customer's acknowledgment contact details. */
  private function customerContact(int $customerId): array {
    $row = $this->database->select('famtastic_customer', 'c')
      ->fields('c', ['display_name', 'email'])
      ->condition('id', $customerId)
      ->execute()
      ->fetchAssoc();
    return $row ? ['display_name' => (string) $row['display_name'], 'email' => mb_strtolower((string) $row['email'])] : ['display_name' => 'there', 'email' => ''];
  }

  /** Builds the portal deep link used by customer acknowledgments. */
  private function portalLink(string $publicId): string {
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
    return $base . '/portal/?section=projects&request=' . rawurlencode($publicId);
  }

  public function updateCustomer(int $customerId, array $input): void {
    $fields = ['changed' => $this->time->getRequestTime()];
    foreach (['display_name' => 255, 'phone' => 64] as $key => $length) {
      if (array_key_exists($key, $input)) {
        $fields[$key] = mb_substr(trim(strip_tags((string) $input[$key])), 0, $length);
      }
    }
    if (isset($input['marketing_status']) && in_array($input['marketing_status'], ['subscribed', 'unsubscribed'], TRUE)) {
      $fields['marketing_status'] = $input['marketing_status'];
    }
    $this->database->update('famtastic_customer')->fields($fields)->condition('id', $customerId)->execute();
  }

  public function preferences(int $customerId): array {
    $defaults = [
      'project_email' => 1, 'support_email' => 1, 'billing_email' => 1,
      'analytics_digest' => 'monthly', 'product_education' => 1,
      'deals_promotions' => 1, 'topic_keys' => '[]', 'consent_version' => 'portal-v1',
    ];
    $row = $this->database->select('famtastic_portal_preference', 'p')->fields('p')
      ->condition('customer_id', $customerId)->execute()->fetchAssoc() ?: [];
    $values = array_replace($defaults, $row);
    $values['topics'] = array_values(array_filter((array) json_decode((string) $values['topic_keys'], TRUE)));
    unset($values['customer_id'], $values['topic_keys'], $values['created'], $values['changed']);
    foreach (['project_email', 'support_email', 'billing_email', 'product_education', 'deals_promotions'] as $key) {
      $values[$key] = (bool) $values[$key];
    }
    return $values;
  }

  public function updatePreferences(int $customerId, array $input): array {
    $topics = array_values(array_intersect(array_keys($this->topics()), array_map('strval', (array) ($input['topics'] ?? []))));
    $digest = in_array($input['analytics_digest'] ?? '', ['off', 'weekly', 'monthly'], TRUE) ? $input['analytics_digest'] : 'monthly';
    $now = $this->time->getRequestTime();
    $fields = [
      'project_email' => !empty($input['project_email']) ? 1 : 0,
      'support_email' => !empty($input['support_email']) ? 1 : 0,
      'billing_email' => !empty($input['billing_email']) ? 1 : 0,
      'analytics_digest' => $digest,
      'product_education' => !empty($input['product_education']) ? 1 : 0,
      'deals_promotions' => !empty($input['deals_promotions']) ? 1 : 0,
      'topic_keys' => json_encode($topics), 'consent_version' => 'portal-v1', 'changed' => $now,
    ];
    $this->database->merge('famtastic_portal_preference')->key('customer_id', $customerId)
      ->insertFields(['customer_id' => $customerId] + $fields + ['created' => $now])->updateFields($fields)->execute();
    $this->database->update('famtastic_customer')->fields([
      'marketing_status' => $fields['deals_promotions'] ? 'subscribed' : 'unsubscribed', 'changed' => $now,
    ])->condition('id', $customerId)->execute();
    return $this->preferences($customerId);
  }

  public function createReferral(int $customerId, string $organizationPublicId, array $input): array {
    $organization = $this->authorizedOrganization($customerId, $organizationPublicId);
    $email = mb_strtolower(trim((string) ($input['friend_email'] ?? '')));
    $name = mb_substr(trim(strip_tags((string) ($input['friend_name'] ?? ''))), 0, 255);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($input['permission_confirmed'])) {
      throw new \InvalidArgumentException('Add your friend’s name, a valid email, and confirm they agreed to be referred.');
    }
    $now = $this->time->getRequestTime();
    $publicId = $this->uuid->generate();
    $code = strtoupper(substr(hash('sha256', $customerId . '|' . $publicId), 0, 10));
    $this->database->insert('famtastic_referral')->fields([
      'public_id' => $publicId, 'customer_id' => $customerId, 'organization_id' => $organization['id'],
      'referral_code' => $code, 'friend_name' => $name, 'friend_email_hash' => hash('sha256', $email),
      'status' => 'shared', 'reward_status' => 'not_earned', 'created' => $now, 'changed' => $now,
    ])->execute();
    $this->activity((int) $organization['id'], 'referral.shared', 'You shared FAMtastic Designs with a friend.');
    return ['public_id' => $publicId, 'friend_name' => $name, 'status' => 'shared', 'reward_status' => 'not_earned', 'created' => $now];
  }

  private function referrals(int $customerId): array {
    return $this->database->select('famtastic_referral', 'r')
      ->fields('r', ['public_id', 'friend_name', 'status', 'reward_status', 'created'])
      ->condition('customer_id', $customerId)->orderBy('created', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function topics(): array {
    return [
      'website_growth' => 'Website growth', 'ai_agents' => 'AI agents', 'lead_generation' => 'Lead generation',
      'automation' => 'Business automation', 'seo' => 'SEO', 'analytics' => 'Analytics',
      'ecommerce' => 'Ecommerce', 'security' => 'Hosting & security', 'reviews' => 'Reviews & reputation',
      'campaigns' => 'Marketing campaigns', 'accessibility' => 'Accessibility',
    ];
  }

  private function articles(): array {
    $ids = $this->entities->getStorage('node')->getQuery()->accessCheck(FALSE)
      ->condition('type', 'blog_post')->condition('status', 1)->sort('created', 'DESC')->range(0, 8)->execute();
    $articles = [];
    foreach ($this->entities->getStorage('node')->loadMultiple($ids) as $node) {
      $category = $node->hasField('field_blog_category') && !$node->get('field_blog_category')->isEmpty()
        ? ($node->get('field_blog_category')->entity?->label() ?? 'Business growth') : 'Business growth';
      $articles[] = [
        'id' => $node->uuid(), 'title' => $node->label(),
        'excerpt' => $node->hasField('field_excerpt') ? (string) $node->get('field_excerpt')->value : '',
        'topic' => $category, 'url' => $node->toUrl()->toString(), 'created' => (int) $node->getCreatedTime(),
      ];
    }
    return $articles;
  }

  private function faqs(): array {
    $ids = $this->entities->getStorage('node')->getQuery()->accessCheck(FALSE)
      ->condition('type', 'faq_item')->condition('status', 1)->sort('created', 'DESC')->range(0, 30)->execute();
    $faqs = [];
    foreach ($this->entities->getStorage('node')->loadMultiple($ids) as $node) {
      $category = $node->hasField('field_faq_category') && !$node->get('field_faq_category')->isEmpty()
        ? ($node->get('field_faq_category')->entity?->label() ?? 'General') : 'General';
      $answer = $node->hasField('field_answer') ? (string) $node->get('field_answer')->value : '';
      $faqs[] = ['id' => $node->uuid(), 'question' => $node->label(), 'answer' => trim(strip_tags($answer)), 'category' => $category];
    }
    return $faqs;
  }

  public function createThread(int $customerId, string $organizationPublicId, array $input, int $uid): array {
    $organization = $this->authorizedOrganization($customerId, $organizationPublicId);
    $subject = mb_substr(trim(strip_tags((string) ($input['subject'] ?? 'Support request'))), 0, 255);
    $body = trim(strip_tags((string) ($input['body'] ?? '')));
    if ($body === '') throw new \InvalidArgumentException('A message is required.');
    $now = $this->time->getRequestTime();
    $publicId = $this->uuid->generate();
    $threadId = (int) $this->database->insert('famtastic_portal_thread')->fields([
      'public_id' => $publicId, 'organization_id' => $organization['id'],
      'kind' => in_array($input['kind'] ?? '', ['project', 'support', 'billing'], TRUE) ? $input['kind'] : 'support',
      'subject' => $subject, 'status' => 'open', 'created_by' => $customerId,
      'created' => $now, 'changed' => $now,
    ])->execute();
    $this->database->insert('famtastic_portal_message')->fields([
      'thread_id' => $threadId, 'author_uid' => $uid, 'author_type' => 'customer', 'body' => $body, 'created' => $now,
    ])->execute();
    $caseNumber = 'FAM-' . gmdate('ymd', $now) . '-' . str_pad((string) $threadId, 5, '0', STR_PAD_LEFT);
    $priority = in_array($input['priority'] ?? '', ['urgent', 'high', 'normal', 'low'], TRUE) ? $input['priority'] : 'normal';
    $responseHours = ['urgent' => 4, 'high' => 24, 'normal' => 72, 'low' => 120][$priority];
    $this->database->insert('famtastic_support_case')->fields([
      'case_number' => $caseNumber, 'thread_id' => $threadId,
      'category' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($input['category'] ?? 'general'))) ?: 'general',
      'priority' => $priority, 'status' => 'new', 'owner_uid' => 1,
      'service_key' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($input['service_key'] ?? ''))),
      'response_due' => $now + ($responseHours * 3600), 'created' => $now, 'changed' => $now,
    ])->execute();
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    $replyAddress = 'support+' . $publicId . '@famtasticdesigns.com';
    $this->queueNotification('support:' . $threadId . ':customer-created', 'transactional', (string) $customer['email'],
      "Support request {$caseNumber} received", "We received your request: {$subject}\nCase: {$caseNumber}\nReply address: {$replyAddress}");
    $this->queueNotification('support:' . $threadId . ':staff-created', 'operational', $admin,
      "New support case {$caseNumber} — {$subject}", "Customer: {$customer['display_name']}\nPriority: {$priority}\nCase: {$caseNumber}\nPortal thread: {$publicId}");
    $this->activity((int) $organization['id'], 'support.created', 'A new support conversation was opened.');
    return ['public_id' => $publicId, 'case_number' => $caseNumber, 'subject' => $subject, 'status' => 'open', 'case_status' => 'new', 'priority' => $priority, 'created' => $now];
  }

  public function thread(int $customerId, string $publicId): array {
    $thread = $this->database->select('famtastic_portal_thread', 't')->fields('t')
      ->condition('public_id', $publicId)->execute()->fetchAssoc();
    if (!$thread || !$this->isMember($customerId, (int) $thread['organization_id'])) throw new \RuntimeException('Conversation not found.');
    $messages = $this->database->select('famtastic_portal_message', 'm')->fields('m', ['author_type', 'body', 'created'])
      ->condition('thread_id', $thread['id'])->orderBy('created')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    unset($thread['id'], $thread['organization_id'], $thread['created_by']);
    return ['thread' => $thread, 'messages' => $messages];
  }

  public function addMessage(int $customerId, string $publicId, string $body, int $uid): void {
    $thread = $this->database->select('famtastic_portal_thread', 't')->fields('t')->condition('public_id', $publicId)->execute()->fetchAssoc();
    if (!$thread || !$this->isMember($customerId, (int) $thread['organization_id'])) throw new \RuntimeException('Conversation not found.');
    $body = trim(strip_tags($body));
    if ($body === '') throw new \InvalidArgumentException('A message is required.');
    $now = $this->time->getRequestTime();
    $this->database->insert('famtastic_portal_message')->fields(['thread_id' => $thread['id'], 'author_uid' => $uid, 'author_type' => 'customer', 'body' => $body, 'created' => $now])->execute();
    $this->database->update('famtastic_portal_thread')->fields(['changed' => $now])->condition('id', $thread['id'])->execute();
    $this->database->update('famtastic_support_case')->fields(['status' => 'waiting_on_famtastic', 'changed' => $now])
      ->condition('thread_id', $thread['id'])->condition('status', 'resolved', '<>')->execute();
  }

  public function issueToken(int $customerId, string $email, string $purpose, array $payload = []): string {
    $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $now = $this->time->getRequestTime();
    $this->database->insert('famtastic_portal_token')->fields([
      'customer_id' => $customerId, 'email' => mb_strtolower($email), 'purpose' => $purpose,
      'token_hash' => hash('sha256', $raw), 'payload' => json_encode($payload),
      'expires_at' => $now + ($purpose === 'verify' ? 86400 : 3600), 'created' => $now,
    ])->execute();
    return $raw;
  }

  public function consumeToken(string $raw, string $purpose): ?array {
    $now = $this->time->getRequestTime();
    $row = $this->database->select('famtastic_portal_token', 't')->fields('t')
      ->condition('token_hash', hash('sha256', $raw))->condition('purpose', $purpose)
      ->condition('expires_at', $now, '>')->isNull('used_at')->execute()->fetchAssoc();
    if (!$row) return NULL;
    $this->database->update('famtastic_portal_token')->fields(['used_at' => $now])->condition('id', $row['id'])->execute();
    return $row;
  }

  private function authorizedOrganization(int $customerId, string $publicId): array {
    foreach ($this->organizations($customerId) as $organization) {
      if (hash_equals($organization['public_id'], $publicId)) return $organization;
    }
    throw new \RuntimeException('Workspace not found.');
  }

  private function isMember(int $customerId, int $organizationId): bool {
    return (bool) $this->database->select('famtastic_membership', 'm')->condition('customer_id', $customerId)
      ->condition('organization_id', $organizationId)->condition('status', 'active')->countQuery()->execute()->fetchField();
  }

  private function findProspect(string $email): ?int {
    $ids = $this->entities->getStorage('famtastic_prospect')->getQuery()->accessCheck(FALSE)
      ->condition('public_email', $email)->sort('id', 'DESC')->range(0, 1)->execute();
    return $ids ? (int) reset($ids) : NULL;
  }

  private function previewRegistrationKey(string $email): string {
    return hash('sha256', mb_strtolower(trim($email)));
  }

  private function claimProspectResources(int $organizationId, int $prospectId): void {
    foreach (['famtastic_order' => 'order', 'famtastic_project' => 'project'] as $entityType => $resourceType) {
      $ids = $this->entities->getStorage($entityType)->getQuery()->accessCheck(FALSE)->condition('prospect_ref', $prospectId)->execute();
      foreach ($ids as $id) $this->claimResource($organizationId, $resourceType, (int) $id);
    }
  }

  public function claimResource(int $organizationId, string $type, int $id): void {
    $this->database->merge('famtastic_customer_resource')->keys(['resource_type' => $type, 'resource_id' => $id])
      ->fields(['organization_id' => $organizationId, 'created' => $this->time->getRequestTime()])->execute();
  }

  private function serializeEntities(string $entityType, array $ids, array $fields): array {
    $out = [];
    foreach ($this->entities->getStorage($entityType)->loadMultiple($ids) as $entity) {
      $row = [];
      foreach ($fields as $field) $row[$field] = $entity->hasField($field) ? $entity->get($field)->value : NULL;
      $out[] = $row;
    }
    return $out;
  }

  /** Serializes the latest complete proof campaign attached to a project. */
  private function projectProofs(object $project): ?array {
    $prospectId = (int) $project->get('prospect_ref')->target_id;
    $request = $this->database->select('famtastic_project_request', 'r')->fields('r', ['public_id', 'proof_campaign_id', 'proof_review_status'])
      ->condition('project_id', (int) $project->id())->orderBy('changed', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if ($request && !in_array($request['proof_review_status'], ['customer_ready', 'notified', 'selected', 'revision_requested'], TRUE)) {
      return NULL;
    }
    $campaignQuery = $this->entities->getStorage('proof_campaign')->getQuery()->accessCheck(FALSE)
      ->condition('prospect_id', $prospectId)->sort('id', 'DESC')->range(0, 1);
    if ($request && !empty($request['proof_campaign_id'])) {
      $campaignQuery->condition('id', (int) $request['proof_campaign_id']);
    }
    $campaignIds = $campaignQuery->execute();
    if (!$campaignIds) return NULL;
    $campaign = $this->entities->getStorage('proof_campaign')->load(reset($campaignIds));
    $variantIds = $this->entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
      ->condition('campaign_id', (int) $campaign->id())->sort('direction_id')->execute();
    $variants = [];
    foreach ($this->entities->getStorage('proof_variant')->loadMultiple($variantIds) as $variant) {
      $variants[] = [
        'direction_id' => (string) $variant->get('direction_id')->value,
        'direction_name' => (string) $variant->get('direction_name')->value,
        'preview_url' => $request
          ? '/web/api/customer/website-requests/' . rawurlencode((string) $request['public_id']) . '/proofs/' . rawurlencode((string) $variant->get('direction_id')->value)
          : (string) $variant->get('preview_url')->value,
        'thumbnail_path' => (string) $variant->get('thumbnail_path')->value,
      ];
    }
    $directions = array_column($variants, 'direction_id');
    if ($directions !== ['a', 'b', 'c'] && $directions !== ['a', 'b', 'c', 'd', 'e', 'f']) return NULL;
    return [
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
      'generation_status' => (string) $campaign->get('generation_status')->value,
      'selected_variant' => (string) $campaign->get('selected_variant')->value,
      'variants' => $variants,
    ];
  }

  public function activity(int $organizationId, string $type, string $summary): void {
    $this->database->insert('famtastic_portal_activity')->fields(['organization_id' => $organizationId, 'event_type' => $type, 'summary' => $summary, 'created' => $this->time->getRequestTime()])->execute();
  }

  /** Publishes an account-owned proof set without entering marketing outreach. */
  public function markProjectProofReady(int $projectId, object $campaign, array $variants): void {
    if (!in_array(count($variants), [3, 6], TRUE)) throw new \RuntimeException('A complete three- or six-direction proof set is required.');
    $project = $this->entities->getStorage('famtastic_project')->load($projectId);
    if (!$project) throw new \RuntimeException('Project no longer exists.');
    $preview = (string) $variants[0]->get('preview_url')->value;
    $project->set('proof_url', $preview)->set('delivery_status', 'proof_ready')->set('approval_status', 'pending')->save();
    $this->recordProofVersion($projectId, $campaign, $variants);
    $resource = $this->database->select('famtastic_customer_resource', 'r')->fields('r')
      ->condition('resource_type', 'project')->condition('resource_id', $projectId)->execute()->fetchAssoc();
    if (!$resource) throw new \RuntimeException('Project is not attached to a customer workspace.');
    $organizationId = (int) $resource['organization_id'];
    $request = $this->database->select('famtastic_project_request', 'r')->fields('r')
      ->condition('project_id', $projectId)->orderBy('changed', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    $count = count($variants);
    if ($request) {
      $this->activity($organizationId, 'project.proofs_owner_review', $count . ' website concepts are awaiting FAMtastic owner review.');
      return;
    }
    $query = $this->database->select('famtastic_membership', 'm');
    $query->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $query->addField('c', 'email');
    $email = (string) $query->condition('m.organization_id', $organizationId)->condition('m.status', 'active')
      ->orderBy('m.id', 'ASC')->range(0, 1)->execute()->fetchField();
    $campaignId = (string) $campaign->get('campaign_id')->value;
    $this->activity($organizationId, 'project.proofs_ready', $count . ' website concepts are ready for your review.');
    if ($email !== '') {
      $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
      $this->queueNotification('project:' . $projectId . ':proofs:' . $campaignId . ':' . $count, 'transactional', $email,
        'Your FAMtastic website concepts are ready', "Review, compare, and select your {$count} concepts securely in your account:\n{$base}/portal/?section=projects\n\nUse the same email address that received this message.");
    }
  }

  public function queueNotification(string $key, string $category, string $recipient, string $subject, string $body): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key, 'category' => $category, 'recipient' => mb_strtolower($recipient), 'subject' => $subject, 'body' => $body,
      'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5, 'available_at' => $now, 'created' => $now, 'changed' => $now,
    ])->execute();
  }

  /** Queues an owner alert to the configured operations inbox. */
  public function queueOwnerAlert(string $key, string $subject, string $body): void {
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    $this->queueNotification($key, 'operational', $admin, $subject, $body);
  }

  /**
   * Records one immutable proof version whenever a complete proof set lands.
   *
   * The first delivered set is version 1 ('initial'); every set delivered after
   * a revision request is a new version sourced from the revision loop. Prior
   * rows are never mutated or deleted, which preserves version history in
   * storage (LEAD_TO_LAUNCH step 9). Re-delivering the same campaign for the
   * same project is idempotent and records nothing new.
   */
  public function recordProofVersion(int $projectId, object $campaign, array $variants): array {
    if (!in_array(count($variants), [3, 6], TRUE)) throw new \RuntimeException('A complete three- or six-direction proof set is required.');
    $project = $this->entities->getStorage('famtastic_project')->load($projectId);
    if (!$project) throw new \RuntimeException('Project no longer exists.');
    $campaignEntityId = (int) $campaign->id();
    $existing = $this->database->select('famtastic_proof_version', 'v')->fields('v')
      ->condition('project_id', $projectId)->condition('proof_campaign_id', $campaignEntityId)
      ->orderBy('version', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if ($existing) return $existing;
    $revisionNumber = max(0, (int) $project->get('revision_count')->value);
    $version = 1 + (int) $this->database->select('famtastic_proof_version', 'v')
      ->condition('project_id', $projectId)->countQuery()->execute()->fetchField();
    $request = $this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['selected_proof_direction'])
      ->condition('project_id', $projectId)->orderBy('changed', 'DESC')->range(0, 1)
      ->execute()->fetchAssoc();
    $checksum = hash('sha256', implode('|', array_map(
      static fn (object $variant): string => (string) $variant->get('preview_url')->value,
      $variants,
    )));
    $now = $this->time->getRequestTime();
    $row = [
      'version_key' => 'proof_version:' . $projectId . ':' . $campaignEntityId,
      'project_id' => $projectId,
      'prospect_id' => (int) $project->get('prospect_ref')->target_id ?: NULL,
      'version' => $version,
      'source' => $revisionNumber > 0 ? 'revision' : 'initial',
      'revision_number' => $revisionNumber,
      'proof_campaign_id' => $campaignEntityId,
      'campaign_key' => (string) $campaign->get('campaign_id')->value,
      'selected_direction' => (string) ($campaign->get('selected_variant')->value ?: ($request['selected_proof_direction'] ?? '')),
      'variant_count' => count($variants),
      'proof_url' => (string) $variants[0]->get('preview_url')->value,
      'artifact_checksum' => $checksum,
      'revision_notes' => (string) $project->get('revision_notes')->value,
      'recorded_at' => $now,
    ];
    try {
      $this->database->insert('famtastic_proof_version')->fields($row)->execute();
    }
    catch (\Throwable $e) {
      if (!$this->isDuplicateKey($e)) {
        throw $e;
      }
      $row = $this->database->select('famtastic_proof_version', 'v')->fields('v')
        ->condition('version_key', $row['version_key'])->execute()->fetchAssoc() ?: $row;
    }
    return $row;
  }

  /** Detects portable unique-key violations without hiding other failures. */
  private function isDuplicateKey(\Throwable $exception): bool {
    $message = $exception->getMessage();
    return str_contains($message, 'UNIQUE constraint failed')
      || str_contains($message, 'Duplicate entry')
      || str_contains($message, 'SQLSTATE[23000]');
  }

  private function contextualOffers(array $entitlements, array $projects): array {
    $types = array_column($entitlements, 'entitlement_type');
    $offers = [];
    if (!in_array('customer_analytics', $types, TRUE)) $offers[] = ['key' => 'analytics', 'title' => 'Growth Analytics', 'description' => 'Turn visits and leads into clear next actions.'];
    if ($projects) {
      $offers[] = ['key' => 'seo', 'title' => 'Search Growth', 'description' => 'Help more local customers discover your live site.'];
      $offers[] = ['key' => 'lead_automation', 'title' => 'Lead Follow-up', 'description' => 'Respond to new leads automatically while interest is high.'];
      $offers[] = ['key' => 'site_expansion', 'title' => 'Expand Your Website', 'description' => 'Add focused pages as your services grow.'];
    }
    return $offers;
  }
}
