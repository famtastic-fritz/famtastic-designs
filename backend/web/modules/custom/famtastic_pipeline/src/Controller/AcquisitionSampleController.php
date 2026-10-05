<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Private Sample Lab capabilities. Account mutation always requires verification. */
final class AcquisitionSampleController extends ControllerBase {

  public function __construct(private readonly AcquisitionSampleService $samples, private readonly AccountProxyInterface $account) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('famtastic_pipeline.acquisition_samples'), $container->get('current_user'));
  }

  public function resolve(string $token): JsonResponse {
    $sample = $this->samples->resolve($token);
    return $this->secure(new JsonResponse($sample ? ['ok' => TRUE, 'sample' => $sample] : ['ok' => FALSE, 'error' => 'sample_not_found'], $sample ? 200 : 404));
  }

  public function preview(string $token, string $recipe): Response {
    try { $html = $this->samples->preview($token, $recipe); }
    catch (\RuntimeException) { $html = NULL; }
    $response = new Response($html ?? 'Sample unavailable.', $html === NULL ? 404 : 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'; sandbox allow-same-origin");
    return $this->secure($response);
  }

  public function preference(Request $request, string $token): JsonResponse {
    if (!$this->sameOrigin($request) || !str_starts_with(strtolower((string) $request->headers->get('Content-Type')), 'application/json')) return $this->error('same_origin_json_required', 403);
    try {
      $input = json_decode($request->getContent(), TRUE, 16, JSON_THROW_ON_ERROR);
      $preference = $this->samples->preference($token, (string) ($input['recipe_id'] ?? ''));
      return $this->secure(new JsonResponse(['ok' => TRUE, 'preference' => $preference]));
    }
    catch (\JsonException) { return $this->error('invalid_json', 422); }
    catch (\InvalidArgumentException $error) { return $this->error($error->getMessage(), 404); }
  }

  public function claim(Request $request, string $token): JsonResponse {
    if (!$this->account->isAuthenticated() || !$this->sameOrigin($request)) return $this->error('authentication_required', 401);
    $customer = \Drupal::service('famtastic_pipeline.customer_portal')->customerForUid((int) $this->account->id());
    if (!$customer || empty($customer['verified_at'])) return $this->error('verification_required', 403);
    try { return $this->secure(new JsonResponse(['ok' => TRUE, 'continuation' => $this->samples->claim((int) $customer['id'], $token)])); }
    catch (\InvalidArgumentException $error) { return $this->error($error->getMessage(), 404); }
  }

  private function sameOrigin(Request $request): bool {
    $origin = $request->headers->get('Origin');
    return ($origin === NULL || hash_equals($request->getSchemeAndHttpHost(), $origin)) && !in_array($request->headers->get('Sec-Fetch-Site'), ['cross-site'], TRUE);
  }

  private function error(string $error, int $status): JsonResponse {
    return $this->secure(new JsonResponse(['ok' => FALSE, 'error' => $error], $status));
  }

  private function secure(Response $response): Response {
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
