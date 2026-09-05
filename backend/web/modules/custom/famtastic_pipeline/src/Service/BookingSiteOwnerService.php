<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Exact customer ownership gate for a branded site's mobile command center. */
final class BookingSiteOwnerService {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Binds one branded site to the customer and organization that own a converted request.
   *
   * This is an operator-only provisioning primitive. It never enables public
   * capture, publishes a site, or connects an external booking provider.
   */
  public function bindToConvertedRequest(string $siteKey, int $websiteRequestId, int $uid): array {
    $siteKey = $this->siteKey($siteKey);
    if ($websiteRequestId <= 0 || $uid <= 0) {
      throw new \InvalidArgumentException('booking_site_binding_invalid');
    }
    $request = $this->database->select('famtastic_project_request', 'r')->fields('r', ['id', 'customer_id', 'organization_id', 'status', 'project_id'])
      ->condition('id', $websiteRequestId)->range(0, 1)->execute()->fetchAssoc();
    if (!$request || $request['status'] !== 'converted' || empty($request['project_id'])) {
      throw new \RuntimeException('booking_site_requires_converted_request');
    }
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_booking_site_owner')->key('site_key', $siteKey)->insertFields([
      'site_key' => $siteKey,
      'customer_id' => (int) $request['customer_id'],
      'organization_id' => (int) $request['organization_id'],
      'website_request_id' => (int) $request['id'],
      'status' => 'active',
      'created_by_uid' => $uid,
      'created' => $now,
      'changed' => $now,
    ])->updateFields([
      'customer_id' => (int) $request['customer_id'],
      'organization_id' => (int) $request['organization_id'],
      'website_request_id' => (int) $request['id'],
      'status' => 'active',
      'created_by_uid' => $uid,
      'changed' => $now,
    ])->execute();
    return $this->binding($siteKey) ?? throw new \RuntimeException('booking_site_binding_unavailable');
  }

  /** Returns a binding only when this customer remains an active organization member. */
  public function forCustomer(int $customerId, string $siteKey): ?array {
    $siteKey = $this->siteKey($siteKey);
    $binding = $this->database->select('famtastic_booking_site_owner', 'b')->fields('b')
      ->condition('site_key', $siteKey)->condition('customer_id', $customerId)->condition('status', 'active')
      ->range(0, 1)->execute()->fetchAssoc();
    if (!$binding) {
      return NULL;
    }
    $membership = $this->database->select('famtastic_membership', 'm')->fields('m', ['customer_id'])
      ->condition('customer_id', $customerId)->condition('organization_id', (int) $binding['organization_id'])
      ->condition('status', 'active')->range(0, 1)->execute()->fetchField();
    return $membership === FALSE ? NULL : $binding;
  }

  /** Throws rather than allowing an unbound customer to enumerate another site's requests. */
  public function requireCustomerOwner(int $customerId, string $siteKey): array {
    return $this->forCustomer($customerId, $siteKey) ?? throw new \RuntimeException('booking_owner_access_denied');
  }

  /** Returns the provisioned mobile command-center binding for one request. */
  public function forWebsiteRequest(int $websiteRequestId): ?array {
    if ($websiteRequestId <= 0) {
      return NULL;
    }
    return $this->database->select('famtastic_booking_site_owner', 'b')->fields('b')
      ->condition('website_request_id', $websiteRequestId)->condition('status', 'active')
      ->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  /**
   * Requires the public consumer site to retain its exact converted-request
   * binding to the supplied organization. This is representation scope only;
   * it does not authorize a provider, payment, booking, or publish action.
   */
  public function requirePublicOrganizationSite(string $siteKey, int $organizationId): void {
    $siteKey = $this->siteKey($siteKey);
    if ($organizationId <= 0) {
      throw new \RuntimeException('booking_site_organization_unavailable');
    }
    $query = $this->database->select('famtastic_booking_site_owner', 'b');
    $query->join('famtastic_project_request', 'r', 'r.id = b.website_request_id');
    $query->fields('b', ['id'])
      ->condition('b.site_key', $siteKey)
      ->condition('b.organization_id', $organizationId)
      ->condition('b.status', 'active')
      ->condition('r.status', 'converted')
      ->condition('r.project_id', 0, '>')
      ->where('r.organization_id = b.organization_id')
      ->where('r.customer_id = b.customer_id')
      ->range(0, 1);
    if ($query->execute()->fetchField() === FALSE) {
      throw new \RuntimeException('booking_site_organization_unavailable');
    }
  }

  private function binding(string $siteKey): ?array {
    return $this->database->select('famtastic_booking_site_owner', 'b')->fields('b')
      ->condition('site_key', $siteKey)->range(0, 1)->execute()->fetchAssoc() ?: NULL;
  }

  private function siteKey(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
    if ($value === '' || mb_strlen($value) > 64) {
      throw new \InvalidArgumentException('booking_site_invalid');
    }
    return $value;
  }

}
