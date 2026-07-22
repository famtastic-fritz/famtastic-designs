<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Order;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Real Stripe gateway (test mode) using Drupal's bundled Guzzle client.
 *
 * No Stripe PHP SDK dependency — the Checkout Session + retrieve endpoints are
 * called directly over HTTPS. The secret key is read from the STRIPE_SECRET_KEY
 * environment variable or the 'stripe_secret_key' Drupal setting, never config.
 */
class StripeGateway implements PaymentGatewayInterface {

  protected const API_BASE = 'https://api.stripe.com/v1';

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $logger,
    protected ProofCampaignService $proofCampaigns,
  ) {}

  /**
   * Returns the configured Stripe secret key, or NULL.
   */
  public static function secretKey(): ?string {
    $key = getenv('STRIPE_SECRET_KEY') ?: Settings::get('stripe_secret_key');
    return $key ?: NULL;
  }

  /**
   * TRUE when a secret key is configured.
   */
  public static function isConfigured(): bool {
    return self::secretKey() !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMode(): string {
    return 'stripe';
  }

  /**
   * {@inheritdoc}
   */
  public function createCheckoutSession(Order $order, array $context): array {
    $form = [
      'mode' => 'payment',
      'success_url' => $context['success_url'],
      'cancel_url' => $context['cancel_url'],
      'client_reference_id' => (string) $order->id(),
      'line_items[0][quantity]' => 1,
      'line_items[0][price_data][currency]' => $order->get('currency')->value ?: 'usd',
      'line_items[0][price_data][unit_amount]' => (int) $order->get('amount')->value,
      'line_items[0][price_data][product_data][name]' => $context['product_name'] ?? 'FAMtastic Basic Website',
      'metadata[order_id]' => (string) $order->id(),
    ];
    if (!empty($context['customer_email'])) {
      $form['customer_email'] = $context['customer_email'];
    }
    // Attach proof campaign selection metadata so the webhook can mark the
    // campaign converted without touching the existing intake flow.
    $prospect = $order->get('prospect_ref')->entity;
    if ($prospect && ($selection = $this->proofCampaigns->activeSelection($prospect))) {
      $form['metadata[campaign_id]'] = $selection['campaign_id'];
      $form['metadata[selected_variant]'] = $selection['selected_variant'];
      $form['metadata[selected_package]'] = $selection['selected_package'];
    }
    // Prefer a pre-created Price if provided (from stripe-setup.sh).
    if ($priceId = (getenv('STRIPE_PRICE_ID') ?: Settings::get('stripe_price_id'))) {
      unset($form['line_items[0][price_data][currency]'], $form['line_items[0][price_data][unit_amount]'], $form['line_items[0][price_data][product_data][name]']);
      $form['line_items[0][price]'] = $priceId;
    }

    $data = $this->request('POST', '/checkout/sessions', $form);
    return [
      'id' => $data['id'] ?? '',
      'url' => $data['url'] ?? '',
      'payment_intent' => $data['payment_intent'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveSession(string $sessionId): array {
    $data = $this->request('GET', '/checkout/sessions/' . rawurlencode($sessionId));
    return [
      'id' => $data['id'] ?? $sessionId,
      'payment_status' => $data['payment_status'] ?? 'unpaid',
      'payment_intent' => $data['payment_intent'] ?? NULL,
    ];
  }

  /**
   * Performs an authenticated Stripe API request.
   */
  protected function request(string $method, string $path, array $form = []): array {
    $key = self::secretKey();
    if (!$key) {
      throw new \RuntimeException('Stripe secret key is not configured.');
    }
    $options = [
      'headers' => ['Authorization' => 'Bearer ' . $key],
      'timeout' => 20,
    ];
    if ($form) {
      $options['form_params'] = $form;
    }
    $response = $this->httpClient->request($method, self::API_BASE . $path, $options);
    $body = (string) $response->getBody();
    $decoded = json_decode($body, TRUE);
    if (!is_array($decoded)) {
      throw new \RuntimeException('Unexpected Stripe response.');
    }
    return $decoded;
  }

}
