<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Opaque principals from the EXISTING worker HMAC and durable nonce authority.
 *
 * The existing controller supplies the routed operation, never body data.
 * Service wiring does not enable the default-off worker switch.
 * The canonical signed path remains /api/pipeline/worker/<operation>, independent
 * of Drupal's installation prefix. No alternate signature, credential or grant.
 *
 * The injected primary coordinator must reject caller transactions/replicas in
 * rememberNonce(): an uncommitted nonce cannot authorize an external operation.
 * This adapter never finds another connection, starts a transaction, claims a
 * job, runs QA, releases proofs or invokes providers. Current facts are scoped
 * authentication, NOT account/receipt/producer/evidence/release authorization.
 *
 * Do not serialize/log facts as an HTTP response: the signed operation body may
 * contain lease credentials and business input. Registry secrets/signatures are
 * never returned. Body fields remain untrusted operation input, not identity.
 */
final class WorkerRequestAuthenticator {
  /** @var \WeakMap<object, array> Only this instance can resolve its principals. */
  private \WeakMap $principals;

  public function __construct(
    private readonly WorkerCoordinator $coordinator,
    private readonly TimeInterface $time,
  ) {
    $this->principals = new \WeakMap();
  }

  private function __clone() {}

  /** Mint only after TLS, installed capabilities, existing HMAC and nonce insert. */
  public function authenticate(Request $request, string $operation): object {
    $this->enabled();
    if (!$request->isSecure()) throw new \InvalidArgumentException('Worker authentication requires TLS.');
    if (!in_array($operation, ['claim', 'renew', 'finish', 'fail', 'review'], TRUE)) throw new \InvalidArgumentException('Worker operation is invalid.');
    // Retain values, never a Request or references to its mutable parameter bags.
    $bound = [
      'worker' => (string) $request->headers->get('X-FAMtastic-Worker', ''),
      'operation' => $operation, 'method' => $request->getMethod(),
      'wire' => $request->getContent(),
      'timestamp' => (string) $request->headers->get('X-FAMtastic-Timestamp', ''),
      'nonce' => (string) $request->headers->get('X-FAMtastic-Nonce', ''),
      'signature' => (string) $request->headers->get('X-FAMtastic-Signature', ''),
    ];
    $installed = $this->installed($bound['worker'], $operation);
    $this->verify($bound, $installed['secret']);
    $bound['registry_sha256'] = $installed['registry_sha256'];
    // Preserve the existing controller's ordering: authenticated malformed JSON
    // consumes its nonce too. Any DB failure withholds the principal; no retry or
    // rollback of a possibly persisted nonce is attempted here.
    $this->coordinator->rememberNonce($bound['worker'], $bound['nonce']);
    $body = json_decode($bound['wire'], TRUE, 32, JSON_THROW_ON_ERROR);
    if (!is_array($body)) throw new \InvalidArgumentException('Worker JSON object required.');
    $bound['body'] = $body;
    // DB work may wait. Check expiry and installed authority again before minting.
    $this->current($bound);
    $principal = new \stdClass();
    $this->principals[$principal] = $bound;
    return $principal;
  }

  /**
   * Fresh authenticated values for the existing controller; arrays are by value.
   * Capabilities are ONLY the installed claim-policy intersection. The controller
   * must still narrow claims and validate lease/attempt/result/operation input.
   */
  public function facts(object $principal): array {
    if (!isset($this->principals[$principal])) throw new \InvalidArgumentException('Unknown worker principal.');
    $bound = $this->principals[$principal];
    try { $capabilities = $this->current($bound); }
    catch (\Throwable $error) {
      // Observed revocation/expiry cannot be undone by restoring old settings or
      // rewinding a clock. Unobserved configuration history is not inferred.
      unset($this->principals[$principal]);
      throw $error;
    }
    return ['worker' => $bound['worker'], 'operation' => $bound['operation'],
      'capabilities' => $capabilities, 'identity' => 'automation:' . $bound['worker'],
      'body' => $bound['body'], 'body_sha256' => hash('sha256', $bound['wire'])];
  }

  /** Exact signed review request only. Reader separately excludes ALL producers. */
  public function reviewer(object $principal, int $exactRequestId): ?string {
    $facts = $this->facts($principal);
    $id = $facts['body']['request_id'] ?? NULL;
    if ($facts['operation'] !== 'review' || $exactRequestId < 1 || !is_int($id) || $id !== $exactRequestId) return NULL;
    return $facts['identity'];
  }

  private function current(array $bound): array {
    $this->enabled();
    $installed = $this->installed($bound['worker'], $bound['operation']);
    if (!hash_equals($bound['registry_sha256'], $installed['registry_sha256'])) throw new \InvalidArgumentException('Worker installed authority changed.');
    $this->verify($bound, $installed['secret']);
    return $installed['capabilities'];
  }

  private function installed(string $worker, string $operation): array {
    $registry = Settings::get('famtastic_worker_registry', []);
    $identity = is_array($registry) ? ($registry[$worker] ?? NULL) : NULL;
    if (!is_array($identity) || !is_string($identity['secret'] ?? NULL) || !is_array($identity['capabilities'] ?? NULL)) throw new \InvalidArgumentException('Worker installed authority is invalid.');
    $capabilities = WorkerCapabilityPolicy::claimCapabilities($identity['capabilities']);
    if ($operation === 'review' ? !in_array('proof.review', $identity['capabilities'], TRUE) : !$capabilities) throw new \InvalidArgumentException('Worker capability rejected.');
    if ($operation === 'review') {
      // Distinct registry names are not independent identities when another
      // credential holder can sign as this reviewer. Recheck on every resolution,
      // including aliases added after minting; never expose the matching entry.
      foreach ($registry as $otherWorker => $otherIdentity) {
        if ($otherWorker !== $worker && is_array($otherIdentity) && is_string($otherIdentity['secret'] ?? NULL)
          && hash_equals($identity['secret'], $otherIdentity['secret'])) throw new \InvalidArgumentException('Worker reviewer credential is not independent.');
      }
    }
    return ['secret' => $identity['secret'], 'capabilities' => $capabilities,
      // Retain ONLY a digest, not the plaintext registry secret, in the principal
      // binding. Fingerprint the full configured capabilities, not just grants.
      'registry_sha256' => hash('sha256', json_encode([$identity['secret'], $identity['capabilities']], JSON_THROW_ON_ERROR))];
  }

  private function verify(array $bound, string $secret): void {
    WorkerRequestSignature::verify($bound['method'], '/api/pipeline/worker/' . $bound['operation'], $bound['wire'], $bound['worker'],
      $bound['timestamp'], $bound['nonce'], $bound['signature'], $secret, $this->time->getCurrentTime());
  }

  private function enabled(): void {
    if (!Settings::get('famtastic_bounded_workers_enabled', FALSE)) throw new \RuntimeException('Workers are not enabled.');
  }
}
