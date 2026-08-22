<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\ConciergeWebhookService;
use Drupal\famtastic_pipeline\Service\InkboxWebhookVerifier;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives signature-verified lifecycle events for FAMtastic Concierge.
 */
final class InkboxWebhookController extends ControllerBase {

  public function __construct(
    private readonly ConciergeWebhookService $concierge,
    private readonly InkboxWebhookVerifier $verifier,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.concierge_webhooks'),
      $container->get('famtastic_pipeline.inkbox_webhook_verifier'),
      $container->get('datetime.time'),
    );
  }

  /**
   * POST /api/pipeline/concierge/inkbox/webhook.
   */
  public function receive(Request $request): JsonResponse {
    $payload = $request->getContent();
    if (strlen($payload) > 262144) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'request_too_large'], 413);
    }
    $secret = (string) (getenv('INKBOX_CONCIERGE_SIGNING_KEY') ?: Settings::get('inkbox_concierge_signing_key', ''));
    if ($secret === '') {
      return new JsonResponse(['ok' => FALSE, 'error' => 'webhook_not_configured'], 503);
    }
    $requestId = (string) $request->headers->get('X-Inkbox-Request-ID', '');
    $timestamp = (string) $request->headers->get('X-Inkbox-Timestamp', '');
    $signature = (string) $request->headers->get('X-Inkbox-Signature', '');
    if (!$this->verifier->verify($payload, $requestId, $timestamp, $signature, $secret, $this->time->getRequestTime())) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_signature'], 403);
    }
    try {
      $event = json_decode($payload, TRUE, 32, JSON_THROW_ON_ERROR);
      if (!is_array($event)) {
        throw new \InvalidArgumentException('inkbox_event_invalid');
      }
      return new JsonResponse(['ok' => TRUE] + $this->concierge->ingest($event), 202);
    }
    catch (\JsonException | \InvalidArgumentException $error) {
      return new JsonResponse(['ok' => FALSE, 'error' => $error->getMessage()], 422);
    }
  }

}
