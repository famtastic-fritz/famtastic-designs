<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/**
 * Authenticated outbound boundary for selected-proof staging packets.
 */
final class SiteStudioStagingClient {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Dispatches an immutable packet without claiming a build or deployment.
   */
  public function dispatch(array $packet): array {
    $endpoint = $this->endpoint();
    $secret = getenv('FAMTASTIC_STUDIO_DISPATCH_SECRET')
      ?: Settings::get('site_studio_staging_dispatch_secret');
    if ($endpoint === '' || !$secret) {
      throw new \RuntimeException('Site Studio staging requires an explicitly configured endpoint and dispatch secret.');
    }

    $idempotencyKey = trim((string) ($packet['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
      throw new \RuntimeException('Site Studio staging packet requires an idempotency key.');
    }

    $body = json_encode(['packet' => $packet], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $response = $this->httpClient->request('POST', $endpoint, [
      'headers' => [
        'Content-Type' => 'application/json',
        'X-FAMtastic-Signature' => 'sha256=' . hash_hmac('sha256', $body, (string) $secret),
        'Idempotency-Key' => $idempotencyKey,
      ],
      'body' => $body,
      'http_errors' => FALSE,
      'timeout' => 30,
    ]);

    if ($response->getStatusCode() !== 202) {
      throw new \RuntimeException('Site Studio staging did not accept the selected build packet.');
    }
    try {
      $decoded = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \RuntimeException('Site Studio staging returned an invalid response.');
    }
    $receipt = is_array($decoded) ? ($decoded['receipt'] ?? NULL) : NULL;
    if (
      ($decoded['accepted'] ?? FALSE) !== TRUE
      || ($decoded['status'] ?? '') !== 'accepted_waiting_callback'
      || !is_array($receipt)
      || trim((string) ($receipt['receipt_id'] ?? '')) === ''
      || !hash_equals((string) $packet['packet_id'], (string) ($receipt['packet_id'] ?? ''))
      || !hash_equals($idempotencyKey, (string) ($receipt['idempotency_key'] ?? ''))
    ) {
      throw new \RuntimeException('Site Studio staging response failed its receipt identity contract.');
    }

    return [
      'status' => 'accepted_waiting_callback',
      'receipt_id' => mb_substr((string) $receipt['receipt_id'], 0, 255),
      'packet_id' => (string) $receipt['packet_id'],
      'idempotency_key' => (string) $receipt['idempotency_key'],
    ];
  }

  private function endpoint(): string {
    $endpoint = trim((string) (
      getenv('SITE_STUDIO_STAGING_URL')
      ?: $this->configFactory->get('famtastic_pipeline.settings')->get('site_studio_staging_url')
    ));
    if ($endpoint === '') {
      return '';
    }
    $parts = parse_url($endpoint);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $local = in_array($host, ['localhost', '127.0.0.1', '::1'], TRUE);
    if (
      !is_array($parts)
      || $host === ''
      || !in_array($scheme, $local ? ['http', 'https'] : ['https'], TRUE)
      || $path !== '/api/pipeline/staging/accept'
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
    ) {
      throw new \RuntimeException('Site Studio staging URL must be the exact HTTPS acceptance endpoint (HTTP is allowed only on loopback).');
    }
    return $endpoint;
  }

}
