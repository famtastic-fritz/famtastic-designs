<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    $projects = $this->serializeEntities('famtastic_project', $resourceIds('project'), [
      'uuid', 'label', 'proof_url', 'live_url', 'delivery_status', 'approval_status', 'revision_count', 'revision_limit', 'created', 'changed',
    ]);
    $entitlements = $this->database->select('famtastic_entitlement', 'e')->fields('e')
      ->condition('organization_id', $organizationId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $threads = $this->database->select('famtastic_portal_thread', 't')
      ->fields('t', ['public_id', 'project_id', 'kind', 'subject', 'status', 'created', 'changed'])
      ->condition('organization_id', $organizationId)->orderBy('changed', 'DESC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
      'threads' => $threads, 'activity' => $activity, 'members' => $members,
      'analytics' => ['entitled' => (bool) $analytics],
      'offers' => $this->contextualOffers($entitlements, $projects),
    ];
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
    $this->activity((int) $organization['id'], 'support.created', 'A new support conversation was opened.');
    return ['public_id' => $publicId, 'subject' => $subject, 'status' => 'open', 'created' => $now];
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

  private function claimResource(int $organizationId, string $type, int $id): void {
    $this->database->merge('famtastic_customer_resource')->key(['resource_type' => $type, 'resource_id' => $id])
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

  private function activity(int $organizationId, string $type, string $summary): void {
    $this->database->insert('famtastic_portal_activity')->fields(['organization_id' => $organizationId, 'event_type' => $type, 'summary' => $summary, 'created' => $this->time->getRequestTime()])->execute();
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
