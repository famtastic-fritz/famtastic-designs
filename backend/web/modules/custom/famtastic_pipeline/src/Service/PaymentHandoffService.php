<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Owner-controlled external payment handoffs for a customer organization.
 *
 * This deliberately stores no merchant credentials, payment instruments,
 * provider responses, or payment verification state. A handoff is a public
 * destination selected by the business owner, not a FAMtastic purchase flow.
 */
final class PaymentHandoffService {

  private const MODES = ['disabled', 'cash_app', 'payment_link', 'qr'];
  private const EVENTS = ['viewed', 'opened'];
  private const SURFACES = ['starter', 'owner_desk'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly BookingSiteOwnerService $sites,
  ) {}

  /** Returns one private configuration only to an active organization owner. */
  public function ownerSnapshot(int $customerId, string $organizationPublicId): array {
    $organization = $this->ownerOrganization($customerId, $organizationPublicId);
    $handoff = $this->loadHandoff((int) $organization['id']);
    return [
      'organization' => [
        'public_id' => (string) $organization['public_id'],
        'name' => (string) $organization['name'],
      ],
      'configured' => $handoff !== NULL,
      'payment_handoff' => $this->ownerModel($handoff),
    ];
  }

  /** Replaces one organization's handoff after exact owner authorization. */
  public function save(int $customerId, string $organizationPublicId, array $input): array {
    $organization = $this->ownerOrganization($customerId, $organizationPublicId);
    $organizationId = (int) $organization['id'];
    $normalized = $this->normalize($input);
    $now = $this->time->getRequestTime();
    $existing = $this->loadHandoff($organizationId);

    $fields = [
      'mode' => $normalized['mode'],
      'destination_url' => $normalized['destination_url'],
      'qr_image_url' => $normalized['qr_image_url'],
      'label' => $normalized['label'],
      'instructions' => $normalized['instructions'],
      'changed' => $now,
    ];
    if ($existing) {
      $this->database->update('famtastic_payment_handoff')->fields($fields)
        ->condition('id', (int) $existing['id'])->execute();
    }
    else {
      $this->database->insert('famtastic_payment_handoff')->fields($fields + [
        'organization_id' => $organizationId,
        'created' => $now,
      ])->execute();
    }

    return $this->ownerSnapshot($customerId, $organizationPublicId);
  }

  /**
   * Returns the small public-safe model only while an owner-enabled handoff is
   * configured. Disabled and absent configurations are intentionally absent.
   */
  public function publicSnapshot(string $organizationPublicId, string $siteKey): array {
    $organization = $this->publicOrganization($organizationPublicId);
    $this->sites->requirePublicOrganizationSite($siteKey, (int) $organization['id']);
    $handoff = $this->loadHandoff((int) $organization['id']);
    if (!$handoff || (string) $handoff['mode'] === 'disabled') {
      throw new \RuntimeException('payment_handoff_unavailable');
    }
    return ['payment_handoff' => $this->publicModel($handoff)];
  }

