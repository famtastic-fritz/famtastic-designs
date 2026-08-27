<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Stages and sends attributable campaign email behind an explicit gate.
 */
class CampaignMessageService {

  private const TEMPLATE_VERSION = 2;
  private const TEMPLATE_VERSION_ENHANCED = 3;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OperationalLedger $ledger,
    private readonly TokenManager $tokens,
    private readonly OutreachMailer $mailer,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly PilotExactDispatchLock $pilotExactDispatchLock,
  ) {}

  /**
   * Creates one idempotent staged proof-ready message.
   */
  public function prepare(Prospect $prospect, int $proofCampaignId): array {
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('Prospect has no valid outreach email.');
    }
    if ($this->ledger->isSuppressed($email)) {
      throw new \RuntimeException('Prospect email is suppressed.');
    }
    $campaignId = $this->campaignId((string) $prospect->get('campaign')->value);
    if (!$campaignId) {
      throw new \RuntimeException('Prospect campaign attribution is missing.');
    }
    $messageKey = sprintf('proof-ready-v%d:%d:%d:%d', self::TEMPLATE_VERSION, $campaignId, $prospect->id(), $proofCampaignId);
    $existing = $this->loadBy('message_key', $messageKey);
    if ($existing) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_email_message')
      ->fields([
        'message_key' => $messageKey,
        'prospect_id' => (int) $prospect->id(),
        'campaign_id' => $campaignId,
        'recipient_hash' => $this->ledger->contactHash($email),
        'recipient_address' => $email,
        'proof_campaign_id' => $proofCampaignId,
        'template_key' => 'proof_ready',
        'template_version' => self::TEMPLATE_VERSION,
        'subject' => sprintf('Three website directions for %s', $prospect->label()),
        'status' => 'staged',
        'tracking_key' => bin2hex(random_bytes(24)),
        'unsubscribe_key' => bin2hex(random_bytes(24)),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
    $this->ledger->recordEvent(
      'email.staged:' . $id,
      'email.staged',
      ['message_id' => $id, 'template' => 'proof_ready', 'template_version' => self::TEMPLATE_VERSION],
      (int) $prospect->id(),
      $campaignId,
    );
    return $this->load($id);
  }

  /**
   * Creates one idempotent staged ENHANCED proof-ready message (v3).
   *
   * Enhanced messages include:
   * - Personalized research-based hook
   * - Named proof directions
   * - Direct /p/{token} ProofHub link
   * - $199 offer context
   */
  public function prepareEnhanced(Prospect $prospect, int $proofCampaignId): array {
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('Prospect has no valid outreach email.');
    }
    if ($this->ledger->isSuppressed($email)) {
      throw new \RuntimeException('Prospect email is suppressed.');
    }
    $campaignId = $this->campaignId((string) $prospect->get('campaign')->value);
    if (!$campaignId) {
      throw new \RuntimeException('Prospect campaign attribution is missing.');
    }
    $messageKey = sprintf('proof-ready-v%d:%d:%d:%d', self::TEMPLATE_VERSION_ENHANCED, $campaignId, $prospect->id(), $proofCampaignId);
    $existing = $this->loadBy('message_key', $messageKey);
    if ($existing) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_email_message')
      ->fields([
        'message_key' => $messageKey,
        'prospect_id' => (int) $prospect->id(),
        'campaign_id' => $campaignId,
        'recipient_hash' => $this->ledger->contactHash($email),
        'recipient_address' => $email,
        'proof_campaign_id' => $proofCampaignId,
        'template_key' => 'proof_ready_enhanced',
        'template_version' => self::TEMPLATE_VERSION_ENHANCED,
        'subject' => sprintf('Three website directions for %s — pick your favorite', $prospect->label()),
        'status' => 'staged',
        'tracking_key' => bin2hex(random_bytes(24)),
        'unsubscribe_key' => bin2hex(random_bytes(24)),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
    $this->ledger->recordEvent(
      'email.staged:' . $id,
      'email.staged',
      ['message_id' => $id, 'template' => 'proof_ready_enhanced', 'template_version' => self::TEMPLATE_VERSION_ENHANCED],
      (int) $prospect->id(),
      $campaignId,
    );
    return $this->load($id);
  }

  /**
   * Queues staged messages only after the campaign is approved.
   */
  public function queueApprovedCampaign(string $campaignKey): int {
    $campaign = $this->database->select('famtastic_campaign', 'c')
      ->fields('c')
      ->condition('campaign_key', $campaignKey)
      ->execute()
      ->fetchAssoc();
    if (!$campaign || $campaign['status'] !== 'approved') {
      throw new \RuntimeException('Campaign must be explicitly approved before messages can be queued.');
    }
    $ids = $this->database->select('famtastic_email_message', 'm')
      ->fields('m', ['id', 'prospect_id'])
      ->condition('campaign_id', $campaign['id'])
      ->condition('status', 'staged')
      // Verified cold proof rooms have their own owner-approved exact-ID
      // dispatcher. They may never leak into this broad campaign queue.
      ->condition('template_key', 'verified_cold_preview', '<>')
      ->execute()
      ->fetchAllKeyed();
    foreach ($ids as $messageId => $prospectId) {
      $this->ledger->enqueue(
        'outreach.send:message:' . $messageId,
        'outreach.send',
        ['message_id' => (int) $messageId],
        (int) $prospectId,
      );
      $this->database->update('famtastic_email_message')
        ->fields(['status' => 'queued', 'changed' => $this->time->getRequestTime()])
        ->condition('id', $messageId)
        ->execute();
    }
    return count($ids);
  }

  /**
   * Sends through memory transport or the explicitly enabled real transport.
   */
  public function send(int $messageId): array {
    // This is the generic campaign mail boundary. The owner-approved
    // PublicPreviewDeliveryService exact-ID dispatcher intentionally uses its
    // own narrow path, so the pilot lock cannot be bypassed through direct
    // CampaignMessageService calls without disabling unrelated Drupal mail.
    if ($this->pilotExactDispatchLock->isActive()) {
      throw new \RuntimeException('Pilot exact-dispatch lock is active; generic campaign email send is disabled.');
    }
    $message = $this->load($messageId);
    if (!$message) {
      throw new \RuntimeException('Email message does not exist.');
    }
    if ((string) ($message['template_key'] ?? '') === 'verified_cold_preview') {
      throw new \RuntimeException('Verified-cold commercial previews must use the exact-ID public preview dispatcher.');
    }
    if (in_array((string) $message['status'], ['quarantined', 'suppressed'], TRUE)) {
      throw new \RuntimeException('This campaign message is quarantined or suppressed and cannot be sent.');
    }
    if (in_array($message['status'], ['sent', 'delivered', 'opened', 'clicked'], TRUE)) {
      return $message;
    }
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
    if (!$prospect) {
      throw new \RuntimeException('Prospect no longer exists.');
    }
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if ($this->ledger->isSuppressed($email)) {
      $this->setStatus($messageId, 'suppressed');
      throw new \RuntimeException('Message suppressed before send.');
    }
    $campaignStatus = $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['status'])
      ->condition('id', $message['campaign_id'])
      ->execute()
      ->fetchField();
    if ($campaignStatus !== 'approved') {
      throw new \RuntimeException('Campaign is not approved at send time.');
    }
    $transport = getenv('FAMTASTIC_EMAIL_TRANSPORT') ?: Settings::get('famtastic_email_transport', 'disabled');
    if (!in_array($transport, ['memory', 'real'], TRUE)) {
      throw new \RuntimeException('Outreach transport is disabled.');
    }
    if ($transport === 'real') {
      $allowed = filter_var(
        getenv('FAMTASTIC_ALLOW_REAL_OUTREACH') ?: Settings::get('famtastic_allow_real_outreach', FALSE),
        FILTER_VALIDATE_BOOL,
      );
      if (!$allowed) {
        throw new \RuntimeException('Real outreach requires explicit environment approval.');
      }
      $base = rtrim((string) (getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: 'https://famtasticdesigns.com'), '/');
      $postalAddress = trim((string) (
        getenv('FAMTASTIC_OUTREACH_POSTAL_ADDRESS')
        ?: Settings::get('famtastic_outreach_postal_address', '')
      ));
      if ($postalAddress === '') {
        throw new \RuntimeException('Real outreach requires a valid physical postal address.');
      }
      $postalAddress = str_replace("\r", '', $postalAddress);
      $isEnhanced = (int) ($message['template_version'] ?? 0) === self::TEMPLATE_VERSION_ENHANCED;
      $body = $isEnhanced
        ? $this->messageBodyEnhanced($prospect, $message, $postalAddress, $base)
        : $this->messageBody($prospect, $message, $postalAddress, $base);
      $proofUrl = $base . '/api/pipeline/email/click/' . $message['tracking_key'];
      $this->database->update('famtastic_email_message')
        ->fields([
          'recipient_address' => $email,
          'from_address' => $this->mailer->fromAddress(),
          'body_snapshot' => $body,
          'proof_url' => $proofUrl,
          'changed' => $this->time->getRequestTime(),
        ])
        ->condition('id', $messageId)
        ->execute();
      $providerMessageId = $this->mailer->send($email, $message['subject'], $body);
      $provider = 'cpanel_smtp';
    }
    else {
      $provider = 'memory';
    }
    $now = $this->time->getRequestTime();
    $providerMessageId ??= $provider . '_' . $messageId . '_' . bin2hex(random_bytes(8));
    $this->database->update('famtastic_email_message')
      ->fields([
        'status' => $transport === 'memory' ? 'delivered' : 'sent',
        'provider' => $provider,
        'provider_message_id' => $providerMessageId,
        'sent_at' => $now,
        'delivered_at' => $transport === 'memory' ? $now : NULL,
        'changed' => $now,
      ])
      ->condition('id', $messageId)
      ->execute();
    $this->ledger->recordEvent(
      'email.sent:' . $providerMessageId,
      'email.sent',
      ['message_id' => $messageId, 'provider' => $provider],
      (int) $prospect->id(),
      (int) $message['campaign_id'],
      provider: $provider,
      providerEventId: $providerMessageId,
    );
    if ($transport === 'memory') {
      $this->ledger->recordEvent(
        'email.delivered:' . $providerMessageId,
        'email.delivered',
        ['message_id' => $messageId],
        (int) $prospect->id(),
        (int) $message['campaign_id'],
        provider: $provider,
        providerEventId: $providerMessageId . ':delivered',
      );
    }
    return $this->load($messageId);
  }

  /**
   * Backfills exact snapshots for a previously sent, explicitly named batch.
   */
  public function backfillCampaignSnapshots(string $campaignKey, string $postalAddress, ?string $base = NULL): int {
    $postalAddress = trim(str_replace("\r", '', $postalAddress));
    if ($postalAddress === '') {
      throw new \InvalidArgumentException('A physical postal address is required.');
    }
    $campaignId = $this->campaignId($campaignKey);
    if (!$campaignId) {
      throw new \InvalidArgumentException('Campaign does not exist.');
    }
    $base = rtrim((string) ($base ?: getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: 'https://famtasticdesigns.com'), '/');
    $messages = $this->database->select('famtastic_email_message', 'm')
      ->fields('m')
      ->condition('campaign_id', $campaignId)
      ->condition('sent_at', 0, '>')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $updated = 0;
    foreach ($messages as $message) {
      $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load((int) $message['prospect_id']);
      if (!$prospect) {
        continue;
      }
      $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
      $proofIds = $this->entityTypeManager->getStorage('proof_campaign')->getQuery()
        ->accessCheck(FALSE)
        ->condition('prospect_id', (int) $prospect->id())
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      $this->database->update('famtastic_email_message')
        ->fields([
          'recipient_address' => $email,
          'from_address' => $this->mailer->fromAddress(),
          'body_snapshot' => $this->messageBody($prospect, $message, $postalAddress, $base),
          'proof_campaign_id' => $proofIds ? (int) reset($proofIds) : NULL,
          'proof_url' => $base . '/api/pipeline/email/click/' . $message['tracking_key'],
          'changed' => $this->time->getRequestTime(),
        ])
        ->condition('id', (int) $message['id'])
        ->execute();
      $updated++;
    }
    return $updated;
  }

  /**
   * Persists one idempotent signed provider lifecycle event.
   */
  public function providerEvent(string $eventId, string $providerMessageId, string $type, array $payload): bool {
    if ($eventId === '' || strlen($eventId) > 255 || $providerMessageId === '') {
      throw new \InvalidArgumentException('Provider event id and message id are required.');
    }
    $allowed = ['delivered', 'bounced', 'complained'];
    if (!in_array($type, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Unsupported provider event type.');
    }
    $message = $this->loadBy('provider_message_id', $providerMessageId);
    if (!$message) {
      throw new \InvalidArgumentException('Unknown provider message.');
    }
    $isNew = $this->ledger->recordEvent(
      'email.provider:' . $eventId,
      'email.' . $type,
      ['message_id' => (int) $message['id'], 'provider_payload' => $payload],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
      provider: (string) $message['provider'],
      providerEventId: $eventId,
    );
    if (!$isNew) {
      return FALSE;
    }
    $this->setStatus((int) $message['id'], $type);
    if (in_array($type, ['bounced', 'complained'], TRUE)) {
      $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
      if ($prospect && ($email = (string) $prospect->get('public_email')->value)) {
        $this->ledger->recordConsent($email, $type === 'bounced' ? 'bounced' : 'complained', (int) $prospect->id());
      }
    }
    return TRUE;
  }

  /**
   * Records an open or click exactly once per event key.
   */
  public function track(string $trackingKey, string $type): ?Prospect {
    if (!in_array($type, ['opened', 'clicked'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported tracking type.');
    }
    $message = $this->loadBy('tracking_key', $trackingKey);
    if (!$message) {
      return NULL;
    }
    $this->ledger->recordEvent(
      'email.' . $type . ':' . $message['id'],
      'email.' . $type,
      ['message_id' => (int) $message['id']],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
    );
    $this->setStatus((int) $message['id'], $type);
    return $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
  }

  /**
   * Resolves the verified-cold click lane without conflating it with legacy
   * campaign links. A malformed stored destination remains explicitly cold so
   * the controller can fail closed instead of minting a legacy prospect token.
   *
   * @return array{is_verified_cold:bool,destination:?string}
   */
  public function resolveVerifiedColdClick(string $trackingKey): array {
    $message = $this->loadBy('tracking_key', $trackingKey);
    if (!$message || (string) $message['template_key'] !== 'verified_cold_preview') {
      return ['is_verified_cold' => FALSE, 'destination' => NULL];
    }
    $url = trim((string) ($message['proof_url'] ?? ''));
    $base = rtrim((string) (
      getenv('FAMTASTIC_PUBLIC_BASE_URL')
      ?: $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url')
      ?: 'https://famtasticdesigns.com'
    ), '/');
    return [
      'is_verified_cold' => TRUE,
      'destination' => $this->isVerifiedColdProofUrl($url, $base) ? $url : NULL,
    ];
  }

  /**
   * Compatibility helper for callers that need only a validated destination.
   * New controllers must use resolveVerifiedColdClick() so invalid cold data
   * cannot fall through to the legacy prospect-token flow.
   */
  public function verifiedColdClickDestination(string $trackingKey): ?string {
    return $this->resolveVerifiedColdClick($trackingKey)['destination'];
  }

  /** Validates one stored, same-origin signed public-preview room URL. */
  private function isVerifiedColdProofUrl(string $url, string $base): bool {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return FALSE;
    }
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $baseParts = parse_url($base);
    $baseHost = strtolower((string) ($baseParts['host'] ?? ''));
    if (
      !in_array($scheme, ['http', 'https'], TRUE)
      || $host === ''
      || $host !== $baseHost
      || (int) ($parts['port'] ?? 0) !== (int) ($baseParts['port'] ?? 0)
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
    ) {
      return FALSE;
    }
    // The signed room path is intentionally narrow: no arbitrary stored
    // redirect, no legacy token exchange, no customer/account surface.
    if (preg_match('#^/proofs/preview/[0-9a-f-]{36}/[a-f0-9]{64}$#', (string) ($parts['path'] ?? '')) !== 1) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Suppresses a recipient from the legacy opaque GET unsubscribe link.
   *
   * Verified-cold commercial mail must use its confirmation/one-click POST
   * route. Rejecting its keys here prevents a copied or transformed cold key
   * from making a GET prefetch mutate suppression state through the older
   * endpoint, while all non-cold historical mail keeps its GET behavior.
   */
  public function unsubscribe(string $unsubscribeKey): bool {
    $message = $this->loadBy('unsubscribe_key', $unsubscribeKey);
    if (!$message || (string) ($message['template_key'] ?? '') === 'verified_cold_preview') {
      return FALSE;
    }
    return $this->unsubscribeMessage($message);
  }

  /**
   * Suppresses only a verified-cold commercial recipient.
   *
   * The one-click endpoint is intentionally separate from the legacy
   * unsubscribe URL.  That lets a cold-email link use a POST confirmation
   * without changing the historical GET contract for older campaign mail.
   */
  public function unsubscribeVerifiedCold(string $unsubscribeKey): bool {
    $message = $this->loadBy('unsubscribe_key', $unsubscribeKey);
    if (!$message || (string) ($message['template_key'] ?? '') !== 'verified_cold_preview') {
      return FALSE;
    }
    return $this->unsubscribeMessage($message);
  }

  /** Applies the durable consent/status change after a lane-specific check. */
  private function unsubscribeMessage(array $message): bool {
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
    if (!$prospect) {
      return FALSE;
    }
    $this->ledger->recordConsent((string) $prospect->get('public_email')->value, 'unsubscribed', (int) $prospect->id());
    $this->setStatus((int) $message['id'], 'unsubscribed');
    return TRUE;
  }

  public function load(int $id): ?array {
    return $this->loadBy('id', $id);
  }

  private function loadBy(string $field, string|int $value): ?array {
    $allowed = ['id', 'message_key', 'tracking_key', 'unsubscribe_key', 'provider_message_id'];
    if (!in_array($field, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Invalid email lookup.');
    }
    $record = $this->database->select('famtastic_email_message', 'm')
      ->fields('m')
      ->condition($field, $value)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $record ?: NULL;
  }

  private function campaignId(string $campaignKey): ?int {
    $id = $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_key', $campaignKey)
      ->execute()
      ->fetchField();
    return $id ? (int) $id : NULL;
  }

  /**
   * Fetches proof variant names for a proof campaign.
   *
   * @return array<string, string> Direction ID => direction name
   */
  private function proofVariantNames(int $proofCampaignId): array {
    $variants = $this->database->select('proof_variant', 'pv')
      ->fields('pv', ['direction_id', 'direction_name'])
      ->condition('campaign_id', $proofCampaignId)
      ->orderBy('direction_id')
      ->execute()
      ->fetchAllKeyed();
    return array_map('strval', $variants);
  }

  private function messageBody(Prospect $prospect, array $message, string $postalAddress, string $base): string {
    return sprintf(
      "Advertisement from FAMtastic Designs\n\nWe created three website directions for %s.\n\nView them: %s/api/pipeline/email/click/%s\n\nWhy you are receiving this: we found your business contact information in a public business listing while researching local businesses that may benefit from a stronger web presence.\n\nFAMtastic Designs\n%s\n\nTo stop receiving commercial email from us, unsubscribe here: %s/api/pipeline/email/unsubscribe/%s",
      $prospect->label(),
      $base,
      $message['tracking_key'],
      $postalAddress,
      $base,
      $message['unsubscribe_key'],
    );
  }

  /**
   * Builds a rich, personalized proof-ready email body (v3).
   */
  private function messageBodyEnhanced(Prospect $prospect, array $message, string $postalAddress, string $base): string {
    $businessName = $prospect->label();
    $category = (string) ($prospect->get('business_category')->value ?: 'local business');
    $serviceArea = (string) ($prospect->get('service_area')->value ?: '');
    $discoveryNotes = (string) ($prospect->get('discovery_notes')->value ?: '');
    $tokenHash = (string) ($prospect->get('token_hash')->value ?: '');

    // Build personalized hook from available data
    $hookLines = [];
    if ($serviceArea !== '') {
      $hookLines[] = sprintf('We research %s-area businesses like yours to find opportunities to strengthen your web presence.', $serviceArea);
    }
    else {
      $hookLines[] = 'We research local businesses like yours to find opportunities to strengthen your web presence.';
    }
    if ($discoveryNotes !== '') {
      $hookLines[] = 'Based on what we found, your current site has room to grow — and we think you will like what we built.';
    }

    // Fetch proof direction names
    $variantNames = $this->proofVariantNames((int) $message['proof_campaign_id']);
    $directionsList = [];
    foreach (['a', 'b', 'c'] as $dir) {
      if (isset($variantNames[$dir])) {
        $directionsList[] = sprintf('  %s) %s', strtoupper($dir), $variantNames[$dir]);
      }
    }

    // Build ProofHub link if token exists
    $proofHubLink = '';
    if ($tokenHash !== '') {
      try {
        // TokenManager stores hash, not raw token. We cannot reconstruct the raw
        // token from the hash, so the /p/{token} link must be generated at token
        // creation time and stored on the prospect. For now, fall back to the
        // tracking click link which redirects appropriately.
      } catch (\Throwable $e) {
        // ignore
      }
    }

    $lines = [
      sprintf('Hi %s,', $businessName),
      '',
      implode(' ', $hookLines),
      '',
      sprintf('We created three website directions for %s. Each one is a complete, ready-to-launch concept — not a template — built specifically for a %s in your market.', $businessName, $category),
      '',
      'Your three directions:',
    ];
    $lines = array_merge($lines, $directionsList);
    $lines[] = '';
    $lines[] = sprintf('View all three and pick your favorite: %s/api/pipeline/email/click/%s', $base, $message['tracking_key']);
    $lines[] = '';
    $lines[] = 'Our $199 Website Starter Special gets you a professional, mobile-ready site with hosting included. Perfect for small businesses that want to look bigger without spending bigger.';
    $lines[] = '';
    $lines[] = 'Lock in your $199 Starter Special in minutes — no calls, no meetings, just results. Pick your favorite direction and checkout directly from your proof room. Want more options? Create a free project space to unlock additional designs and manage everything in one place.';
    $lines[] = '';
    $lines[] = '— FAMtastic Designs';
    $lines[] = '';
    $lines[] = $postalAddress;
    $lines[] = '';
    $lines[] = sprintf('Why you are receiving this: we found your business contact information in a public business listing while researching local %s businesses that may benefit from a stronger web presence.', $category);
    $lines[] = '';
    $lines[] = sprintf('To stop receiving commercial email from us, unsubscribe here: %s/api/pipeline/email/unsubscribe/%s', $base, $message['unsubscribe_key']);

    return implode("\n", $lines);
  }

  private function setStatus(int $messageId, string $status): void {
    $now = $this->time->getRequestTime();
    $timestampField = match ($status) {
      'delivered' => 'delivered_at',
      'opened' => 'opened_at',
      'clicked' => 'clicked_at',
      'bounced' => 'bounced_at',
      'complained' => 'complained_at',
      'unsubscribed' => 'unsubscribed_at',
      default => NULL,
    };
    $fields = ['status' => $status, 'changed' => $now];
    if ($timestampField) {
      $fields[$timestampField] = $now;
    }
    $this->database->update('famtastic_email_message')
      ->fields($fields)
      ->condition('id', $messageId)
      ->execute();
  }

}
