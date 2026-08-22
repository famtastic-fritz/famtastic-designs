<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\Prospect;
use GuzzleHttp\ClientInterface;

/**
 * Creates the source-bound, fail-closed handoff for website_proof.generate.v1.
 *
 * This service is intentionally not a proof renderer and does not send mail.
 * It makes the input, route, provider-preflight result, Build DNA requirement,
 * and return boundary durable before the existing asynchronous proof adapter is
 * allowed to dispatch work. The final provider must return a complete Build DNA
 * record; this service's preflight record is never an owner-delivery artifact.
 */
final class ProofRunnerContractService {

  public const ROUTINE = 'website_proof.generate.v1';
  public const CONTRACT_SCHEMA = 'famtastic.proof-runner-request.v1';

  /** The only profile permitted for a public pre-registration lead. */
  private const PUBLIC_INITIAL = [
    'id' => 'public_initial.v1',
    'proof_count' => 3,
    'directions' => [
      'a' => ['name' => 'Safe', 'intent' => 'polished, familiar, credible, and low-risk'],
      'b' => ['name' => 'Medium FAMtastic', 'intent' => 'expressive, energetic, and clearly differentiated'],
      'c' => ['name' => 'Ultra FAMtastic', 'intent' => 'the strongest campaign-level visual idea'],
    ],
    'customer_visibility' => 'owner_review_only',
  ];

  /** The equivalent three-direction contract for an authenticated workspace. */
  private const PORTAL_INITIAL = [
    'id' => 'portal_initial.v1',
    'proof_count' => 3,
    'directions' => [
      'a' => ['name' => 'Safe', 'intent' => 'polished, familiar, credible, and low-risk'],
      'b' => ['name' => 'Medium FAMtastic', 'intent' => 'expressive, energetic, and clearly differentiated'],
      'c' => ['name' => 'Ultra FAMtastic', 'intent' => 'the strongest campaign-level visual idea'],
    ],
    'customer_visibility' => 'owner_review_only',
  ];

  /** Three additive directions for the account-owned six-direction package. */
  private const PORTAL_SHOWCASE = [
    'id' => 'portal_showcase.v1',
    'proof_count' => 3,
    'combined_proof_count' => 6,
    'directions' => [
      'd' => ['name' => 'Ultra FAMtastic · Direction 2', 'intent' => 'a second maximum-FAMtastic direction with a distinct visual system and conversion path'],
      'e' => ['name' => 'Ultra FAMtastic · Direction 3', 'intent' => 'a third maximum-FAMtastic direction with a distinct visual system and conversion path'],
      'f' => ['name' => 'Ultra FAMtastic · Direction 4', 'intent' => 'a fourth maximum-FAMtastic direction with a distinct visual system and conversion path'],
    ],
    'customer_visibility' => 'owner_review_only',
  ];

  /**
   * Input keys allowed to leave the authenticated portal as build context.
   *
   * Deliberately excludes contact details, passwords, tokens, and free-form
   * customer-managed provider credentials. The resulting contract contains a
   * contact hash for correlation, never an email address.
   */
  private const PORTAL_INTAKE_KEYS = [
    'primary_goal', 'secondary_goals', 'success_metrics', 'ideal_customer',
    'customer_pain_points', 'products_services', 'desired_actions',
    'required_features', 'integrations', 'page_list', 'content_status',
    'copywriting_needs', 'photo_asset_status', 'brand_status',
    'style_preferences', 'reference_sites', 'reference_site_reasons',
    'competitors', 'seo_keywords', 'service_locations', 'business_hours',
    'accessibility_needs', 'privacy_legal_needs', 'ecommerce_details',
    'product_count', 'shipping_pickup', 'booking_details', 'ai_agent_goals',
    'maintenance_needs', 'launch_timing', 'budget_context', 'decision_makers',
    'notes', 'business_model', 'industry', 'research_context',
    'existing_technology', 'desired_domains', 'domain_fallback',
    'custom_needs', 'preferred_colors',
    'colors_to_avoid', 'desired_feeling', 'styles_to_avoid',
    'visual_reference_notes', 'famtastic_level', 'allow_bolder_direction',
    'life_path_opt_in', 'ai_enrichment_mode', 'page_count', 'recommendation',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly FileSystemInterface $fileSystem,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ClientInterface $httpClient,
    private readonly OperationalLedger $ledger,
    private readonly BuildTelemetryService $telemetry,
  ) {}

