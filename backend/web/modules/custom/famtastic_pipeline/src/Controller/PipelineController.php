<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\famtastic_pipeline\Entity\Order;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\FulfillmentService;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\famtastic_pipeline\Service\PaymentGatewayManager;
use Drupal\famtastic_pipeline\Service\PipelineRepository;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\famtastic_pipeline\Service\StripeGateway;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public, token-scoped prospect pipeline API.
 *
 * Every endpoint resolves the prospect from the link token and scopes all reads
 * and writes to that prospect, so one prospect can never touch another's data.
 * Internal fields (discovery notes) are never serialized to the prospect.
 */
class PipelineController extends ControllerBase {

  public function __construct(
    protected PipelineRepository $repository,
    protected PaymentGatewayManager $gatewayManager,
    protected FulfillmentService $fulfillment,
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected FileSystemInterface $fileSystem,
    protected TimeInterface $time,
    protected OperationalLedger $ledger,
    protected ProofCampaignService $proofCampaigns,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.repository'),
      $container->get('famtastic_pipeline.gateway_manager'),
      $container->get('famtastic_pipeline.fulfillment'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('datetime.time'),
      $container->get('famtastic_pipeline.operational_ledger'),
      $container->get('famtastic_pipeline.proof_campaign_service'),
    );
  }

  /**
   * GET /api/pipeline/session — prospect-safe view + pipeline state.
   */
  public function session(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    if ($prospect->get('status')->value === 'new') {
      $prospect->set('status', 'viewed')->save();
    }
    return $this->noStore(new JsonResponse($this->safePayload($prospect)));
  }

  /**
   * POST /api/pipeline/confirm — corrections + contact + authorization → lead.
   */
  public function confirm(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $data = $this->jsonBody($request);

    if (empty($data['authorized'])) {
      return $this->error('authorization_required', 422, 'You must confirm you are authorized to represent this business.');
    }

    $corrections = is_array($data['corrections'] ?? NULL) ? $data['corrections'] : [];
    $confirmed = [];
    foreach (Prospect::PUBLIC_BUSINESS_FIELDS as $field) {
      if (array_key_exists($field, $corrections)) {
        $value = $this->sanitize((string) $corrections[$field]);
        $prospect->set($field, $value);
      }
      // Mark every business field as owner-confirmed at this step.
      $confirmed[$field] = TRUE;
    }

    $prospect->set('contact_name', $this->sanitize((string) ($data['contact_name'] ?? '')));
    $method = in_array($data['contact_method'] ?? '', ['email', 'phone', 'text'], TRUE) ? $data['contact_method'] : 'email';
    $prospect->set('contact_method', $method);
    $prospect->set('contact_value', $this->sanitize((string) ($data['contact_value'] ?? '')));
    $prospect->set('authorized', TRUE);
    $prospect->set('confirmed_fields', json_encode($confirmed));
    $prospect->set('confirmed_at', $this->time->getRequestTime());
    $prospect->set('status', 'lead');
    $prospect->save();

    return new JsonResponse(['ok' => TRUE, 'status' => 'lead', 'prospect' => $this->safePayload($prospect)]);
  }

