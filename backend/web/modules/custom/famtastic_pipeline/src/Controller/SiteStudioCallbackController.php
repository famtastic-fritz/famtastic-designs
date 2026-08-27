<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\ProofAssetContract;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Signature-verified asynchronous Site Studio completion callback.
 */
final class SiteStudioCallbackController extends ControllerBase {

  /**
   * Constructs the callback controller.
   */
  public function __construct(
    private readonly ProofCampaignService $proofCampaigns,
    private readonly SiteStudioBuildPacketService $buildPackets,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.proof_campaign_service'),
      $container->get('famtastic_pipeline.site_studio_build_packets'),
    );
  }

  /**
   * Accepts a signed proof callback or build-success packet.
   */
  public function handle(Request $request): JsonResponse {
    if (strlen($request->getContent()) > ProofAssetContract::MAX_CALLBACK_BYTES) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'request_too_large'], 413);
    }
    $secret = getenv('SITE_STUDIO_CALLBACK_SECRET') ?: Settings::get('site_studio_callback_secret');
    if (!$secret) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'callback_not_configured'], 503);
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
      if (($data['schema'] ?? '') === 'site-studio.build-success.v1') {
        $result = $this->buildPackets->acceptSuccess($data);
        return new JsonResponse([
          'ok' => TRUE,
          'newly_processed' => $result['newly_processed'],
          'project_id' => (int) $result['project']->id(),
          'status' => 'site_studio_build_succeeded',
        ]);
      }
      $result = $this->proofCampaigns->acceptCallback(
        (string) ($data['event_id'] ?? ''),
        (string) ($data['campaign_id'] ?? ''),
        (string) ($data['job_id'] ?? ''),
        is_array($data['variants'] ?? NULL) ? $data['variants'] : [],
      );
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_callback', 'message' => $e->getMessage()], 422);
    }
    return new JsonResponse([
      'ok' => TRUE,
      'newly_processed' => $result['newly_processed'],
      'variant_count' => count($result['variants']),
    ]);
  }

}