  /**
   * Creates and registers a non-deliverable preflight record for one proof job.
   *
   * @return array{
   *   build_id:string,
   *   classification:string,
   *   contract_uri:string,
   *   build_dna_uri:string,
   *   profile:array,
   *   preflight:array,
   *   handoff:array
   * }
   */
  public function prepare(Prospect $prospect, array $context, array $job = []): array {
    $routine = $this->routineFor($context);
    $profile = $this->profileFor($context);
    $source = $this->normalizeSource($prospect, $context, $profile);
    $buildClass = $this->buildClass($context);
    $buildId = 'proof-runner-' . $this->uuid->generate();
    $createdAt = gmdate(DATE_ATOM, $this->time->getRequestTime());
    $preflight = $this->preflight($routine, $profile, $buildClass, $source, $buildId);

    $contract = [
      'schema' => self::CONTRACT_SCHEMA,
      'contract_version' => '1.0.0',
      'build_id' => $buildId,
      'idempotency_key' => $this->idempotencyKey($source, $job),
      'routine' => $routine,
      'created_at' => $createdAt,
      'classification' => $preflight['classification'],
      'source' => $source,
      'profile' => $profile,
      'build_class' => $buildClass,
      'provider_preflight' => $preflight,
      'mutation_policy' => [
        'customer_email' => 'forbidden',
        'outbox_enqueue' => 'forbidden',
        'checkout' => 'forbidden',
        'payment' => 'forbidden',
        'domain_purchase' => 'forbidden',
        'production_publish' => 'forbidden',
      ],
      'return_contract' => [
        'callback_required' => TRUE,
        'callback_must_correlate_build_id' => $buildId,
        'callback_must_include_complete_build_dna' => TRUE,
        'callback_must_include_provider_preflight_receipts' => TRUE,
        'callback_must_include_browser_qa' => TRUE,
        'callback_must_include_independent_visual_review' => TRUE,
        'callback_variants_must_include_sha256' => TRUE,
        'callback_build_dna_must_include_per_direction_html_hashes' => TRUE,
        'callback_variant_artifact' => [
          'required_fields' => ['direction_id', 'html', 'artifact_sha256'],
          'artifact_sha256' => 'sha256 of the exact callback html bytes',
          'final_build_dna_artifact' => ['role' => 'proof_html', 'required_fields' => ['direction_id', 'sha256']],
        ],
        'quality_contract' => [
          'quality_status' => 'passed',
          'technical_status' => 'passed',
          'visual' => ['independent' => TRUE, 'status' => 'passed', 'decision' => 'required', 'reviewer' => 'required'],
        ],
        'owner_review_required_before_customer_visibility' => TRUE,
      ],
    ];
    $this->assertNoRawContact($contract);

    $directory = $this->runDirectory($buildId);
    $contractUri = $directory . '/proof-runner-request.json';
    $contractJson = $this->json($contract);
    $this->write($contractUri, $contractJson);

    $dna = $this->preflightBuildDna($buildId, $createdAt, $routine, $source, $profile, $buildClass, $preflight, $contractUri, $contractJson);
    $this->validatePreflightBuildDna($dna, $routine, $contractUri, $contractJson);
    $buildDnaUri = $directory . '/build-dna.json';
    $this->write($buildDnaUri, $this->json($dna));
    $telemetryId = $this->telemetry->recordBuildDna($dna);

    $event = $preflight['ready'] ? 'proof.runner.preflight_ready' : 'proof.runner.preflight_gated';
    $this->ledger->recordEvent(
      'proof-runner:' . $buildId,
      $event,
      [
        'build_id' => $buildId,
        'profile_id' => $profile['id'],
        'transport' => $preflight['transport'],
        'status' => $preflight['status'],
        'reason' => $preflight['reason'],
        'build_telemetry_id' => $telemetryId,
        'source_type' => $source['type'],
      ],
      (int) $prospect->id(),
    );

    if (!$preflight['ready']) {
      throw new \RuntimeException('Proof runner preflight gated: ' . $preflight['reason']);
    }

    return [
      'build_id' => $buildId,
      'classification' => $preflight['classification'],
      'contract_uri' => $contractUri,
      'build_dna_uri' => $buildDnaUri,
      'profile' => $profile,
      'preflight' => $preflight,
      'handoff' => [
        'build_id' => $buildId,
        'contract_sha256' => hash('sha256', $contractJson),
        // The remote runner cannot dereference Drupal's private:// URI. It
        // receives this inline sanitized contract over the existing signed
        // dispatch boundary, while Drupal keeps the private evidence copies.
        'contract' => $contract,
        // Internal-only: ProofCampaignService consumes this before it passes
        // the returned, remote-safe envelope to Site Studio.
        'build_dna_uri' => $buildDnaUri,
        'build_dna_status' => 'preflight_only_final_callback_required',
        'source_type' => $source['type'],
        'profile_id' => $profile['id'],
        'idempotency_key' => $contract['idempotency_key'],
      ],
    ];
  }

