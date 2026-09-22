<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\WorkerCoordinator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Installed worker credentials, not cookies or a customer/staff supplied id. */
final class WorkerCoordinatorController extends ControllerBase {
  public function handle(Request $request, string $operation): JsonResponse {
    $headers = ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow'];
    if (!Settings::get('famtastic_bounded_workers_enabled', FALSE)) return new JsonResponse(['error' => 'workers_not_enabled'], 503, $headers);
    if (!$request->isSecure()) return new JsonResponse(['error' => 'tls_required'], 403, $headers);
    try {
      $authenticator = \Drupal::service('famtastic_pipeline.worker_request_authenticator');
      $principal = $authenticator->authenticate($request, $operation);
    } catch (\Throwable) { return new JsonResponse(['error' => 'worker_authentication_rejected'], 403, $headers); }
    try {
      if (\Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive()) throw new \RuntimeException('Pilot lock prevents worker execution.');
      // Use only the exact authenticated body, not the mutable HTTP request or a
      // caller-supplied reviewer. Recheck current installed authority before work.
      $facts = $authenticator->facts($principal);
      $body = $facts['body']; $worker = $facts['worker']; $capabilities = $facts['capabilities'];
      $coordinator = \Drupal::service('famtastic_pipeline.worker_coordinator');
      $portal = \Drupal::service('famtastic_pipeline.customer_portal');
      if ($operation === 'claim') {
        // Legacy runners send {} and remain static-only even for a multi-role
        // identity. A request may narrow its installed grant, never elevate it.
        $requested = $body['capability'] ?? WorkerCoordinator::CAPABILITY;
        if (!is_string($requested) || !in_array($requested, $capabilities, TRUE)) throw new \InvalidArgumentException('Claim capability rejected.');
        $claim = $coordinator->claim($worker, [$requested]);
        if ($claim && $claim['capability'] === WorkerCoordinator::CAPABILITY) {
          $packet = json_decode($claim['payload_wire'], TRUE, flags: JSON_THROW_ON_ERROR)['packet'];
          try { $portal->assertCurrentSelectedStagingPacket($packet); }
          catch (\Throwable $e) { $coordinator->fail($claim['job_id'], $worker, $claim['lease_token']); throw $e; }
        }
        return new JsonResponse(['claim' => $claim], 200, $headers);
      }
      $id = (int) ($body['job_id'] ?? 0);
      $token = (string) ($body['lease_token'] ?? '');
      // Proof ownership requires an integer; never coerce a caller's generation.
      // Legacy static/review operations keep ignoring this previously unused key.
      $attempt = is_int($body['attempt'] ?? NULL) ? $body['attempt'] : NULL;
      $result = match ($operation) {
        'renew' => $coordinator->renew($id, $worker, $token, $capabilities, $attempt),
        'finish' => $coordinator->finish($id, $worker, $token, (array) ($body['result'] ?? []), $capabilities, $attempt),
        'fail' => $coordinator->fail($id, $worker, $token, $capabilities, $attempt),
        'review' => $portal->releaseWebsiteRequestProofAfterQa((int) ($body['request_id'] ?? 0), (array) ($body['research'] ?? []),
          (array) ($body['evidence'] ?? []), $facts['identity'], (array) ($body['notification'] ?? []), $principal),
        default => throw new \InvalidArgumentException('Unknown operation.'),
      };
      return new JsonResponse(['result' => $result], 200, $headers);
    } catch (\Throwable $e) {
      // Do not leak database records, payloads, lease tokens or secrets in HTTP errors.
      return new JsonResponse(['error' => $e->getMessage() === 'worker_budget_exhausted' ? 'worker_budget_exhausted' : 'worker_operation_rejected'], 409, $headers);
    }
  }
}