  /** Records a handoff interaction, never a payment attempt or result. */
  public function recordEvent(string $organizationPublicId, string $siteKey, string $event, string $surface): void {
    if (!in_array($event, self::EVENTS, TRUE)) {
      throw new \InvalidArgumentException('payment_handoff_event_invalid');
    }
    if (!in_array($surface, self::SURFACES, TRUE)) {
      throw new \InvalidArgumentException('payment_handoff_surface_invalid');
    }
    $organization = $this->publicOrganization($organizationPublicId);
    $this->sites->requirePublicOrganizationSite($siteKey, (int) $organization['id']);
    $handoff = $this->loadHandoff((int) $organization['id']);
    if (!$handoff || (string) $handoff['mode'] === 'disabled') {
      throw new \RuntimeException('payment_handoff_unavailable');
    }
    $this->database->insert('famtastic_payment_handoff_event')->fields([
      'payment_handoff_id' => (int) $handoff['id'],
      'organization_id' => (int) $organization['id'],
      'event_type' => $event,
      'surface' => $surface,
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  private function ownerOrganization(int $customerId, string $organizationPublicId): array {
    if ($customerId <= 0 || !$this->uuid($organizationPublicId)) {
      throw new \RuntimeException('payment_handoff_owner_access_denied');
    }
    $query = $this->database->select('famtastic_membership', 'm');
    $query->join('famtastic_organization', 'o', 'o.id = m.organization_id');
    $query->fields('o', ['id', 'public_id', 'name']);
    $query->condition('m.customer_id', $customerId)
      ->condition('m.role', 'owner')
      ->condition('m.status', 'active')
      ->condition('o.public_id', $organizationPublicId)
      ->condition('o.status', 'active')
      ->range(0, 1);
    return $query->execute()->fetchAssoc() ?: throw new \RuntimeException('payment_handoff_owner_access_denied');
  }

  private function publicOrganization(string $organizationPublicId): array {
    if (!$this->uuid($organizationPublicId)) {
      throw new \RuntimeException('payment_handoff_unavailable');
    }
    return $this->database->select('famtastic_organization', 'o')->fields('o', ['id'])
      ->condition('public_id', $organizationPublicId)->condition('status', 'active')
      ->range(0, 1)->execute()->fetchAssoc() ?: throw new \RuntimeException('payment_handoff_unavailable');
  }

  private function loadHandoff(int $organizationId): ?array {
    return $this->database->select('famtastic_payment_handoff', 'h')->fields('h')
      ->condition('organization_id', $organizationId)->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  private function normalize(array $input): array {
    $mode = (string) ($input['mode'] ?? 'disabled');
    if (!in_array($mode, self::MODES, TRUE)) {
      throw new \InvalidArgumentException('payment_handoff_mode_invalid');
    }
    if ($mode === 'disabled') {
      return [
        'mode' => 'disabled',
        'destination_url' => '',
        'qr_image_url' => '',
        'label' => '',
        'instructions' => '',
      ];
    }

    $destination = $this->publicHttpsUrl((string) ($input['destination_url'] ?? ''), 'payment_handoff_destination_invalid');
    $qrImage = $this->publicHttpsUrl((string) ($input['qr_image_url'] ?? ''), 'payment_handoff_qr_image_invalid');
    if ($mode === 'cash_app') {
      $parts = parse_url($destination);
      if ($destination === '' || mb_strtolower((string) ($parts['host'] ?? '')) !== 'cash.app') {
        throw new \InvalidArgumentException('payment_handoff_cash_app_invalid');
      }
    }
    elseif ($mode === 'payment_link' && $destination === '') {
      throw new \InvalidArgumentException('payment_handoff_destination_required');
    }
    elseif ($mode === 'qr' && $qrImage === '') {
      throw new \InvalidArgumentException('payment_handoff_qr_image_required');
    }

    return [
      'mode' => $mode,
      'destination_url' => $destination,
      'qr_image_url' => $qrImage,
      'label' => $this->text((string) ($input['label'] ?? ''), 80) ?: $this->defaultLabel($mode),
      'instructions' => $this->text((string) ($input['instructions'] ?? ''), 240, TRUE),
    ];
  }

  private function ownerModel(?array $handoff): array {
    $handoff ??= [
      'mode' => 'disabled',
      'destination_url' => '',
      'qr_image_url' => '',
      'label' => '',
      'instructions' => '',
    ];
    return [
      'mode' => (string) $handoff['mode'],
      'destination_url' => (string) $handoff['destination_url'],
      'qr_image_url' => (string) $handoff['qr_image_url'],
      'label' => (string) $handoff['label'],
      'instructions' => (string) $handoff['instructions'],
      'disclosure' => $this->disclosure(),
    ];
  }

  private function publicModel(array $handoff): array {
    $model = [
      'mode' => (string) $handoff['mode'],
      'label' => (string) $handoff['label'],
      'instructions' => (string) $handoff['instructions'],
      'disclosure' => $this->disclosure(),
    ];
    if ((string) $handoff['destination_url'] !== '') {
      $model['destination_url'] = (string) $handoff['destination_url'];
    }
    if ((string) $handoff['qr_image_url'] !== '') {
      $model['qr_image_url'] = (string) $handoff['qr_image_url'];
    }
    return $model;
  }

  private function publicHttpsUrl(string $value, string $error): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    if (!str_contains($value, '://')) {
      $value = 'https://' . $value;
    }
    if (mb_strlen($value) > 500 || !filter_var($value, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException($error);
    }
    $parts = parse_url($value);
    $host = mb_strtolower((string) ($parts['host'] ?? ''));
    if (
      ($parts['scheme'] ?? '') !== 'https'
      || $host === ''
      || isset($parts['user'])
      || isset($parts['pass'])
      || $host === 'localhost'
      || str_ends_with($host, '.localhost')
      || filter_var($host, FILTER_VALIDATE_IP)
    ) {
      throw new \InvalidArgumentException($error);
    }
    return $value;
  }

  private function text(string $value, int $limit, bool $multiline = FALSE): string {
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = $multiline ? preg_replace('/[ \t]+/u', ' ', $value) : preg_replace('/\s+/u', ' ', $value);
    return mb_substr(trim((string) $value), 0, $limit);
  }

  private function defaultLabel(string $mode): string {
    return match ($mode) {
      'cash_app' => 'Pay with Cash App',
      'payment_link' => 'Continue to payment',
      'qr' => 'Scan to pay',
      default => '',
    };
  }

  private function disclosure(): string {
    return 'Payment is handled directly by the business. This handoff does not confirm payment, create an order, or reserve a service.';
  }

  private function uuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
  }

}
