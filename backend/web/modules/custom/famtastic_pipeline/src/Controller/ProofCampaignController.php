<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\PipelineRepository;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public, token-scoped proof campaign API.
 *
 * Same authentication style as PipelineController: the X-Prospect-Token header
 * resolves the prospect through the repository, and every read/write is scoped
 * to that prospect.
 */
class ProofCampaignController extends ControllerBase {

  public function __construct(
    protected PipelineRepository $repository,
    protected ProofCampaignService $proofCampaigns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.repository'),
      $container->get('famtastic_pipeline.proof_campaign_service'),
    );
  }

  /**
   * POST /api/pipeline/proof-campaign — idempotent create.
   *
   * Returns the existing active campaign when the prospect already has one;
   * otherwise generates a fresh campaign with three variants.
   */
  public function createCampaign(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }

    $existing = $this->proofCampaigns->getForProspect($prospect);
    if ($existing) {
      $campaign = $this->refreshExpiry($existing['campaign']);
      if ($campaign->get('status')->value === 'active') {
        return new JsonResponse([
          'ok' => TRUE,
          'existing' => TRUE,
          'campaign' => $this->campaignPayload($campaign),
          'variants' => $this->variantPayloads($existing['variants']),
        ]);
      }
    }

    try {
      $created = $this->proofCampaigns->createForProspect($prospect);
    }
    catch (\Throwable $e) {
      $this->getLogger('famtastic_pipeline')->error('Proof campaign creation failed: @m', ['@m' => $e->getMessage()]);
      return $this->error('generation_failed', 502, 'Could not generate design proofs. Please try again.');
    }

    return new JsonResponse([
      'ok' => TRUE,
      'existing' => FALSE,
      'campaign' => $this->campaignPayload($created['campaign']),
      'variants' => $this->variantPayloads($created['variants']),
    ], 201);
  }

  /**
   * GET /api/pipeline/proof-campaign — campaign + variants for this token.
   */
  public function show(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $found = $this->proofCampaigns->getForProspect($prospect);
    if (!$found) {
      return $this->error('no_campaign', 404, 'No proof campaign exists yet.');
    }
    $campaign = $this->refreshExpiry($found['campaign']);
    return $this->noStore(new JsonResponse([
      'ok' => TRUE,
      'campaign' => $this->campaignPayload($campaign),
      'variants' => $this->variantPayloads($found['variants']),
    ]));
  }

  /**
   * POST /api/pipeline/proof-campaign/select — {variant_id, package}.
   */
  public function selectAction(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $found = $this->proofCampaigns->getForProspect($prospect);
    if (!$found) {
      return $this->error('no_campaign', 404, 'No proof campaign exists yet.');
    }
    $campaign = $this->refreshExpiry($found['campaign']);
    if ($campaign->get('status')->value === 'expired') {
      return $this->error('campaign_expired', 410, 'This proof campaign has expired.');
    }
    if ($campaign->get('status')->value !== 'active') {
      return $this->error('campaign_not_active', 409, 'This proof campaign is no longer active.');
    }

    $data = $this->jsonBody($request);
    try {
      $campaign = $this->proofCampaigns->select(
        $campaign,
        (string) ($data['variant_id'] ?? ''),
        (string) ($data['package'] ?? ''),
      );
    }
    catch (\InvalidArgumentException $e) {
      return $this->error('invalid_selection', 422, $e->getMessage());
    }

    return new JsonResponse([
      'ok' => TRUE,
      'campaign' => $this->campaignPayload($campaign),
      'variants' => $this->variantPayloads($found['variants']),
    ]);
  }

  // ------------------------------------------------------------------------
  // Helpers.
  // ------------------------------------------------------------------------

  /**
   * Resolves and validates the prospect from the request token.
   */
  protected function resolveProspect(Request $request): ?Prospect {
    return $this->repository->loadProspectByToken($this->readToken($request));
  }

  /**
   * Reads the token from header, then query, then JSON body.
   */
  protected function readToken(Request $request): string {
    $token = $request->headers->get('X-Prospect-Token', '');
    if (!$token) {
      $token = (string) $request->query->get('token', '');
    }
    if (!$token) {
      $token = (string) ($this->jsonBody($request)['token'] ?? '');
    }
    return trim($token);
  }

  /**
   * Lazily marks an active-but-past-due campaign expired.
   */
  protected function refreshExpiry(ProofCampaign $campaign): ProofCampaign {
    if ($campaign->get('status')->value === 'active' && $campaign->isExpired()) {
      $campaign->set('status', 'expired');
      $campaign->save();
    }
    return $campaign;
  }

  /**
   * Prospect-safe campaign payload.
   */
  protected function campaignPayload(ProofCampaign $campaign): array {
    return [
      'id' => (int) $campaign->id(),
      'campaign_id' => $campaign->get('campaign_id')->value,
      'business_name' => $campaign->get('business_name')->value,
      'status' => $campaign->get('status')->value,
      'generation_status' => $campaign->get('generation_status')->value,
      'expires_at' => (int) $campaign->get('expires_at')->value,
      'selected_variant' => $campaign->get('selected_variant')->value,
      'selected_package' => $campaign->get('selected_package')->value,
      'selected_at' => $campaign->get('selected_at')->value ? (int) $campaign->get('selected_at')->value : NULL,
      'created' => (int) $campaign->get('created')->value,
    ];
  }

  /**
   * Prospect-safe variant payloads.
   */
  protected function variantPayloads(array $variants): array {
    $out = [];
    foreach ($variants as $variant) {
      $out[] = [
        'id' => (int) $variant->id(),
        'direction_id' => $variant->get('direction_id')->value,
        'direction_name' => $variant->get('direction_name')->value,
        'preview_url' => $variant->get('preview_url')->value,
        'artifact_path' => $variant->get('artifact_path')->value,
        'thumbnail_path' => $variant->get('thumbnail_path')->value,
        'design_dna' => $variant->get('design_dna')->value,
      ];
    }
    return $out;
  }

  /**
   * Decodes the JSON body (cached per request).
   */
  protected function jsonBody(Request $request): array {
    static $cache = [];
    $key = spl_object_id($request);
    if (!array_key_exists($key, $cache)) {
      $decoded = json_decode($request->getContent() ?: '[]', TRUE);
      $cache[$key] = is_array($decoded) ? $decoded : [];
    }
    return $cache[$key];
  }

  /**
   * Standard JSON error.
   */
  protected function error(string $code, int $status, ?string $message = NULL): JsonResponse {
    return new JsonResponse(['ok' => FALSE, 'error' => $code, 'message' => $message ?? $code], $status);
  }

  /**
   * Marks a response uncacheable — token-scoped GETs must never be page-cached.
   */
  protected function noStore(JsonResponse $response): JsonResponse {
    $response->setPrivate();
    $response->setMaxAge(0);
    $response->headers->addCacheControlDirective('no-store', TRUE);
    return $response;
  }

}
