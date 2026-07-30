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
 * Both the signature-verified webhook and the (stub-mode) simulate control call
 * this. Idempotency is enforced by recording processed Stripe event ids on the
 * order, so a re-delivered webhook never fulfills twice.
 */
class FulfillmentService {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Marks the order for a checkout session as paid.
   *
   * @return array{found:bool,newly_processed:bool,paid:bool,order:?\Drupal\famtastic_pipeline\Entity\Order}
   */
  public function markPaidBySession(string $sessionId, ?string $paymentIntent, string $eventId): array {
    $order = $this->loadOrderBySession($sessionId);
    if (!$order) {
      $this->logger->warning('Fulfillment: no order for session @s', ['@s' => $sessionId]);
      return ['found' => FALSE, 'newly_processed' => FALSE, 'paid' => FALSE, 'order' => NULL];
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
      $this->advanceProspect($order);
    }
    $order->save();

    $this->logger->info('Fulfillment: order @id paid (event @e).', ['@id' => $order->id(), '@e' => $eventId]);
    return ['found' => TRUE, 'newly_processed' => TRUE, 'paid' => TRUE, 'order' => $order];
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

}