  /** Returns TRUE only for a deliberately non-creative plumbing fixture. */
  public function isLocalContractFixture(array $run): bool {
    return ($run['classification'] ?? '') === 'local_contract_fixture';
  }

  /**
   * Resumes only the campaign attached to this exact intake correlation.
   *
   * A prospect can have several concurrent projects. The generic
   * ProofCampaignService::getForProspect() convenience lookup is therefore
   * deliberately not used for canonical runner jobs.
   *
   * @return array{campaign:\Drupal\famtastic_pipeline\Entity\ProofCampaign,variants:array}|null
   */
  public function existingCampaignForSource(Prospect $prospect, array $context): ?array {
    $routine = $this->routineFor($context);
    $profile = $this->profileFor($context);
    $source = $this->normalizeSource($prospect, $context, $profile);
    $registered = $this->telemetry->loadBuildDnaForSource($source, $routine);
    if (!$registered) {
      return NULL;
    }
    $manifest = (array) $registered['manifest'];
    $run = (array) ($manifest['run'] ?? []);
    if (($manifest['recipe']['routine'] ?? '') !== $routine
      || ($manifest['recipe']['profile_id'] ?? '') !== $profile['id']) {
      return NULL;
    }
    $campaignEntityId = (int) ($run['proof_campaign_id'] ?? 0);
    if ($campaignEntityId < 1) {
      return NULL;
    }
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign|null $campaign */
    $campaign = $this->entities->getStorage('proof_campaign')->load($campaignEntityId);
    if (!$campaign || (int) $campaign->get('prospect_id')->target_id !== (int) $prospect->id()) {
      return NULL;
    }
    $variantStorage = $this->entities->getStorage('proof_variant');
    $variantIds = $variantStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', $campaign->id())
      ->sort('direction_id', 'ASC')
      ->execute();
    return [
      'campaign' => $campaign,
      'variants' => $variantIds ? array_values($variantStorage->loadMultiple($variantIds)) : [],
    ];
  }

  /**
   * Binds the preflight to the newly created campaign before remote dispatch.
   *
   * The preflight record is mutable only until the provider returns its final
   * manifest. It remains visibly `preflight` in the Build DNA projection and
   * cannot be used to stage a customer delivery.
   */
  public function linkCampaign(array $run, ProofCampaign $campaign): array {
    $run['handoff'] = $this->linkCampaignHandoff((array) ($run['handoff'] ?? []), $campaign);
    return $run;
  }

