<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Order;
use Psr\Log\LoggerInterface;

/**
 * Advances an order to paid — the single fulfillment code path.
 *
 * Both the signature-verified webhook and the explicitly enabled test simulator call
 * this. Idempotency is enforced by recording processed Stripe event ids on the
 * order, so a re-delivered webhook never fulfills twice.
 */
class FulfillmentService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
    protected ProofCampaignService $proofCampaigns,
    protected OperationalLedger $ledger,
    protected CustomerPortalService $customerPortal,
  ) {}

  /**
   * Marks the order for a checkout session as paid.
   *
   * @return array{found:bool,newly_processed:bool,paid:bool,order:?\Drupal\famtastic_pipeline\Entity\Order}
   */
  public function markPaidBySession(
    string $sessionId,
    ?string $paymentIntent,
    string $eventId,
    ?int $amountTotal = NULL,
    ?string $currency = NULL,
  ): array {
    $order = $this->loadOrderBySession($sessionId);
    if (!$order) {
      $this->logger->warning('Fulfillment: no order for session @s', ['@s' => $sessionId]);
      return ['found' => FALSE, 'newly_processed' => FALSE, 'paid' => FALSE, 'order' => NULL];
    }
    if ($amountTotal !== NULL && $amountTotal !== (int) $order->get('amount')->value) {
      $this->logger->error('Fulfillment amount mismatch for order @id.', ['@id' => $order->id()]);
      return ['found' => TRUE, 'newly_processed' => FALSE, 'paid' => FALSE, 'order' => $order, 'error' => 'amount_mismatch'];
    }
    if ($currency !== NULL && strtolower($currency) !== strtolower((string) $order->get('currency')->value)) {
      $this->logger->error('Fulfillment currency mismatch for order @id.', ['@id' => $order->id()]);
      return ['found' => TRUE, 'newly_processed' => FALSE, 'paid' => FALSE, 'order' => $order, 'error' => 'currency_mismatch'];
    }

    // Idempotency: duplicate event id is a no-op.
    if (!$order->markEventProcessed($eventId)) {
      return ['found' => TRUE, 'newly_processed' => FALSE, 'paid' => $order->isPaid(), 'order' => $order];
    }

    // Only transition to paid once.
    if (!$order->isPaid()) {
      $order->set('payment_status', 'paid');
      $order->set('paid_at', $this->time->getRequestTime());
      if ($paymentIntent) {
        $order->set('stripe_payment_intent_id', $paymentIntent);
      }
      if ($order->get('package')->value === 'revision_addon_75') {
        $this->fulfillRevisionAddOn($order);
      }
      else {
        $this->advanceProspect($order);
      }
    }
    $order->save();
    $this->customerPortal->syncPaidOrder($order);
    $prospect = $order->get('prospect_ref')->entity;
    $this->ledger->recordEvent(
      'payment.verified:' . $eventId,
      'payment.verified',
      [
        'order_id' => (int) $order->id(),
        'package' => $order->get('package')->value,
        'amount_minor' => (int) $order->get('amount')->value,
        'currency' => $order->get('currency')->value,
      ],
      $prospect ? (int) $prospect->id() : NULL,
      orderId: (int) $order->id(),
      provider: 'stripe',
      providerEventId: $eventId,
    );

    $this->logger->info('Fulfillment: order @id paid (event @e).', ['@id' => $order->id(), '@e' => $eventId]);
    return ['found' => TRUE, 'newly_processed' => TRUE, 'paid' => TRUE, 'order' => $order];
  }

  /**
   * Marks a proof campaign converted after a successful payment.
   *
   * Called by the Stripe webhook when the checkout session metadata carries a
   * campaign_id, and by the stub-mode simulator for the prospect's active
   * selection. The existing intake-unlock flow is untouched.
   */
  public function markProofCampaignConverted(string $campaignId, ?string $sessionId = NULL): bool {
    return $this->proofCampaigns->markConverted($campaignId, $sessionId);
  }

  /**
   * Loads an order by its Stripe checkout session id.
   */
  protected function loadOrderBySession(string $sessionId): ?Order {
    $storage = $this->entityTypeManager->getStorage('famtastic_order');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('stripe_checkout_session_id', $sessionId)
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Moves the prospect to 'paid' if it has not progressed past that.
   */
  protected function advanceProspect(Order $order): void {
    $prospect = $order->get('prospect_ref')->entity;
    if (!$prospect) {
      return;
    }
    $terminal = ['paid', 'intake_started', 'intake_complete', 'submitted_to_studio', 'proof_ready', 'revision_requested', 'approved', 'launched'];
    if (!in_array($prospect->get('status')->value, $terminal, TRUE)) {
      $prospect->set('status', 'paid');
      $prospect->save();
    }
  }

  /**
   * Adds exactly one revision allowance after verified add-on payment.
   */
  protected function fulfillRevisionAddOn(Order $order): void {
    $prospect = $order->get('prospect_ref')->entity;
    if (!$prospect) {
      throw new \RuntimeException('Revision add-on order has no prospect.');
    }
    $storage = $this->entityTypeManager->getStorage('famtastic_project');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('prospect_ref', $prospect->id())
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    $project = $ids ? $storage->load(reset($ids)) : NULL;
    if (!$project || $project->get('approval_status')->value !== 'revision_requested') {
      throw new \RuntimeException('Revision add-on has no eligible project.');
    }
    $oldLimit = max(1, (int) $project->get('revision_limit')->value);
    $project->set('revision_limit', $oldLimit + 1);
    $project->save();
    $this->ledger->recordEvent(
      'revision_addon.fulfilled:' . $order->id(),
      'revision_addon.fulfilled',
      [
        'order_id' => (int) $order->id(),
        'old_revision_limit' => $oldLimit,
        'new_revision_limit' => $oldLimit + 1,
      ],
      (int) $prospect->id(),
      orderId: (int) $order->id(),
      projectId: (int) $project->id(),
    );
    $contact = (string) ($prospect->get('contact_value')->value ?: $prospect->get('public_email')->value);
    if ($contact !== '') {
      $this->customerPortal->queueNotification(
        'revision_addon:' . $order->id() . ':customer-receipt',
        'transactional',
        $contact,
        'Your additional revision round is confirmed',
        sprintf(
          "Payment received for one additional revision round on your website project.\nYour project now includes %d revision rounds. FAMtastic will deliver the updated proof for your review.\n",
          $oldLimit + 1,
        ),
      );
    }
    $this->customerPortal->queueOwnerAlert(
      'revision_addon:' . $order->id() . ':staff-sale',
      'Additional revision purchased — project #' . $project->id(),
      sprintf(
        "A revision add-on was purchased and fulfilled.\nProject: #%d\nProspect: #%d\nOrder: #%d\nRevision allowance: %d → %d\nDeliver the updated proof for customer review.",
        (int) $project->id(),
        (int) $prospect->id(),
        (int) $order->id(),
        $oldLimit,
        $oldLimit + 1,
      ),
    );
  }

}
