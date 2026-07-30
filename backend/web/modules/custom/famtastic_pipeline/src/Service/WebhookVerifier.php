<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/**
 * Verifies Stripe webhook signatures (the "t=…,v1=…" scheme).
 *
 * signed_payload = "{timestamp}.{raw_body}"
 * expected       = HMAC-SHA256(signed_payload, webhook_secret)
 * The header is valid if any v1 signature matches in constant time and the
 * timestamp is within tolerance.
 */
class WebhookVerifier {

  /**
   * A documented local-dev default so the proof runs with zero config.
   *
   * In any real environment set STRIPE_WEBHOOK_SECRET (or the
   * 'stripe_webhook_secret' Drupal setting) to Stripe's signing secret.
   */
  public const LOCAL_DEV_SECRET = 'whsec_local_dev_secret';

  /**
   * Returns the configured webhook secret, falling back to the local default.
   */
  public static function secret(): string {
    return (string) (getenv('STRIPE_WEBHOOK_SECRET') ?: Settings::get('stripe_webhook_secret') ?: self::LOCAL_DEV_SECRET);
  }

  /**
   * Verifies a raw payload against a Stripe-Signature header.
   */
  public function verify(string $payload, string $sigHeader, ?string $secret = NULL, int $tolerance = 300): bool {
    $secret ??= self::secret();
    if ($sigHeader === '' || $secret === '') {
      return FALSE;
    }
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
      $kv = explode('=', trim($pair), 2);
      if (count($kv) === 2) {
        $parts[$kv[0]][] = $kv[1];
      }
    }
    $timestamp = $parts['t'][0] ?? NULL;
    $signatures = $parts['v1'] ?? [];
    if ($timestamp === NULL || !$signatures) {
      return FALSE;
    }
    if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
      return FALSE;
    }
    $expected = $this->computeSignature($payload, (string) $timestamp, $secret);
    foreach ($signatures as $candidate) {
      if (hash_equals($expected, $candidate)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds a valid Stripe-Signature header value (used by simulate + tests).
   */
  public function sign(string $payload, string $timestamp, ?string $secret = NULL): string {
    $secret ??= self::secret();
    return sprintf('t=%s,v1=%s', $timestamp, $this->computeSignature($payload, $timestamp, $secret));
  }

  /**
   * HMAC-SHA256 of "{timestamp}.{payload}".
   */
  protected function computeSignature(string $payload, string $timestamp, string $secret): string {
    return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
  }

}