  /**
   * Links a prepared runner handoff before outbound HTTP dispatch begins.
   *
   * This sequencing closes the fast-callback race: callback verification can
   * always find the source-bound campaign record before the provider receives
   * the signed request.
   */
  public function linkCampaignHandoff(array $handoff, ProofCampaign $campaign): array {
    $buildDnaUri = (string) ($handoff['build_dna_uri'] ?? '');
    $buildId = (string) ($handoff['build_id'] ?? '');
    if ($buildDnaUri === '') {
      throw new \RuntimeException('Proof runner preflight evidence is unavailable for campaign linkage.');
    }
    $contents = file_get_contents($buildDnaUri);
    if ($contents === FALSE) {
      throw new \RuntimeException('Proof runner preflight evidence is unavailable for campaign linkage.');
    }
    $dna = json_decode($contents, TRUE, 512, JSON_THROW_ON_ERROR);
    if (($dna['build_id'] ?? '') !== $buildId) {
      throw new \RuntimeException('Proof runner linkage does not match the prepared Build DNA record.');
    }
    $campaignContext = [
      'proof_campaign_id' => (int) $campaign->id(),
      'campaign_id' => (string) $campaign->get('campaign_id')->value,
    ];
    $dna['run']['proof_campaign_id'] = $campaignContext['proof_campaign_id'];
    $dna['run']['campaign_id'] = $campaignContext['campaign_id'];
    $dna['run']['status'] = 'dispatched_waiting_callback';
    $dna['retrieval']['database']['status'] = 'registered_preflight_waiting_callback';
    $dna['retrieval']['site_studio']['status'] = 'final_build_dna_required_before_owner_stage';
    $contractUri = (string) ($dna['artifacts'][0]['path'] ?? '');
    $contractHash = (string) ($dna['artifacts'][0]['sha256'] ?? '');
    if ($contractUri === '' || $contractHash === '') {
      throw new \RuntimeException('Proof runner linkage is missing its immutable contract receipt.');
    }
    $this->validatePreflightBuildDna($dna, (string) ($dna['recipe']['routine'] ?? ''), $contractUri, $this->readContractForHash($contractUri, $contractHash));
    $this->write($buildDnaUri, $this->json($dna));
    $this->telemetry->recordBuildDna($dna);
    $this->ledger->recordEvent(
      'proof-runner:linked:' . $buildId . ':' . $campaignContext['campaign_id'],
      'proof.runner.dispatched',
      [
        'build_id' => $buildId,
        'campaign_id' => $campaignContext['campaign_id'],
        'proof_campaign_id' => $campaignContext['proof_campaign_id'],
        'profile_id' => $dna['recipe']['profile_id'] ?? '',
      ],
      (int) $campaign->get('prospect_id')->target_id,
      (int) $campaign->id(),
    );

    $handoff['preflight_build_dna_sha256'] = hash('sha256', $this->json($dna));
    $handoff['campaign_id'] = $campaignContext['campaign_id'];
    $handoff['proof_campaign_id'] = $campaignContext['proof_campaign_id'];
    unset($handoff['build_dna_uri']);
    return $handoff;
  }

  /** TRUE for the one canonical asynchronous creative routine. */
  public static function isSupportedRoutine(string $routine): bool {
    return $routine === self::ROUTINE;
  }

  private function routineFor(array $context): string {
    $routine = trim((string) ($context['routine'] ?? self::ROUTINE));
    if (!self::isSupportedRoutine($routine)) {
      throw new \InvalidArgumentException('Unsupported proof runner routine.');
    }
    return $routine;
  }

