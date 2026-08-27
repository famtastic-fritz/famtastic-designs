<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Commercial-email record for the verified_cold public-preview lane.
 *
 * It deliberately does not send. PublicPreviewDeliveryService remains the
 * bounded exact-ID dispatcher, while this service supplies the same durable
 * recipient, campaign, tracking, unsubscribe, footer, and provider-event
 * contract as campaign messages.
 */
class ColdProofCommercialMessageService {

  private const TEMPLATE_KEY = 'verified_cold_preview';
  private const TEMPLATE_VERSION = 1;

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
    private readonly OperationalLedger $ledger,
    private readonly OutreachMailer $mailer,
  ) {}

  /** Creates one frozen, compliant commercial-email record for owner review. */
  public function stage(array $delivery, Prospect $prospect, string $subject, string $coreBody, string $shareUrl): array {
    if ((string) ($delivery['source_lane'] ?? '') !== 'verified_cold') {
      throw new \InvalidArgumentException('Commercial cold-preview messaging requires the verified_cold source lane.');
    }
    $deliveryId = (int) ($delivery['id'] ?? 0);
    if ($deliveryId < 1) {
      throw new \InvalidArgumentException('Cold-preview commercial message requires a delivery ID.');
    }
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $this->ledger->isSuppressed($email)) {
      throw new \RuntimeException('The verified-cold recipient is invalid or suppressed.');
    }
    $campaign = $this->campaignForProspect($prospect);
    // Staging is review-only. Campaign approval is enforced at hold and exact
    // dispatch, allowing an owner to inspect a complete ten-lead batch before
    // deciding whether to approve that campaign for commercial delivery.
    $postal = $this->postalAddress();
    $messageKey = 'verified-cold-preview-v' . self::TEMPLATE_VERSION . ':' . $campaign['id'] . ':' . $deliveryId . ':' . (int) ($delivery['share_version'] ?? 1);
    if ($existing = $this->loadBy('message_key', $messageKey)) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $tracking = bin2hex(random_bytes(24));
    $unsubscribe = bin2hex(random_bytes(24));
    $body = $this->commercialBody($coreBody, $shareUrl, $tracking, $unsubscribe, $postal);
    $id = (int) $this->database->insert('famtastic_email_message')->fields([
      'message_key' => $messageKey,
      'prospect_id' => (int) $prospect->id(),
      'campaign_id' => (int) $campaign['id'],
      'recipient_hash' => $this->ledger->contactHash($email),
      'recipient_address' => $email,
      'from_address' => $this->mailer->fromAddress(),
      'template_key' => self::TEMPLATE_KEY,
      'template_version' => self::TEMPLATE_VERSION,
      'subject' => mb_substr(trim($subject), 0, 512),
      'body_snapshot' => $body,
      'proof_campaign_id' => (int) ($delivery['proof_campaign_id'] ?? 0) ?: NULL,
      'preview_delivery_id' => $deliveryId,
      // The click endpoint records attribution, then safely redirects here.
      'proof_url' => $shareUrl,
      'status' => 'staged',
      'tracking_key' => $tracking,
      'unsubscribe_key' => $unsubscribe,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $message = $this->load((int) $id);
    if (!$message) {
      throw new \RuntimeException('Cold-preview commercial message could not be reloaded.');
    }
    $this->ledger->recordEvent(
      'cold-proof.email.staged:' . $id,
      'email.staged',
      ['message_id' => $id, 'template' => self::TEMPLATE_KEY, 'template_version' => self::TEMPLATE_VERSION, 'preview_delivery_id' => $deliveryId],
      (int) $prospect->id(),
      (int) $campaign['id'],
    );
    return $message;
  }

  /** Moves only this staged commercial record to held after owner approval. */
  public function hold(int $deliveryId): void {
    $message = $this->messageForDelivery($deliveryId);
    if (!$message || !in_array((string) $message['status'], ['staged', 'held'], TRUE)) {
      throw new \RuntimeException('Verified-cold commercial message is not staged for this delivery.');
    }
    $this->assertCampaignApproved((int) $message['campaign_id']);
    // This idempotency is intentional: if the owner-gated outbox write is
    // interrupted after this transition, a repeat approval can complete it
    // without reopening or auto-dispatching the commercial message.
    if ($message['status'] === 'held') {
      return;
    }
    if ($this->database->update('famtastic_email_message')->fields([
      'status' => 'held', 'changed' => $this->time->getRequestTime(),
    ])->condition('id', (int) $message['id'])->condition('status', 'staged')->execute() !== 1) {
      throw new \RuntimeException('Verified-cold commercial message changed before owner hold.');
    }
    $this->ledger->recordEvent(
      'cold-proof.email.held:' . (int) $message['id'],
      'email.held',
      ['message_id' => (int) $message['id'], 'preview_delivery_id' => $deliveryId],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
    );
  }

  /** Atomically marks one exact held commercial record as being dispatched. */
  public function claim(int $deliveryId): void {
    $message = $this->messageForDelivery($deliveryId);
    if (!$message || $message['status'] !== 'held') {
      throw new \RuntimeException('Verified-cold commercial message is not held for this exact delivery.');
    }
    $this->assertCampaignApproved((int) $message['campaign_id']);
    if ($this->database->update('famtastic_email_message')->fields([
      'status' => 'dispatching', 'changed' => $this->time->getRequestTime(),
    ])->condition('id', (int) $message['id'])->condition('status', 'held')->execute() !== 1) {
      throw new \RuntimeException('Verified-cold commercial message changed before exact dispatch claim.');
    }
  }

  /**
   * Cancels only an unsent verified-cold record when its signed room is
   * revoked. This never touches a provider-accepted message: the share link
   * itself is invalidated by PublicPreviewDeliveryService, while the durable
   * delivery history remains accurate.
   */
  public function revoke(int $deliveryId, string $reason): void {
    $message = $this->messageForDelivery($deliveryId);
    if (!$message) {
      throw new \RuntimeException('Verified-cold commercial message is missing for this delivery.');
    }
    $status = (string) $message['status'];
    if ($status === 'cancelled') {
      return;
    }
    if (!in_array($status, ['staged', 'held'], TRUE)) {
      // Dispatching is blocked before this method is reached. Any accepted
      // mail remains historically true and cannot be made unsent.
      return;
    }
    if ($this->database->update('famtastic_email_message')->fields([
      'status' => 'cancelled', 'changed' => $this->time->getRequestTime(),
    ])->condition('id', (int) $message['id'])->condition('status', $status)->execute() !== 1) {
      throw new \RuntimeException('Verified-cold commercial message changed before revocation.');
    }
    $this->ledger->recordEvent(
      'cold-proof.email.cancelled:' . (int) $message['id'] . ':' . hash('sha256', $reason),
      'email.cancelled',
      ['message_id' => (int) $message['id'], 'preview_delivery_id' => $deliveryId, 'reason_hash' => hash('sha256', $reason)],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
    );
  }

  public function accepted(int $deliveryId, string $providerMessageId): void {
    $this->recordDispatchResult($deliveryId, 'sent', $providerMessageId, 'email.sent');
  }

  public function receiptUnknown(int $deliveryId, string $providerMessageId): void {
    $this->recordDispatchResult($deliveryId, 'receipt_unknown', $providerMessageId, 'email.receipt_unknown');
  }

  public function failed(int $deliveryId, string $error): void {
    $message = $this->messageForDelivery($deliveryId);
    if (!$message || $message['status'] !== 'dispatching') {
      return;
    }
    $this->setStatus((int) $message['id'], 'dispatch_failed');
    $this->ledger->recordEvent(
      'cold-proof.email.failed:' . (int) $message['id'] . ':' . hash('sha256', $error),
      'email.dispatch_failed',
      ['message_id' => (int) $message['id'], 'preview_delivery_id' => $deliveryId, 'error_hash' => hash('sha256', $error)],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
    );
  }

  private function recordDispatchResult(int $deliveryId, string $status, string $providerMessageId, string $eventType): void {
    $message = $this->messageForDelivery($deliveryId);
    if (!$message || $message['status'] !== 'dispatching') {
      throw new \RuntimeException('Verified-cold commercial message is not dispatching for this exact delivery.');
    }
    $now = $this->time->getRequestTime();
    $updated = $this->database->update('famtastic_email_message')->fields([
      'status' => $status,
      'provider' => 'cpanel_smtp',
      'provider_message_id' => $providerMessageId,
      'sent_at' => $now,
      'changed' => $now,
    ])->condition('id', (int) $message['id'])->condition('status', 'dispatching')->execute();
    if ($updated !== 1) {
      throw new \RuntimeException('Verified-cold commercial dispatch result could not be recorded safely.');
    }
    $this->ledger->recordEvent(
      'cold-proof.' . $eventType . ':' . $providerMessageId,
      $eventType,
      ['message_id' => (int) $message['id'], 'preview_delivery_id' => $deliveryId],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
      provider: 'cpanel_smtp',
      providerEventId: $providerMessageId,
    );
  }

  private function commercialBody(string $coreBody, string $shareUrl, string $tracking, string $unsubscribe, string $postal): string {
    $apiBase = $this->publicApiBase();
    $click = $apiBase . '/api/pipeline/email/click/' . $tracking;
    $unsub = $this->oneClickUnsubscribeUrlForKey($unsubscribe);
    // Keep the raw signed room URL only in the server-side proof_url column.
    // Every customer-facing CTA must cross the click ledger first.
    $body = str_replace($shareUrl, $click, trim($coreBody));
    if (str_contains($body, $shareUrl)) {
      throw new \RuntimeException('Verified-cold commercial body retained an untracked signed proof URL.');
    }
    return $body
      . "\n\nAdvertisement from FAMtastic Designs"
      . "\n\nWhy you are receiving this: we found your business contact information in a verified public source while researching businesses that may benefit from a stronger web presence."
      . "\n\nFAMtastic Designs\n{$postal}"
      . "\n\nTo stop receiving commercial email from us, unsubscribe here:\n{$unsub}";
  }

  /**
   * Returns the exact one-click URL for a bound verified-cold invitation.
   *
   * PublicPreviewDeliveryService supplies this only to the shared mailer for
   * the commercial lane.  It is deliberately derived from the same frozen
   * message record as the visible footer rather than from a caller-provided
   * URL.
   */
  public function oneClickUnsubscribeUrl(int $deliveryId): string {
    $message = $this->messageForDelivery($deliveryId);
    if (!$message) {
      throw new \RuntimeException('Verified-cold commercial message has no valid one-click unsubscribe key.');
    }
    $key = trim((string) ($message['unsubscribe_key'] ?? ''));
    if (preg_match('/^[a-f0-9]{48}$/', $key) !== 1) {
      throw new \RuntimeException('Verified-cold commercial message has no valid one-click unsubscribe key.');
    }
    return $this->oneClickUnsubscribeUrlForKey($key);
  }

  /** Builds the public /web one-click unsubscribe endpoint from an opaque key. */
  private function oneClickUnsubscribeUrlForKey(string $unsubscribeKey): string {
    if (preg_match('/^[a-f0-9]{48}$/', $unsubscribeKey) !== 1) {
      throw new \InvalidArgumentException('Verified-cold unsubscribe key is invalid.');
    }
    return $this->publicApiBase() . '/api/pipeline/email/unsubscribe/confirm/' . $unsubscribeKey;
  }

  private function campaignForProspect(Prospect $prospect): array {
    $key = trim((string) $prospect->get('campaign')->value);
    $row = $key === '' ? FALSE : $this->database->select('famtastic_campaign', 'c')->fields('c', ['id', 'status'])
      ->condition('campaign_key', $key)->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('Verified-cold commercial message requires an attributed campaign.');
    }
    return $row;
  }

  private function assertCampaignApproved(int $campaignId): void {
    $status = $this->database->select('famtastic_campaign', 'c')->fields('c', ['status'])
      ->condition('id', $campaignId)->range(0, 1)->execute()->fetchField();
    if ($status !== 'approved') {
      throw new \RuntimeException('Verified-cold commercial message campaign is not approved at dispatch time.');
    }
  }

  private function postalAddress(): string {
    $postal = trim(str_replace("\r", '', (string) (
      getenv('FAMTASTIC_OUTREACH_POSTAL_ADDRESS')
      ?: Settings::get('famtastic_outreach_postal_address', '')
    )));
    if ($postal === '') {
      throw new \RuntimeException('Verified-cold commercial email requires configured physical postal address.');
    }
    return mb_substr($postal, 0, 1000);
  }

  private function base(): string {
    return rtrim((string) (getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url') ?: 'https://famtasticdesigns.com'), '/');
  }

  /**
   * Resolves the public Drupal document root separately from the SPA origin.
   *
   * The public frontend lives at the origin while Drupal routes are mounted
   * below `/web`. An optional explicit API base is accepted only on the same
   * public origin and only at that document-root path, so an environment typo
   * cannot silently turn a commercial click or unsubscribe into an arbitrary
   * redirect endpoint.
   */
  private function publicApiBase(): string {
    $publicBase = $this->validatedPublicBase($this->base(), 'public base URL');
    $configured = trim((string) (
      getenv('FAMTASTIC_PUBLIC_API_BASE_URL')
      ?: $this->configFactory->get('famtastic_pipeline.settings')->get('public_api_base_url')
      ?: ''
    ));
    if ($configured === '') {
      $path = rtrim((string) (parse_url($publicBase, PHP_URL_PATH) ?: ''), '/');
      if ($path === '') {
        return $publicBase . '/web';
      }
      if ($path === '/web') {
        return $publicBase;
      }
      throw new \RuntimeException('Verified-cold commercial email requires FAMTASTIC_PUBLIC_API_BASE_URL when the public base URL has a path other than /web.');
    }

    $apiBase = $this->validatedPublicBase($configured, 'public API base URL');
    $public = parse_url($publicBase);
    $api = parse_url($apiBase);
    if (
      strtolower((string) ($public['scheme'] ?? '')) !== strtolower((string) ($api['scheme'] ?? ''))
      || strtolower((string) ($public['host'] ?? '')) !== strtolower((string) ($api['host'] ?? ''))
      || (int) ($public['port'] ?? 0) !== (int) ($api['port'] ?? 0)
      || rtrim((string) ($api['path'] ?? ''), '/') !== '/web'
    ) {
      throw new \RuntimeException('Verified-cold public API base must use the public site origin and end exactly at /web.');
    }
    return $apiBase;
  }

  private function validatedPublicBase(string $value, string $label): string {
    $value = rtrim(trim($value), '/');
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
      throw new \RuntimeException('Verified-cold ' . $label . ' must be an absolute http(s) URL.');
    }
    $parts = parse_url($value);
    if (
      !is_array($parts)
      || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], TRUE)
      || trim((string) ($parts['host'] ?? '')) === ''
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
    ) {
      throw new \RuntimeException('Verified-cold ' . $label . ' must contain only a scheme, host, optional port, and path.');
    }
    return $value;
  }

  private function messageForDelivery(int $deliveryId): ?array {
    $messageId = $this->database->select('famtastic_preview_delivery', 'p')->fields('p', ['commercial_message_id'])
      ->condition('id', $deliveryId)->range(0, 1)->execute()->fetchField();
    if (!$messageId) {
      return NULL;
    }
    return $this->database->select('famtastic_email_message', 'm')->fields('m')
      ->condition('id', (int) $messageId)
      ->condition('preview_delivery_id', $deliveryId)
      ->condition('template_key', self::TEMPLATE_KEY)
      ->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  private function load(int $id): ?array {
    return $this->loadBy('id', $id);
  }

  private function loadBy(string $field, string|int $value): ?array {
    return $this->database->select('famtastic_email_message', 'm')->fields('m')
      ->condition($field, $value)->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  private function setStatus(int $id, string $status): void {
    $this->database->update('famtastic_email_message')->fields([
      'status' => $status,
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', $id)->execute();
  }

}
