<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/**
 * Controls included hosting and separately authorized month-13 billing.
 */
final class HostingLifecycleService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
  ) {}

  public function activate(int $projectId): array {
    $existing = $this->loadEntitlementByProject($projectId);
    if ($existing) {
      return $existing;
    }
    $project = $this->entityTypeManager->getStorage('famtastic_project')->load($projectId);
    $order = $project?->get('order_ref')->entity;
    if (!$project || !$order || !$order->isPaid()) {
      throw new \RuntimeException('Included hosting requires a paid project order.');
    }
    $domainReady = (bool) $this->database->select('famtastic_domain', 'd')
      ->condition('project_id', $projectId)
      ->condition('dns_status', 'verified')
      ->condition('ssl_status', 'verified')
      ->countQuery()
      ->execute()
      ->fetchField();
    if (!$domainReady) {
      throw new \RuntimeException('Included hosting starts only after DNS and SSL verification.');
    }
    $starts = $this->time->getRequestTime();
    $includedUntil = (new \DateTimeImmutable('@' . $starts))->modify('+12 months')->getTimestamp();
    $id = (int) $this->database->insert('famtastic_hosting_entitlement')
      ->fields([
        'project_id' => $projectId,
        'order_id' => (int) $order->id(),
        'status' => 'included',
        'starts_at' => $starts,
        'included_until' => $includedUntil,
        'renews_at' => $includedUntil,
        'created' => $starts,
        'changed' => $starts,
      ])
      ->execute();
    $this->ledger->recordEvent(
      'hosting.activated:project:' . $projectId,
      'hosting.included_started',
      ['entitlement_id' => $id, 'included_until' => $includedUntil, 'months' => 12],
      orderId: (int) $order->id(),
      projectId: $projectId,
    );
    return $this->loadEntitlement($id);
  }

  /**
   * Records separate recurring consent and creates a non-live subscription.
   */
  public function authorizeRecurring(int $entitlementId, string $customerEmail, int $amountMinor, array $evidence): array {
    if ($amountMinor <= 0) {
      throw new \InvalidArgumentException('Recurring hosting amount must be positive.');
    }
    $entitlement = $this->loadEntitlement($entitlementId);
    $project = $this->entityTypeManager->getStorage('famtastic_project')->load($entitlement['project_id']);
    $prospectId = $project?->get('prospect_ref')->target_id;
    $consentId = $this->ledger->recordConsent(
      $customerEmail,
      'accepted',
      $prospectId ? (int) $prospectId : NULL,
      'recurring_hosting',
      evidence: $evidence + [
        'entitlement_id' => $entitlementId,
        'amount_minor' => $amountMinor,
        'currency' => 'usd',
        'billing_interval' => 'month',
        'starts_at' => (int) $entitlement['renews_at'],
      ],
    );
    $provider = getenv('FAMTASTIC_HOSTING_BILLING_PROVIDER') ?: Settings::get('famtastic_hosting_billing_provider', 'disabled');
    if ($provider !== 'memory') {
      throw new \RuntimeException('Live recurring billing is disabled; only the acceptance-test memory provider is configured.');
    }
    $existing = $this->database->select('famtastic_subscription', 's')
      ->fields('s')
      ->condition('entitlement_id', $entitlementId)
      ->execute()
      ->fetchAssoc();
    if ($existing) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_subscription')
      ->fields([
        'entitlement_id' => $entitlementId,
        'provider' => 'memory',
        'provider_customer_id' => 'cus_test_' . $consentId,
        'provider_subscription_id' => 'sub_test_' . $entitlementId,
        'status' => 'scheduled',
        'amount_minor' => $amountMinor,
        'currency' => 'usd',
        'retry_count' => 0,
        'next_attempt_at' => (int) $entitlement['renews_at'],
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
    $this->ledger->recordEvent(
      'hosting.recurring_authorized:' . $id,
      'hosting.recurring_authorized',
      ['subscription_id' => $id, 'consent_id' => $consentId, 'amount_minor' => $amountMinor],
      projectId: (int) $entitlement['project_id'],
    );
    return $this->loadSubscription($id);
  }

  /**
   * Simulates an authoritative provider renewal result for acceptance testing.
   */
  public function processRenewal(int $subscriptionId, bool $paid, int $asOf): array {
    $subscription = $this->loadSubscription($subscriptionId);
    $entitlement = $this->loadEntitlement((int) $subscription['entitlement_id']);
    if ($asOf < (int) $entitlement['renews_at']) {
      throw new \RuntimeException('Recurring billing cannot begin during the included hosting year.');
    }
    if ($subscription['provider'] !== 'memory') {
      throw new \RuntimeException('Synthetic renewal results are forbidden for live providers.');
    }
    $retryCount = (int) $subscription['retry_count'];
    if ($paid) {
      $next = (new \DateTimeImmutable('@' . $asOf))->modify('+1 month')->getTimestamp();
      $this->database->update('famtastic_subscription')->fields([
        'status' => 'active',
        'retry_count' => 0,
        'next_attempt_at' => $next,
        'changed' => $asOf,
      ])->condition('id', $subscriptionId)->execute();
      $this->database->update('famtastic_hosting_entitlement')->fields([
        'status' => 'recurring',
        'renews_at' => $next,
        'changed' => $asOf,
      ])->condition('id', $entitlement['id'])->execute();
      $eventType = 'hosting.renewal_paid';
    }
    else {
      $retryCount++;
      $exhausted = $retryCount >= 3;
      $next = $exhausted ? NULL : $asOf + (86400 * (2 ** $retryCount));
      $this->database->update('famtastic_subscription')->fields([
        'status' => $exhausted ? 'canceled' : 'past_due',
        'retry_count' => $retryCount,
        'next_attempt_at' => $next,
        'cancel_at' => $exhausted ? $asOf : NULL,
        'changed' => $asOf,
      ])->condition('id', $subscriptionId)->execute();
      $this->database->update('famtastic_hosting_entitlement')->fields([
        'status' => $exhausted ? 'suspended' : 'past_due',
        'suspended_at' => $exhausted ? $asOf : NULL,
        'changed' => $asOf,
      ])->condition('id', $entitlement['id'])->execute();
      $eventType = $exhausted ? 'hosting.suspended' : 'hosting.renewal_failed';
    }
    $this->ledger->recordEvent(
      $eventType . ':' . $subscriptionId . ':' . $asOf,
      $eventType,
      ['subscription_id' => $subscriptionId, 'retry_count' => $paid ? 0 : $retryCount],
      projectId: (int) $entitlement['project_id'],
      occurredAt: $asOf,
    );
    return $this->loadSubscription($subscriptionId);
  }

  private function loadEntitlementByProject(int $projectId): ?array {
    $record = $this->database->select('famtastic_hosting_entitlement', 'h')
      ->fields('h')->condition('project_id', $projectId)->execute()->fetchAssoc();
    return $record ?: NULL;
  }

  private function loadEntitlement(int $id): array {
    $record = $this->database->select('famtastic_hosting_entitlement', 'h')
      ->fields('h')->condition('id', $id)->execute()->fetchAssoc();
    if (!$record) {
      throw new \InvalidArgumentException('Unknown hosting entitlement.');
    }
    return $record;
  }

  private function loadSubscription(int $id): array {
    $record = $this->database->select('famtastic_subscription', 's')
      ->fields('s')->condition('id', $id)->execute()->fetchAssoc();
    if (!$record) {
      throw new \InvalidArgumentException('Unknown hosting subscription.');
    }
    return $record;
  }

}