  /** Chooses an explicit phase profile; no prior proof leaks into another run. */
  private function profileFor(array $context): array {
    $routine = $this->routineFor($context);
    $phase = trim((string) ($context['proof_phase'] ?? 'initial'));
    if (!in_array($phase, ['initial', 'showcase'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported proof runner phase.');
    }
    if ($phase === 'showcase') {
      if (empty($context['website_request_id'])) {
        throw new \InvalidArgumentException('Showcase proof runner requires an authenticated website request source.');
      }
      return self::PORTAL_SHOWCASE;
    }
    $declared = trim((string) ($context['delivery_class'] ?? ''));
    if ($declared === 'public_initial' || !empty($context['public_preview_delivery_id'])) {
      return self::PUBLIC_INITIAL;
    }
    if (!empty($context['website_request_id'])) {
      return self::PORTAL_INITIAL;
    }
    throw new \InvalidArgumentException('Proof runner requires a public preview delivery or an authenticated website request source.');
  }

  /**
   * Reconstructs only the business facts that were actually submitted.
   *
   * This intentionally never invents a business title, campaign tagline,
   * customer testimonials, price, or research conclusion.
   */
  private function normalizeSource(Prospect $prospect, array $context, array $profile): array {
    $contact = (string) $prospect->get('public_email')->value;
    $common = [
      'prospect_id' => (int) $prospect->id(),
      'business_name' => (string) $prospect->label(),
      'business_category' => (string) $prospect->get('business_category')->value,
      'business_description' => (string) $prospect->get('business_description')->value,
      'service_area' => (string) $prospect->get('service_area')->value,
      'contact_hash' => $this->ledger->contactHash($contact),
      'campaign_key' => (string) $prospect->get('campaign')->value,
      'source_key' => (string) $prospect->get('source')->value,
    ];

    if ($profile['id'] === self::PUBLIC_INITIAL['id']) {
      $intakeId = (int) ($context['intake_id'] ?? 0);
      $deliveryId = (int) ($context['public_preview_delivery_id'] ?? 0);
      if (!$intakeId || !$deliveryId) {
        throw new \InvalidArgumentException('Public proof runner requires both public preview delivery and intake correlation.');
      }
      $intake = $this->entities->getStorage('famtastic_intake')->load($intakeId);
      if (!$intake || (int) $intake->get('prospect_ref')->target_id !== (int) $prospect->id()) {
        throw new \RuntimeException('Public intake does not belong to the proof prospect.');
      }
      $fact = static function (object $entity, string $field): string {
        return $entity->hasField($field) ? trim((string) $entity->get($field)->value) : '';
      };
      return $common + [
        'type' => 'public_solution_finder_intake',
        'proof_phase' => 'initial',
        'public_preview_delivery_id' => $deliveryId,
        'intake_id' => $intakeId,
        'facts' => array_filter([
          'primary_goal' => $fact($intake, 'primary_goal'),
          'primary_cta' => $fact($intake, 'primary_cta'),
          'services' => $fact($intake, 'services'),
          'about' => $fact($intake, 'about'),
          'ideal_customer' => $fact($intake, 'ideal_customer'),
          'customer_problem' => $fact($intake, 'customer_problem'),
          'brand_colors' => $fact($intake, 'brand_colors'),
          'style_preferences' => $fact($intake, 'style_preferences'),
          'reference_sites' => $fact($intake, 'reference_sites'),
          'existing_domain' => $fact($intake, 'existing_domain'),
        ], static fn(string $value): bool => $value !== ''),
      ];
    }

    $requestId = (int) ($context['website_request_id'] ?? 0);
    $publicId = trim((string) ($context['website_request_public_id'] ?? ''));
    if (!$requestId || $publicId === '') {
      throw new \InvalidArgumentException('Portal proof runner requires a submitted v3 request and opaque public ID.');
    }
    $request = $this->database->select('famtastic_project_request', 'r')->fields('r', ['id', 'prospect_id', 'public_id', 'status', 'intake_data'])
      ->condition('id', $requestId)->range(0, 1)->execute()->fetchAssoc();
    if (!$request || (int) $request['prospect_id'] !== (int) $prospect->id() || !hash_equals((string) $request['public_id'], $publicId) || (string) $request['status'] === 'draft') {
      throw new \RuntimeException('Portal website request does not belong to the proof prospect.');
    }
    try {
      $intake = json_decode((string) $request['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Throwable) {
      throw new \RuntimeException('Portal website request has no valid normalized intake.');
    }
    if (!is_array($intake) || ($intake['schema_version'] ?? '') !== 'website_discovery_v3') {
      throw new \InvalidArgumentException('Portal proof runner requires a submitted v3 request and opaque public ID.');
    }
    $facts = [];
    foreach (self::PORTAL_INTAKE_KEYS as $key) {
      if (array_key_exists($key, $intake) && (is_scalar($intake[$key]) || is_array($intake[$key]))) {
        $facts[$key] = $intake[$key];
      }
    }
    return $common + [
      'type' => 'authenticated_website_request',
      'proof_phase' => (string) ($context['proof_phase'] ?? 'initial'),
      'website_request_id' => $requestId,
      'website_request_public_id' => $publicId,
      'facts' => $facts,
    ];
  }

  private function buildClass(array $context): string {
    $buildClass = trim((string) ($context['build_class'] ?? $this->config('proof_runner_default_build_class', 'low')));
    $allowed = ['free', 'low', 'medium', 'premium', 'premium_brain_free_workers', 'custom'];
    if (!in_array($buildClass, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Unsupported proof runner build class.');
    }
    return $buildClass;
  }

  /**
   * Runs only an explicitly declared route. A missing route never becomes a
   * local mockup or an unrecorded assistant session.
   */
  private function preflight(string $routine, array $profile, string $buildClass, array $source, string $buildId): array {
    $transport = trim((string) $this->config('proof_runner_transport', 'disabled'));
    if ($transport === 'local_contract_fixture') {
      $allowed = getenv('FAMTASTIC_ALLOW_LOCAL_CONTRACT_FIXTURE') === '1';
      return [
        'status' => $allowed ? 'local_contract_fixture_validated' : 'gated',
        'ready' => $allowed,
        'transport' => 'local_contract_fixture',
        'classification' => 'local_contract_fixture',
        'reason' => $allowed
          ? 'Explicit local contract fixture; it cannot generate, publish, or send a proof.'
          : 'FAMTASTIC_ALLOW_LOCAL_CONTRACT_FIXTURE=1 is required for the local contract fixture.',
        'provider' => ['id' => 'deterministic_contract_fixture', 'model_status' => 'not_applicable'],
      ];
    }
    if ($transport === 'site_studio_dispatch') {
      $url = rtrim((string) (getenv('SITE_STUDIO_URL') ?: $this->config('studio_url', '')), '/');
      $secret = (string) (getenv('SITE_STUDIO_DISPATCH_SECRET') ?: Settings::get('site_studio_dispatch_secret'));
      $ready = $url !== '' && $secret !== '';
      return [
        'status' => $ready ? 'dispatch_boundary_configured' : 'gated',
        'ready' => $ready,
        'transport' => 'site_studio_dispatch',
        'classification' => 'proof_runner_preflight',
        'reason' => $ready
          ? 'Signed Site Studio dispatch boundary is configured; final provider receipts remain required in the callback.'
          : 'Signed Site Studio dispatch endpoint and secret are both required; no fallback proof renderer is allowed.',
        'provider' => ['id' => 'site_studio_dispatch_boundary', 'model_status' => 'resolved_by_runner'],
      ];
    }
    if ($transport === 'external_runner') {
      return $this->externalPreflight($routine, $profile, $buildClass, $source, $buildId);
    }
    return [
      'status' => 'gated',
      'ready' => FALSE,
      'transport' => $transport === '' ? 'disabled' : $transport,
      'classification' => 'proof_runner_preflight',
      'reason' => 'No declared proof runner transport is configured. The job is intentionally fail-closed.',
      'provider' => ['id' => 'unresolved', 'model_status' => 'not_applicable'],
    ];
  }

  /**
   * Optional generic external runner preflight. It transports a sanitized
   * source envelope only; execution and model selection stay provider-neutral.
   */
  private function externalPreflight(string $routine, array $profile, string $buildClass, array $source, string $buildId): array {
    $endpoint = rtrim((string) $this->config('proof_runner_external_url', ''), '/');
    $secret = (string) (getenv('FAMTASTIC_PROOF_RUNNER_SECRET') ?: Settings::get('famtastic_proof_runner_secret'));
    if ($endpoint === '' || $secret === '') {
      return [
        'status' => 'gated', 'ready' => FALSE, 'transport' => 'external_runner', 'classification' => 'proof_runner_preflight',
        'reason' => 'External runner URL and dispatch secret are required; no provider fallback is allowed.',
        'provider' => ['id' => 'external_runner', 'model_status' => 'unresolved'],
      ];
    }
    $payload = [
      'schema' => 'famtastic.proof-runner-preflight.v1',
      'build_id' => $buildId,
      'routine' => $routine,
      'profile_id' => $profile['id'],
      'build_class' => $buildClass,
      'source_type' => $source['type'],
      'required_capabilities' => ['intake_validation', 'research', 'creative_direction', 'image_generation', 'prototype_construction', 'browser_qa', 'independent_visual_review', 'build_dna'],
    ];
    $body = $this->json($payload);
    try {
      $response = $this->httpClient->request('POST', $endpoint . '/preflight', [
        'headers' => [
          'Content-Type' => 'application/json',
          'X-FAMtastic-Signature' => 'sha256=' . hash_hmac('sha256', $body, $secret),
        ],
        'body' => $body,
        'timeout' => 15,
        'http_errors' => FALSE,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      $ready = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && is_array($data) && ($data['status'] ?? '') === 'ready';
      return [
        'status' => $ready ? 'external_preflight_passed' : 'gated',
        'ready' => $ready,
        'transport' => 'external_runner',
        'classification' => 'proof_runner_preflight',
        'reason' => $ready ? 'External runner returned an explicit ready preflight.' : 'External runner preflight did not return status=ready.',
        'provider' => [
          'id' => (string) ($data['provider_id'] ?? 'external_runner'),
          'model_status' => (string) ($data['model_status'] ?? 'not_disclosed_by_runtime'),
        ],
        'receipt' => is_array($data) ? $data : ['http_status' => $response->getStatusCode()],
      ];
    }
    catch (\Throwable $error) {
      return [
        'status' => 'gated', 'ready' => FALSE, 'transport' => 'external_runner', 'classification' => 'proof_runner_preflight',
        'reason' => 'External runner preflight failed: ' . mb_substr($error->getMessage(), 0, 280),
        'provider' => ['id' => 'external_runner', 'model_status' => 'unresolved'],
      ];
    }
  }

  private function preflightBuildDna(string $buildId, string $createdAt, string $routine, array $source, array $profile, string $buildClass, array $preflight, string $contractUri, string $contractJson): array {
    $stageStatus = $preflight['ready'] ? 'passed_preflight_only' : 'gated';
    return [
      'schema' => 'famtastic.build-dna.v1',
      'build_id' => $buildId,
      'classification' => $preflight['classification'],
      'created_at' => $createdAt,
      'run' => [
        'run_id' => $buildId,
        'status' => $preflight['ready'] ? 'preflight_ready' : 'gated',
        'prospect_id' => $source['prospect_id'],
        'source_type' => $source['type'],
        'source_correlation' => array_diff_key($source, array_flip(['facts', 'business_name', 'business_category', 'business_description', 'service_area'])),
        'final_build_dna_required' => TRUE,
      ],
      'repository' => [
        'name' => 'famtastic-designs',
        'revision' => $this->repositoryRevision(),
        'worktree_state' => 'runtime_not_disclosed',
      ],
      'recipe' => [
        'routine' => $routine,
        'version' => '1.0.0',
        'build_class' => $buildClass,
        'profile_id' => $profile['id'],
        'proof_count' => $profile['proof_count'],
        'direction_contract' => $profile['directions'],
      ],
      'stages' => [[
        'stage_id' => 'provider-preflight',
        'sequence' => 1,
        'attempt' => 1,
        'capability' => 'proof_runner_provider_preflight',
        'execution' => [
          'kind' => 'deterministic_contract_and_provider_preflight',
          'provider' => ['id' => (string) $preflight['provider']['id']],
          'model' => ['id' => NULL, 'status' => (string) $preflight['provider']['model_status']],
          'timing' => ['status' => 'drupal_request_measured_at_contract_creation'],
          'cost' => ['status' => 'not_charged_by_preflight_contract'],
          'input' => ['contract_uri' => $contractUri, 'source_type' => $source['type']],
          'output' => ['status' => $preflight['status'], 'transport' => $preflight['transport']],
        ],
        'result' => ['status' => $stageStatus, 'reason' => $preflight['reason']],
      ]],
      'artifacts' => [[
        'role' => 'proof_runner_request_contract',
        'path' => $contractUri,
        'sha256' => hash('sha256', $contractJson),
        'rights_status' => 'internal_operational_record',
      ]],
      'retrieval' => [
        'filesystem' => ['canonical_manifest' => 'build-dna.json', 'contract_uri' => $contractUri],
        'database' => ['status' => 'registered_preflight_only', 'build_key' => 'build-dna:' . $buildId],
        'site_studio' => ['status' => 'final_build_dna_required_before_owner_stage', 'routine' => $routine],
      ],
      'integrity' => ['artifact_hash_algorithm' => 'sha256'],
      'quality' => [
        'status' => 'not_reviewed',
        'open_gates' => ['provider execution', 'browser QA', 'independent visual review', 'complete Build DNA callback', 'owner approval'],
      ],
    ];
  }

  /** Validates the initial record without pretending it is a final proof. */
  private function validatePreflightBuildDna(array $dna, string $routine, string $contractUri, string $contractJson): void {
    if (($dna['schema'] ?? '') !== 'famtastic.build-dna.v1' || ($dna['build_id'] ?? '') === '' || ($dna['recipe']['routine'] ?? '') !== $routine || !self::isSupportedRoutine($routine)) {
      throw new \RuntimeException('Proof runner Build DNA did not satisfy the required identity contract.');
    }
    if (($dna['artifacts'][0]['path'] ?? '') !== $contractUri || !hash_equals((string) ($dna['artifacts'][0]['sha256'] ?? ''), hash('sha256', $contractJson))) {
      throw new \RuntimeException('Proof runner Build DNA contract hash does not match its source artifact.');
    }
    if (!in_array((string) ($dna['retrieval']['database']['status'] ?? ''), ['registered_preflight_only', 'registered_preflight_waiting_callback'], TRUE) || ($dna['retrieval']['site_studio']['status'] ?? '') !== 'final_build_dna_required_before_owner_stage') {
      throw new \RuntimeException('Proof runner Build DNA has an unsafe retrieval state.');
    }
  }

  private function assertNoRawContact(array $contract): void {
    $forbidden = ['email', 'public_email', 'phone', 'public_phone', 'password', 'token', 'access_token', 'refresh_token', 'api_key', 'secret'];
    $walk = function (mixed $value, string $key = '') use (&$walk, $forbidden): void {
      if (in_array(mb_strtolower($key), $forbidden, TRUE)) {
        throw new \InvalidArgumentException('Proof runner contracts may not contain raw contact or credential fields.');
      }
      if (is_array($value)) {
        foreach ($value as $childKey => $childValue) {
          $walk($childValue, (string) $childKey);
        }
      }
    };
    $walk($contract);
  }

  private function runDirectory(string $buildId): string {
    $directory = 'private://famtastic-proof-runs/' . preg_replace('/[^a-z0-9-]/', '-', strtolower($buildId));
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \RuntimeException('Proof runner evidence directory could not be prepared.');
    }
    return $directory;
  }

  private function write(string $uri, string $contents): void {
    // Drupal's private:// wrapper deliberately does not implement advisory
    // locks. Resolve the already-created private directory to its local path,
    // write a sibling temporary file with a local lock, and atomically rename
    // it into place. This retains durable private evidence without asking a
    // remote runner to dereference a private URI.
    $directory = $this->fileSystem->realpath(dirname($uri));
    if ($directory === FALSE || $directory === '') {
      throw new \RuntimeException('Proof runner evidence directory could not be resolved.');
    }
    $target = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($uri);
    $temporary = tempnam($directory, '.proof-runner-');
    if ($temporary === FALSE) {
      throw new \RuntimeException('Proof runner evidence temporary file could not be created.');
    }
    $written = file_put_contents($temporary, $contents, LOCK_EX);
    if ($written === FALSE || $written !== strlen($contents) || !rename($temporary, $target)) {
      @unlink($temporary);
      throw new \RuntimeException('Proof runner evidence could not be written.');
    }
  }

  /** Reads the immutable dispatch receipt only to verify the stored checksum. */
  private function readContractForHash(string $uri, string $expectedHash): string {
    $contents = file_get_contents($uri);
    if ($contents === FALSE || !hash_equals($expectedHash, hash('sha256', $contents))) {
      throw new \RuntimeException('Proof runner contract receipt changed after dispatch preparation.');
    }
    return $contents;
  }

  private function idempotencyKey(array $source, array $job): string {
    $key = trim((string) ($job['job_key'] ?? ''));
    if ($key !== '') {
      return 'proof-runner:' . $key;
    }
    $correlation = (string) ($source['public_preview_delivery_id'] ?? $source['website_request_id'] ?? $source['prospect_id']);
    return 'proof-runner:' . $source['type'] . ':' . $correlation . ':' . (string) ($source['proof_phase'] ?? 'initial');
  }

  private function repositoryRevision(): string {
    $configured = trim((string) (getenv('FAMTASTIC_RELEASE_SHA') ?: ''));
    if (preg_match('/^[a-f0-9]{7,64}$/i', $configured)) {
      return strtolower($configured);
    }
    $marker = dirname(\Drupal::root()) . '/.backend-release';
    if (is_readable($marker) && preg_match('/^commit=([a-f0-9]{7,64})$/mi', (string) file_get_contents($marker), $matches)) {
      return strtolower($matches[1]);
    }
    return 'not_available';
  }

  private function config(string $key, mixed $default): mixed {
    $value = $this->configFactory->get('famtastic_pipeline.settings')->get($key);
    return $value === NULL || $value === '' ? $default : $value;
  }

  private function json(array $value): string {
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
  }

}