  /**
   * POST /api/pipeline/checkout — create a Stripe test Checkout Session.
   */
  public function checkout(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $leadOrBeyond = ['lead', 'paid', 'intake_started', 'intake_complete', 'submitted_to_studio', 'proof_ready', 'revision_requested', 'approved', 'launched'];
    if (!in_array($prospect->get('status')->value, $leadOrBeyond, TRUE)) {
      return $this->error('confirm_first', 409, 'Please confirm your business before purchasing.');
    }
    $data = $this->jsonBody($request);
    $terms = $this->ledger->activeTerms();
    if (!$terms) {
      return $this->error('terms_unavailable', 503, 'Checkout terms are temporarily unavailable.');
    }
    if (
      empty($data['terms_accepted'])
      || !hash_equals($terms['checksum'], (string) ($data['terms_checksum'] ?? ''))
    ) {
      return $this->error('terms_acceptance_required', 422, 'Please accept the current service terms before checkout.');
    }

    $selection = $this->proofCampaigns->activeSelection($prospect);
    $offerKey = (string) ($selection['selected_package'] ?? $data['package'] ?? 'essential_199');
    if ($offerKey === 'basic_199') {
      $offerKey = 'essential_199';
    }
    $offer = $this->ledger->activeOffer($offerKey);
    if (!$offer || str_starts_with($offerKey, 'revision_')) {
      return $this->error('invalid_package', 422, 'Select a valid launch package.');
    }

    $order = $this->repository->getOrder($prospect);
    if ($order && $order->isPaid()) {
      return new JsonResponse(['ok' => TRUE, 'already_paid' => TRUE]);
    }
    if (!$order) {
      $order = Order::create([
        'prospect_ref' => $prospect->id(),
        'package' => $offer['offer_key'],
        'offer_version_id' => $offer['id'],
        'amount' => $offer['amount_minor'],
        'currency' => $offer['currency'],
        'payment_status' => 'pending',
      ]);
    }
    $contact = (string) ($prospect->get('contact_value')->value ?: $prospect->get('public_email')->value);
    $acceptanceId = $this->ledger->recordConsent(
      $contact,
      'accepted',
      (int) $prospect->id(),
      'website_terms',
      $terms['id'],
      [
        'terms_checksum' => $terms['checksum'],
        'offer_key' => $offer['offer_key'],
        'offer_version_id' => $offer['id'],
        'ip_hash' => hash('sha256', (string) ($request->getClientIp() ?: 'unknown')),
        'user_agent_hash' => hash('sha256', (string) $request->headers->get('User-Agent', '')),
      ],
    );
    $order
      ->set('package', $offer['offer_key'])
      ->set('offer_version_id', $offer['id'])
      ->set('amount', $offer['amount_minor'])
      ->set('currency', $offer['currency'])
      ->set('terms_version_id', $terms['id'])
      ->set('terms_acceptance_id', $acceptanceId)
      ->set('terms_accepted_at', $this->time->getRequestTime())
      ->save();

    $token = $this->readToken($request);
    $frontend = $this->frontendBase();
    $context = [
      'success_url' => $frontend . '/p/' . $token . '/return?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url' => $frontend . '/p/' . $token . '/cancel',
      'customer_email' => $prospect->get('contact_value')->value && $prospect->get('contact_method')->value === 'email'
        ? $prospect->get('contact_value')->value
        : $prospect->get('public_email')->value,
      'product_name' => $offer['name'],
    ];

    try {
      $gateway = $this->gatewayManager->active();
      $session = $gateway->createCheckoutSession($order, $context);
    }
    catch (\Throwable $e) {
      $this->getLogger('famtastic_pipeline')->error('Checkout failed: @m', ['@m' => $e->getMessage()]);
      return $this->error('checkout_failed', 502, 'Could not start checkout. Please try again.');
    }

    $order->set('stripe_checkout_session_id', $session['id']);
    if (!empty($session['payment_intent'])) {
      $order->set('stripe_payment_intent_id', $session['payment_intent']);
    }
    $order->save();

    return new JsonResponse([
      'ok' => TRUE,
      'url' => $session['url'],
      'session_id' => $session['id'],
      'gateway_mode' => $gateway->getMode(),
    ]);
  }

  /**
   * GET /api/pipeline/order-status — server-verified payment status.
   */
  public function orderStatus(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $order = $this->repository->getOrder($prospect);
    if (!$order) {
      return $this->noStore(new JsonResponse(['payment_status' => 'none', 'gateway_mode' => $this->gatewayManager->active()->getMode()]));
    }

    // Never trust the browser redirect: for real Stripe, reconcile server-side.
    if (!$order->isPaid() && $this->gatewayManager->active()->getMode() === 'stripe' && $order->get('stripe_checkout_session_id')->value) {
      try {
        $remote = $this->gatewayManager->active()->retrieveSession($order->get('stripe_checkout_session_id')->value);
        if (($remote['payment_status'] ?? '') === 'paid') {
          $this->fulfillment->markPaidBySession(
            $order->get('stripe_checkout_session_id')->value,
            $remote['payment_intent'] ?? NULL,
            'retrieve_' . $order->get('stripe_checkout_session_id')->value,
            $remote['amount_total'] ?? NULL,
            $remote['currency'] ?? NULL,
          );
          $order = $this->repository->getOrder($prospect);
        }
      }
      catch (\Throwable $e) {
        $this->getLogger('famtastic_pipeline')->warning('Retrieve failed: @m', ['@m' => $e->getMessage()]);
      }
    }

    return $this->noStore(new JsonResponse([
      'payment_status' => $order->get('payment_status')->value,
      'gateway_mode' => $this->gatewayManager->active()->getMode(),
    ]));
  }

