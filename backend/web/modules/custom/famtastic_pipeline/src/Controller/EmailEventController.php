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
    $cold = $this->messages->resolveVerifiedColdClick($tracking_key);
    if ($cold['is_verified_cold']) {
      if ($cold['destination'] === NULL) {
        return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_cold_preview_destination'], 404, [
          'Cache-Control' => 'no-store, private',
        ]);
      }
      return new TrustedRedirectResponse($cold['destination'], 302, [
        'Cache-Control' => 'no-store, private',
      ]);
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

  /**
   * Confirmation boundary for verified-cold commercial mail.
   *
   * A human follows the GET link to a plain confirmation page.  Only a POST
   * carrying the RFC 8058 one-click form value changes suppression state, so
   * security scanners and mail prefetchers cannot unsubscribe someone merely
   * by following the link.
   */
  public function verifiedColdUnsubscribe(Request $request, string $unsubscribe_key): Response {
    if ($request->isMethod('GET')) {
      // Drupal is mounted at the public /web document root.  Keep this action
      // as a fixed public path rather than rebuilding it from an internal
      // Request base path, which may omit /web behind a proxy.
      $action = '/web/api/pipeline/email/unsubscribe/confirm/' . $unsubscribe_key;
      return new Response(
        '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Confirm unsubscribe</title></head><body><main><h1>Stop commercial emails from FAMtastic Designs?</h1><p>Choose confirm to stop future commercial email at this address.</p><form method="post" action="' . htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><input type="hidden" name="List-Unsubscribe" value="One-Click"><button type="submit">Confirm unsubscribe</button></form></main></body></html>',
        200,
        [
          'Content-Type' => 'text/html; charset=UTF-8',
          'Cache-Control' => 'no-store, private',
          'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'",
          'Referrer-Policy' => 'no-referrer',
          'X-Content-Type-Options' => 'nosniff',
        ],
      );
    }

    // RFC 8058 one-click clients submit this form field in a POST body.  The
    // same field is present in the human confirmation form above.
    if ((string) $request->request->get('List-Unsubscribe', '') !== 'One-Click') {
      return new JsonResponse([
        'ok' => FALSE,
        'error' => 'unsubscribe_confirmation_required',
      ], 400, ['Cache-Control' => 'no-store, private']);
    }

    $ok = $this->messages->unsubscribeVerifiedCold($unsubscribe_key);
    if (!$ok) {
      return new JsonResponse([
        'ok' => FALSE,
        'error' => 'invalid_unsubscribe_link',
      ], 404, ['Cache-Control' => 'no-store, private']);
    }

    return new Response(
      '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Unsubscribed</title></head><body><main><h1>You have been unsubscribed.</h1><p>You will no longer receive commercial email from FAMtastic Designs at this address.</p></main></body></html>',
      200,
      [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-store, private',
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'",
        'Referrer-Policy' => 'no-referrer',
        'X-Content-Type-Options' => 'nosniff',
      ],
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
