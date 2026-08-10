<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\LifecycleOperationsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Receives signed cPanel mail-pipe envelopes. */
final class InboundMailController extends ControllerBase {
  public function __construct(private readonly LifecycleOperationsService $operations) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('famtastic_pipeline.lifecycle_operations')); }
  public function receive(Request $request): JsonResponse {
    $secret = (string) Settings::get('famtastic_inbound_mail_secret', '');
    $provided = (string) $request->headers->get('X-FAMtastic-Mail-Signature', '');
    if ($secret === '' || !hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $provided)) return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_signature'], 403);
    try {
      $payload = json_decode($request->getContent(), TRUE, 32, JSON_THROW_ON_ERROR);
      return new JsonResponse(['ok' => TRUE] + $this->operations->ingestInbound($payload), 202);
    }
    catch (\InvalidArgumentException $error) { return new JsonResponse(['ok' => FALSE, 'error' => $error->getMessage()], 422); }
  }
}