  /**
   * POST /api/pipeline/intake — save intake (requires a paid order).
   */
  public function intake(Request $request): JsonResponse {
    [$prospect, $order, $gate] = $this->requirePaid($request);
    if ($gate) {
      return $gate;
    }
    $data = $this->jsonBody($request);

    $intake = $this->repository->getIntake($prospect);
    if (!$intake) {
      $storage = $this->entityTypeManagerService->getStorage('famtastic_intake');
      $intake = $storage->create([
        'prospect_ref' => $prospect->id(),
        'order_ref' => $order->id(),
      ]);
    }
    foreach (\Drupal\famtastic_pipeline\Entity\Intake::TEXT_FIELDS as $field) {
      if (array_key_exists($field, $data)) {
        $intake->set($field, $this->sanitize((string) $data[$field], TRUE));
      }
    }
    $intake->set('asset_ownership_confirmed', !empty($data['asset_ownership_confirmed']));
    $intake->set('submitted_at', $this->time->getRequestTime());
    $intake->save();

    if (in_array($prospect->get('status')->value, ['paid', 'intake_started'], TRUE)) {
      $prospect->set('status', 'intake_complete')->save();
    }

    return new JsonResponse(['ok' => TRUE, 'intake_id' => (int) $intake->id(), 'status' => 'intake_complete']);
  }

  /**
   * POST /api/pipeline/asset — upload a logo/photo (requires a paid order).
   */
  public function asset(Request $request): JsonResponse {
    [$prospect, $order, $gate] = $this->requirePaid($request);
    if ($gate) {
      return $gate;
    }

    $uploaded = $request->files->get('file');
    if (!$uploaded) {
      return $this->error('no_file', 422, 'No file uploaded.');
    }
    $allowedExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    $ext = strtolower($uploaded->getClientOriginalExtension());
    if (!in_array($ext, $allowedExt, TRUE)) {
      return $this->error('bad_file_type', 422, 'Only image files are accepted.');
    }
    if ($uploaded->getSize() > 8 * 1024 * 1024) {
      return $this->error('file_too_large', 422, 'Max file size is 8 MB.');
    }

    $dir = 'private://prospect-assets/' . $prospect->id();
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $uploaded->getClientOriginalName() ?: ('asset.' . $ext));
    $destination = $dir . '/' . $safeName;
    $data = file_get_contents($uploaded->getRealPath());
    $uri = $this->fileSystem->saveData($data, $destination, FileSystemInterface::EXISTS_RENAME);

    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();

    $intake = $this->repository->getIntake($prospect);
    if (!$intake) {
      $intake = $this->entityTypeManagerService->getStorage('famtastic_intake')->create([
        'prospect_ref' => $prospect->id(),
        'order_ref' => $order->id(),
      ]);
      $intake->save();
    }
    $intake->get('asset_refs')->appendItem($file->id());
    $intake->save();

