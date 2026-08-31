<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\MicrositeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Public storefront and owner-management API for small client sites. */
final class MicrositeController implements ContainerInjectionInterface {

  public function __construct(
    private readonly MicrositeService $microsites,
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.microsites'),
      $container->get('flood'),
      $container->get('current_user'),
    );
  }

  public function content(string $site_key): JsonResponse {
    try {
      return $this->response(['ok' => TRUE] + $this->microsites->publicSnapshot($site_key), 200, FALSE);
    }
    catch (\Throwable) {
      return $this->error('microsite_not_found', 404);
    }
  }

  public function capture(Request $request, string $site_key, string $kind): JsonResponse {
    if (strlen($request->getContent()) > 16384) {
      return $this->error('request_too_large', 413);
    }
    $input = $this->json($request);
    // Quiet honeypot success gives bots no useful validation oracle.
    if (trim((string) ($input['website'] ?? '')) !== '') {
      return $this->response(['ok' => TRUE, 'status' => 'received']);
    }
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    $ipKey = 'microsite:' . $site_key . ':' . $kind . ':ip:' . ($request->getClientIp() ?: 'unknown');
    $emailKey = 'microsite:' . $site_key . ':' . $kind . ':email:' . hash('sha256', $email);
    if (
      !$this->flood->isAllowed('famtastic_microsite_capture', 12, 3600, $ipKey)
      || !$this->flood->isAllowed('famtastic_microsite_capture', 5, 3600, $emailKey)
    ) {
      return $this->error('rate_limited', 429);
    }
    try {
      $result = $this->microsites->capture(
        $site_key,
        $kind,
        $input,
        (string) ($input['source'] ?? 'thirst-trap-v2'),
      );
      $this->flood->register('famtastic_microsite_capture', 3600, $ipKey);
      $this->flood->register('famtastic_microsite_capture', 3600, $emailKey);
      return $this->response(['ok' => TRUE] + $result);
    }
    catch (\InvalidArgumentException $error) {
      return $this->error($error->getMessage(), 422);
    }
    catch (\Throwable) {
      return $this->error('capture_unavailable', 503);
    }
  }

  public function owner(string $site_key): JsonResponse {
    if ($this->currentUser->isAnonymous()) {
      return $this->error('authentication_required', 401);
    }
    try {
      return $this->response(['ok' => TRUE] + $this->microsites->ownerSnapshot(
        $site_key,
        (int) $this->currentUser->id(),
        $this->currentUser->hasPermission('administer famtastic pipeline'),
      ));
    }
    catch (\Throwable $error) {
      return $this->ownerError($error);
    }
  }

  public function update(Request $request, string $site_key): JsonResponse {
    if ($this->currentUser->isAnonymous()) {
      return $this->error('authentication_required', 401);
    }
    if (strlen($request->getContent()) > 131072) {
      return $this->error('request_too_large', 413);
    }
    try {
      $result = $this->microsites->updateContent(
        $site_key,
        (int) $this->currentUser->id(),
        $this->currentUser->hasPermission('administer famtastic pipeline'),
        $this->json($request),
      );
      return $this->response(['ok' => TRUE] + $result);
    }
    catch (\InvalidArgumentException $error) {
      return $this->error($error->getMessage(), 422);
    }
    catch (\Throwable $error) {
      return $this->ownerError($error);
    }
  }

  public function updateMessage(Request $request, string $site_key, int $message_id): JsonResponse {
    if ($this->currentUser->isAnonymous()) {
      return $this->error('authentication_required', 401);
    }
    try {
      $input = $this->json($request);
      $this->microsites->updateMessageStatus(
        $site_key,
        (int) $this->currentUser->id(),
        $this->currentUser->hasPermission('administer famtastic pipeline'),
        $message_id,
        (string) ($input['status'] ?? ''),
      );
      return $this->response(['ok' => TRUE, 'status' => (string) $input['status']]);
    }
    catch (\InvalidArgumentException $error) {
      return $this->error($error->getMessage(), 422);
    }
    catch (\Throwable $error) {
      return $this->ownerError($error);
    }
  }

  private function json(Request $request): array {
    $input = json_decode($request->getContent(), TRUE);
    return is_array($input) ? $input : [];
  }

  private function ownerError(\Throwable $error): JsonResponse {
    return match ($error->getMessage()) {
      'microsite_not_found' => $this->error('microsite_not_found', 404),
      'microsite_access_denied' => $this->error('microsite_access_denied', 403),
      'message_not_found' => $this->error('message_not_found', 404),
      default => $this->error('microsite_operation_failed', 500),
    };
  }

  private function error(string $error, int $status): JsonResponse {
    return $this->response(['ok' => FALSE, 'error' => $error], $status);
  }

  private function response(array $payload, int $status = 200, bool $private = TRUE): JsonResponse {
    $response = new JsonResponse($payload, $status);
    $response->headers->set('Cache-Control', $private ? 'private, no-store' : 'public, max-age=60, stale-while-revalidate=300');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
