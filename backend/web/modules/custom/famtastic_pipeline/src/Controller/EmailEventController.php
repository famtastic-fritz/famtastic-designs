<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\CampaignMessageService;
use Drupal\famtastic_pipeline\Service\TokenManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opaque tracking, unsubscribe, and signed email-provider event endpoints.
 */
final class EmailEventController extends ControllerBase {

  public function __construct(
    private readonly CampaignMessageService $messages,
    private readonly TokenManager $tokens,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.campaign_messages'),
      $container->get('famtastic_pipeline.token_manager'),
    );
  }

  public function open(string $tracking_key): Response {
    $this->messages->track($tracking_key, 'opened');
    $gif = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', TRUE);
    return new Response($gif ?: '', 200, [
      'Content-Type' => 'image/gif',
      'Cache-Control' => 'no-store, private',
    ]);
  }

  public function click(string $tracking_key): Response {
    $prospect = $this->messages->track($tracking_key, 'clicked');
    if (!$prospect) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_tracking_link'], 404);
    }
    $token = $this->tokens->generate();
    $prospect
      ->set('token_hash', $token['hash'])
      ->set('token_expires', $token['expires'])
      ->set('token_revoked', FALSE)
      ->save();
    return new TrustedRedirectResponse($this->tokens->link($token['raw']), 302, [
      'Cache-Control' => 'no-store, private',
    ]);
  }

  public function unsubscribe(string $unsubscribe_key): JsonResponse {
    $ok = $this->messages->unsubscribe($unsubscribe_key);
    return new JsonResponse(
      $ok
        ? ['ok' => TRUE, 'message' => 'You have been unsubscribed.']
        : ['ok' => FALSE, 'error' => 'invalid_unsubscribe_link'],
      $ok ? 200 : 404,
      ['Cache-Control' => 'no-store, private'],
    );
  }

  public function provider(Request $request): JsonResponse {
    if (strlen($request->getContent()) > 65536) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'request_too_large'], 413);
    }
    $secret = getenv('FAMTASTIC_EMAIL_WEBHOOK_SECRET') ?: Settings::get('famtastic_email_webhook_secret');
    if (!$secret) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'webhook_not_configured'], 503);
    }
    $provided = (string) $request->headers->get('X-FAMtastic-Signature', '');
    $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), (string) $secret);
    if (!hash_equals($expected, $provided)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_signature'], 400);
    }
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_json'], 400);
    }
    try {
      $new = $this->messages->providerEvent(
        (string) ($data['event_id'] ?? ''),
        (string) ($data['provider_message_id'] ?? ''),
        (string) ($data['type'] ?? ''),
        $data,
      );
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_event', 'message' => $e->getMessage()], 422);
    }
    return new JsonResponse(['ok' => TRUE, 'newly_processed' => $new]);
  }

}
