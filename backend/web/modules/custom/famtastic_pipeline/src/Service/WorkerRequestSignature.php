<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Short-lived worker authentication; the nonce is atomically retained by Drupal. */
final class WorkerRequestSignature {
  public static function verify(string $method, string $path, string $body, string $worker, string $timestamp, string $nonce, string $signature, string $secret, int $now): void {
    if ($method !== 'POST' || strlen($body) > 100000 || strlen($secret) < 32
      || !preg_match('/^[a-z][a-z0-9._-]{2,63}$/', $worker) || !preg_match('/^[0-9]{10}$/', $timestamp)
      || abs($now - (int) $timestamp) > 90 || !preg_match('/^[a-f0-9]{32}$/', $nonce)
      || !preg_match('#^/api/pipeline/worker/(claim|renew|finish|fail|review)$#', $path)) {
      throw new \InvalidArgumentException('Worker authentication rejected.');
    }
    $wire = implode("\n", [$method, $path, $worker, $timestamp, $nonce, hash('sha256', $body)]);
    if (!hash_equals('sha256=' . hash_hmac('sha256', $wire, $secret), $signature)) throw new \InvalidArgumentException('Worker authentication rejected.');
  }
}
