<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;
use Drupal\famtastic_pipeline\Entity\Order;

/**
 * Owns durable customer identity and organization-scoped portal data.
 */
final class CustomerPortalService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function customerForUid(int $uid): ?array {
    $row = $this->database->select('famtastic_customer', 'c')
      ->fields('c')->condition('uid', $uid)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function customerForEmail(string $email): ?array {
    $row = $this->database->select('famtastic_customer', 'c')->fields('c')
      ->condition('email', mb_strtolower(trim($email)))->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function createCustomer(UserInterface $user, array $input = []): array {
    if ($existing = $this->customerForUid((int) $user->id())) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $email = mb_strtolower(trim($user->getEmail()));
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
    $prospect = $this->entities->getStorage('famtastic_prospect')->create([
      'business_name' => $clean['business_name'] ?: $clean['project_name'],
      'public_email' => (string) $customer['email'], 'contact_name' => (string) $customer['display_name'],
      'contact_method' => 'email', 'contact_value' => (string) $customer['email'],
      'campaign' => 'customer_portal', 'source' => 'customer_portal', 'authorized' => TRUE,
      'confirmed_at' => $now, 'status' => $clean['status'] === 'submitted' ? 'lead' : 'new', 'owner_uid' => 1,
    ]);
    $prospect->save();
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
    $this->activity((int) $organization['id'], 'website_request.created', $clean['status'] === 'submitted' ? 'A new website request was submitted.' : 'A website request draft was saved.');
    if ($clean['status'] === 'submitted') $this->queueWebsiteRequestNotifications($id, $customer, $clean);
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
      $this->activity((int) $row['organization_id'], 'website_request.submitted', 'A website request was submitted for review.');
    }
    $updated = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $row['id'])->execute()->fetchAssoc();
    return $this->serializeWebsiteRequest($updated);
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
    $intake = ['schema_version' => 'website_discovery_v2'];
    foreach ([
      'primary_goal', 'secondary_goals', 'success_metrics', 'ideal_customer', 'customer_pain_points',
      'products_services', 'desired_actions', 'required_features', 'integrations', 'page_list',
      'content_status', 'copywriting_needs', 'photo_asset_status', 'brand_status', 'style_preferences',
      'reference_sites', 'competitors', 'seo_keywords', 'service_locations', 'business_hours',
      'contact_details', 'social_profiles', 'accessibility_needs', 'privacy_legal_needs',
      'ecommerce_details', 'product_count', 'shipping_pickup', 'booking_details', 'ai_agent_goals',
      'maintenance_needs', 'launch_timing', 'budget_context', 'decision_makers', 'notes',
    ] as $key) $intake[$key] = $text($key);
    $intake['page_count'] = max(1, min(100, (int) ($input['page_count'] ?? 1)));
    $intake['recommendation'] = $this->recommendWebsitePackage($type, $intake);
    if ($status === 'submitted' && ($intake['primary_goal'] === '' || $intake['products_services'] === '')) throw new \InvalidArgumentException('Add the primary goal and what the business sells before submitting.');
    return [
      'status' => $status, 'project_name' => $projectName, 'business_name' => $text('business_name', 255),
      'project_type' => $type, 'domain_choice' => $domain, 'existing_domain' => $text('existing_domain', 255),
      'recommendation_requested' => !empty($input['recommendation_requested']) ? 1 : 0, 'intake' => $intake,
    ];
  }

  /** Creates an explainable recommendation without turning every intake into $199. */
  private function recommendWebsitePackage(string $type, array $intake): array {
    $features = mb_strtolower(implode(' ', [
      $intake['required_features'], $intake['integrations'], $intake['ecommerce_details'],
      $intake['booking_details'], $intake['ai_agent_goals'],
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
    if ($intake['ai_agent_goals'] !== '') $addons[] = 'FAM-AI-AGENT';
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
    $row['recommended_sku'] = (string) ($recommendation['recommended_sku'] ?? '');
    if ($offer) $row['recommended_sku'] = (string) $offer['sku'];
    $row['direct_checkout_available'] = $row['status'] === 'submitted'
      && empty($recommendation['review_required'])
      && in_array($row['recommended_sku'], ['FAM-FOOT-199', 'FAM-BUSINESS-499'], TRUE);
    foreach (['id', 'organization_id', 'customer_id', 'prospect_id', 'commerce_order_id', 'intake_id', 'project_id', 'intake_data'] as $key) unset($row[$key]);
    return $row;
  }

  private function queueWebsiteRequestNotifications(int $id, array $customer, array $request): void {
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fritz.medine@gmail.com');
    $subject = 'Website request received — ' . $request['project_name'];
    $this->queueNotification("website-request:{$id}:customer", 'transactional', (string) $customer['email'], $subject,
      "We received your website request for {$request['project_name']}. Fritz will review it within 3 business days. You can continue or review it from your customer portal.");
    $this->queueNotification("website-request:{$id}:staff", 'operational', $admin, 'New portal website request — ' . $request['project_name'],
      "Customer: {$customer['display_name']}\nEmail: {$customer['email']}\nType: {$request['project_type']}\nReview in Drupal: /web/admin/famtastic/metric/website-requests");
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
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fritz.medine@gmail.com');
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

  public function activity(int $organizationId, string $type, string $summary): void {
    $this->database->insert('famtastic_portal_activity')->fields(['organization_id' => $organizationId, 'event_type' => $type, 'summary' => $summary, 'created' => $this->time->getRequestTime()])->execute();
  }

  private function queueNotification(string $key, string $category, string $recipient, string $subject, string $body): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key, 'category' => $category, 'recipient' => mb_strtolower($recipient), 'subject' => $subject, 'body' => $body,
      'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5, 'available_at' => $now, 'created' => $now, 'changed' => $now,
    ])->execute();
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
