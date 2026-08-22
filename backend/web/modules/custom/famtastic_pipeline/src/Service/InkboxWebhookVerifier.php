<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Verifies signed webhook deliveries from the FAMtastic Concierge identity.
 */
final class InkboxWebhookVerifier {

  /**
   * Verifies the Inkbox `{request_id}.{timestamp}.{raw_body}` HMAC contract.
   */
  public function verify(
    string $payload,
    string $requestId,
    string $timestamp,
    string $signature,
    string $secret,
    int $now,
    int $tolerance = 300,
  ): bool {
    if ($requestId === '' || $timestamp === '' || $signature === '' || $secret === '' || !ctype_digit($timestamp)) {
      return FALSE;
    }
    if ($tolerance > 0 && abs($now - (int) $timestamp) > $tolerance) {
      return FALSE;
    }
    $expected = $this->sign($payload, $requestId, $timestamp, $secret);
    return hash_equals($expected, $signature);
  }

  /**
   * Builds a signature for tests and local webhook fixtures.
   */
  public function sign(string $payload, string $requestId, string $timestamp, string $secret): string {
    return 'sha256=' . hash_hmac('sha256', $requestId . '.' . $timestamp . '.' . $payload, $secret);
  }

}
