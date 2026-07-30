<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Issues and verifies secure prospect link tokens.
 *
 * The raw token is a 32-byte cryptographically random value, base64url encoded.
 * Only its SHA-256 hash is ever stored, so a database leak cannot replay links.
 */
class TokenManager {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
  ) {}

  /**
   * Generates a new token bundle.
   *
   * @return array{raw:string,hash:string,expires:int}
   */
  public function generate(): array {
    $raw = $this->base64UrlEncode(random_bytes(32));
    $ttlDays = (int) ($this->configFactory->get('famtastic_pipeline.settings')->get('token_ttl_days') ?: 14);
    return [
      'raw' => $raw,
      'hash' => $this->hash($raw),
      'expires' => $this->time->getRequestTime() + ($ttlDays * 86400),
    ];
  }

  /**
   * Returns the SHA-256 hex hash of a raw token.
   */
  public function hash(string $raw): string {
    return hash('sha256', $raw);
  }

  /**
   * Constant-time comparison of a raw token against a stored hash.
   */
  public function verify(string $raw, string $hash): bool {
    if ($raw === '' || $hash === '') {
      return FALSE;
    }
    return hash_equals($hash, $this->hash($raw));
  }

  /**
   * Builds the full prospect landing URL for a raw token.
   */
  public function link(string $raw): string {
    $base = rtrim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url'), '/');
    if ($envBase = getenv('FRONTEND_BASE_URL')) {
      $base = rtrim($envBase, '/');
    }
    return $base . '/p/' . $raw;
  }

  /**
   * URL-safe base64 without padding.
   */
  protected function base64UrlEncode(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  }

}