    return new JsonResponse(['ok' => TRUE, 'file_id' => (int) $file->id(), 'filename' => $file->getFilename()]);
  }

  /**
   * POST /api/pipeline/approval — approve or request one revision.
   */
  public function approval(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $project = $this->repository->getProject($prospect);
    if (!$project || !$project->get('proof_url')->value) {
      return $this->error('no_proof_yet', 409, 'There is no proof to review yet.');
    }
    $data = $this->jsonBody($request);
    $action = $data['action'] ?? '';
    if ($action === 'approve') {
      $approvedAt = $this->time->getRequestTime();
      $artifactChecksum = hash('sha256', (string) $project->get('studio_json')->value);
      $releaseSha = hash('sha256', implode(':', [
        $project->id(),
        $prospect->id(),
        $project->get('proof_url')->value,
        $artifactChecksum,
        $approvedAt,
      ]));
      $project->set('approval_status', 'approved');
      $project->set('approved_at', $approvedAt);
      $project->set('delivery_status', 'approved');
      $project->set('release_sha', $releaseSha);
      $project->set('artifact_checksum', $artifactChecksum);
      $project->save();
      $prospect->set('status', 'approved')->save();
      $jobId = $this->ledger->enqueue(
        'deployment.prepare:' . $project->id() . ':' . $releaseSha,
        'deployment.prepare',
        [
          'project_id' => (int) $project->id(),
          'release_sha' => $releaseSha,
          'artifact_checksum' => $artifactChecksum,
        ],
        (int) $prospect->id(),
      );
      $this->ledger->recordEvent(
        'project.approved:' . $project->id() . ':' . $releaseSha,
        'project.approved',
        ['release_sha' => $releaseSha, 'deployment_job_id' => $jobId],
        (int) $prospect->id(),
        projectId: (int) $project->id(),
      );
      return new JsonResponse([
        'ok' => TRUE,
        'approval_status' => 'approved',
        'release_sha' => $releaseSha,
        'deployment_job_id' => $jobId,
      ]);
    }
    if ($action === 'request_revision') {
      $used = (int) $project->get('revision_count')->value;
      $limit = max(1, (int) $project->get('revision_limit')->value);
      if ($used >= $limit) {
        $addon = $this->ledger->activeOffer('revision_addon_75');
        return $this->error(
          'revision_addon_required',
          402,
          sprintf(
            'Your package includes %d revision round%s. An additional revision is available for $%0.2f.',
            $limit,
            $limit === 1 ? '' : 's',
            ((int) ($addon['amount_minor'] ?? 7500)) / 100,
          ),
        );
      }
      $project->set('approval_status', 'revision_requested');
      $project->set('delivery_status', 'revision');
      $project->set('revision_count', $used + 1);
      $project->set('revision_notes', $this->sanitize((string) ($data['note'] ?? ''), TRUE));
      $project->save();
      $prospect->set('status', 'revision_requested')->save();
      $this->ledger->recordEvent(
        'project.revision:' . $project->id() . ':' . ($used + 1),
        'project.revision_requested',
        ['revision_number' => $used + 1, 'revision_limit' => $limit],
        (int) $prospect->id(),
        projectId: (int) $project->id(),
      );
      return new JsonResponse([
        'ok' => TRUE,
        'approval_status' => 'revision_requested',
        'revision_count' => $used + 1,
        'revision_limit' => $limit,
      ]);
    }
    return $this->error('bad_action', 422, 'Action must be approve or request_revision.');
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
   * Requires a paid order; returns [prospect, order, errorResponseOrNull].
   */
  protected function requirePaid(Request $request): array {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return [NULL, NULL, $this->error('invalid_or_expired_token', 404)];
    }
    $order = $this->repository->getOrder($prospect);
    if (!$order || !$order->isPaid()) {
      return [$prospect, $order, $this->error('payment_required', 402, 'Payment is required before the intake.')];
    }
    return [$prospect, $order, NULL];
  }

  /**
   * Builds the prospect-safe payload (never includes internal notes).
   */
  protected function safePayload(Prospect $prospect): array {
    $business = [];
    foreach (Prospect::PUBLIC_BUSINESS_FIELDS as $field) {
      $business[$field] = $prospect->get($field)->value;
    }
    $offers = array_values(array_filter([
      $this->ledger->activeOffer('essential_199'),
      $this->ledger->activeOffer('business_499'),
    ]));
    $terms = $this->ledger->activeTerms();
    $order = $this->repository->getOrder($prospect);
    $intake = $this->repository->getIntake($prospect);
    $project = $this->repository->getProject($prospect);

    return [
      'prospect' => [
        'id' => (int) $prospect->id(),
        'status' => $prospect->get('status')->value,
        'business' => $business,
        'contact' => [
          'name' => $prospect->get('contact_name')->value,
          'method' => $prospect->get('contact_method')->value,
          'value' => $prospect->get('contact_value')->value,
        ],
        'authorized' => (bool) $prospect->get('authorized')->value,
      ],
      'offer' => $offers[0] ?? NULL,
      'offers' => $offers,
      'terms' => $terms ? [
        'id' => $terms['id'],
        'version' => $terms['version'],
        'checksum' => $terms['checksum'],
        'document_url' => $terms['document_url'],
        'body' => $terms['body'],
      ] : NULL,
      'order' => $order ? [
        'payment_status' => $order->get('payment_status')->value,
        'package' => $order->get('package')->value,
        'amount' => (int) $order->get('amount')->value,
        'currency' => $order->get('currency')->value,
        'terms_accepted_at' => $order->get('terms_accepted_at')->value ? (int) $order->get('terms_accepted_at')->value : NULL,
      ] : NULL,
      'gateway_mode' => $this->gatewayManager->active()->getMode(),
      'intake' => $intake ? ['submitted' => (bool) $intake->get('submitted_at')->value] : NULL,
      'project' => $project ? [
        'proof_url' => $project->get('proof_url')->value,
        'live_url' => $project->get('live_url')->value,
        'delivery_status' => $project->get('delivery_status')->value,
        'approval_status' => $project->get('approval_status')->value,
        'revision_notes' => $project->get('revision_notes')->value,
        'revision_count' => (int) $project->get('revision_count')->value,
        'revision_limit' => (int) $project->get('revision_limit')->value,
        'release_sha' => $project->get('release_sha')->value,
      ] : NULL,
    ];
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
   * Basic server-side sanitization of untrusted public input.
   */
  protected function sanitize(string $value, bool $multiline = FALSE): string {
    $value = trim($value);
    // Strip control chars except newlines/tabs when multiline.
    $value = $multiline
      ? preg_replace('/[^\P{C}\n\t]+/u', '', $value)
      : preg_replace('/[^\P{C}]+/u', '', $value);
    $value = strip_tags($value);
    return mb_substr($value, 0, $multiline ? 5000 : 500);
  }

  /**
   * The configured frontend base URL (env override wins).
   */
  protected function frontendBase(): string {
    $base = (string) $this->config('famtastic_pipeline.settings')->get('frontend_base_url');
    if ($env = getenv('FRONTEND_BASE_URL')) {
      $base = $env;
    }
    return rtrim($base, '/');
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
