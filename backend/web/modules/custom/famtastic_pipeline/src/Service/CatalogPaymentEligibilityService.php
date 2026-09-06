<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Evaluates the catalog-declared path a product may take into Commerce.
 *
 * The catalog is the commercial source of truth. This service deliberately
 * does not create orders, contact Stripe, or infer eligibility from a price.
 */
final class CatalogPaymentEligibilityService {

  public const MODES = [
    'proof_selected_website_request',
    'bundle_or_active_website',
    'active_website_project',
    'direct_account',
    'renewal_authorization_only',
  ];

  /**
   * Returns the safe public payment contract for one catalog product.
   *
   * @throws \InvalidArgumentException
   *   When a published product has no valid payment contract.
   */
  public function contract(array $definition): array {
    $sku = (string) ($definition['sku'] ?? '');
    $payment = (array) ($definition['payment'] ?? []);
    $mode = (string) ($payment['mode'] ?? '');
    if ($sku === '' || !in_array($mode, self::MODES, TRUE)) {
      throw new \InvalidArgumentException("Catalog payment contract is invalid for {$sku}.");
    }
    $message = trim((string) ($payment['customer_message'] ?? ''));
    if ($message === '') {
      throw new \InvalidArgumentException("Catalog payment message is missing for {$sku}.");
    }
    $requires = array_values(array_unique(array_filter(array_map('strval', (array) ($payment['requires'] ?? [])))));
    if ($requires === []) {
      throw new \InvalidArgumentException("Catalog payment requirements are missing for {$sku}.");
    }

    return [
      'mode' => $mode,
      'customer_message' => $message,
      'requires' => $requires,
    ];
  }

  /**
   * Checks a cart without creating an order or contacting a provider.
   *
   * @return array{allowed: bool, code?: string, message?: string}
   */
  public function evaluateCart(array $definitions, array $skus, bool $hasSelectedWebsiteRequest, bool $hasActiveWebsiteEntitlement, bool $hasActiveWebsiteProject): array {
    $websiteBundleCount = 0;
    foreach ($skus as $sku) {
      $definition = $definitions[$sku] ?? NULL;
      if (!is_array($definition) || empty($definition['published'])) {
        return $this->blocked('product_unavailable', 'One selected service is unavailable.');
      }
      try {
        $payment = $this->contract($definition);
      }
      catch (\InvalidArgumentException) {
        return $this->blocked('payment_contract_unavailable', 'This service is not available for checkout until its payment path is configured.');
      }

      switch ($payment['mode']) {
        case 'proof_selected_website_request':
          $websiteBundleCount++;
          if (!$hasSelectedWebsiteRequest) {
            return $this->blocked('website_proof_selection_required', 'Start with your business intake and choose an approved website direction before checkout.');
          }
          break;

        case 'bundle_or_active_website':
          if (!$hasSelectedWebsiteRequest && !$hasActiveWebsiteEntitlement) {
            return $this->blocked('active_website_required', 'This service can be added to a selected website request or an active website project.');
          }
          break;

        case 'active_website_project':
          if (!$hasActiveWebsiteEntitlement || !$hasActiveWebsiteProject) {
            return $this->blocked('active_website_project_required', 'This add-on is available only from an active website project after its scope is confirmed.');
          }
          break;

        case 'renewal_authorization_only':
          return $this->blocked('recurring_checkout_unavailable', $payment['customer_message']);

        case 'direct_account':
          break;
      }
    }

    if ($websiteBundleCount > 1) {
      return $this->blocked('invalid_cart', 'Choose one website bundle per request.');
    }
    return ['allowed' => TRUE];
  }

  private function blocked(string $code, string $message): array {
    return ['allowed' => FALSE, 'code' => $code, 'message' => $message];
  }

}
