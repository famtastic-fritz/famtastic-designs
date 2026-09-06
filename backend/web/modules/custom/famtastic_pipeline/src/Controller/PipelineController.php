<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\famtastic_pipeline\Entity\Order;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\FulfillmentService;
use Drupal\famtastic_pipeline\Service\HostingLifecycleService;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\famtastic_pipeline\Service\PaymentGatewayManager;
use Drupal\famtastic_pipeline\Service\PipelineRepository;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\famtastic_pipeline\Service\StripeGateway;
use Drupal\file\Entity\File;
use Drupal\Core\Site\Settings;
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
    protected Connection $database,
    protected HostingLifecycleService $hostingLifecycle,
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
      $container->get('database'),
      $container->get('famtastic_pipeline.hosting_lifecycle'),
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
    if (!$this->legacyCheckoutEnabled()) {
      return new JsonResponse([
        'ok' => FALSE, 'error' => 'account_checkout_required',
        'message' => 'Create or sign in to your customer account to purchase securely.',
        'checkout_url' => $this->frontendBase() . '/login?redirect=%2Fbuy',
      ], 409);
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

    $order = $this->repository->getLaunchOrder($prospect);
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
    $dealSnapshot = $this->ledger->dealSnapshotForSkus(['FAM-FOOT-199']);
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
        'deal_snapshot' => $dealSnapshot,
        'deal_checksum' => $dealSnapshot['checksum'],
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
   * POST /api/pipeline/revision-checkout — purchase one additional revision.
   */
  public function revisionCheckout(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    if (!$this->legacyCheckoutEnabled()) {
      return new JsonResponse([
        'ok' => FALSE, 'error' => 'account_checkout_required',
        'message' => 'Purchase add-ons from your customer account so they stay with your order history.',
        'checkout_url' => $this->frontendBase() . '/login?redirect=%2Fbuy',
      ], 409);
    }
    $project = $this->repository->getProject($prospect);
    if (!$project || $project->get('approval_status')->value !== 'revision_requested') {
      return $this->error('revision_addon_unavailable', 409, 'An additional revision is not currently required.');
    }
    $used = (int) $project->get('revision_count')->value;
    $limit = (int) $project->get('revision_limit')->value;
    if ($used < $limit) {
      return $this->error('included_revision_available', 409, 'Use the included revision before purchasing another.');
    }
    $data = $this->jsonBody($request);
    $terms = $this->ledger->activeTerms();
    if (
      !$terms
      || empty($data['terms_accepted'])
      || !hash_equals($terms['checksum'], (string) ($data['terms_checksum'] ?? ''))
    ) {
      return $this->error('terms_acceptance_required', 422, 'Please accept the current service terms before checkout.');
    }
    $offer = $this->ledger->activeOffer('revision_addon_75');
    if (!$offer) {
      return $this->error('revision_addon_unavailable', 503);
    }
    $pending = $this->entityTypeManagerService->getStorage('famtastic_order')->getQuery()
      ->accessCheck(FALSE)
      ->condition('prospect_ref', $prospect->id())
      ->condition('package', 'revision_addon_75')
      ->condition('payment_status', 'pending')
      ->range(0, 1)
      ->execute();
    if ($pending) {
      return $this->error('revision_addon_checkout_pending', 409, 'A revision add-on checkout is already pending.');
    }
    $contact = (string) ($prospect->get('contact_value')->value ?: $prospect->get('public_email')->value);
    $acceptanceId = $this->ledger->recordConsent(
      $contact,
      'accepted',
      (int) $prospect->id(),
      'revision_addon_terms',
      $terms['id'],
      [
        'terms_checksum' => $terms['checksum'],
        'offer_key' => $offer['offer_key'],
        'offer_version_id' => $offer['id'],
        'project_id' => (int) $project->id(),
        'revision_count' => $used,
        'revision_limit' => $limit,
      ],
    );
    $order = Order::create([
      'prospect_ref' => $prospect->id(),
      'package' => $offer['offer_key'],
      'offer_version_id' => $offer['id'],
      'amount' => $offer['amount_minor'],
      'currency' => $offer['currency'],
      'payment_status' => 'pending',
      'terms_version_id' => $terms['id'],
      'terms_acceptance_id' => $acceptanceId,
      'terms_accepted_at' => $this->time->getRequestTime(),
    ]);
    $order->save();
    $token = $this->readToken($request);
    $frontend = $this->frontendBase();
    try {
      $gateway = $this->gatewayManager->active();
      $session = $gateway->createCheckoutSession($order, [
        'success_url' => $frontend . '/p/' . $token . '/proof?revision_addon=success',
        'cancel_url' => $frontend . '/p/' . $token . '/proof?revision_addon=cancel',
        'customer_email' => $contact,
        'product_name' => $offer['name'],
      ]);
    }
    catch (\Throwable $e) {
      $this->getLogger('famtastic_pipeline')->error('Revision add-on checkout failed: @m', ['@m' => $e->getMessage()]);
      return $this->error('checkout_failed', 502, 'Could not start revision checkout.');
    }
    $order->set('stripe_checkout_session_id', $session['id']);
    if (!empty($session['payment_intent'])) {
      $order->set('stripe_payment_intent_id', $session['payment_intent']);
    }
    $order->save();
    $this->ledger->recordEvent(
      'revision_addon.checkout:' . $order->id(),
      'revision_addon.checkout_started',
      ['order_id' => (int) $order->id(), 'project_id' => (int) $project->id(), 'amount_minor' => (int) $offer['amount_minor']],
      (int) $prospect->id(),
      orderId: (int) $order->id(),
      projectId: (int) $project->id(),
    );
    return new JsonResponse([
      'ok' => TRUE,
      'url' => $session['url'],
      'session_id' => $session['id'],
      'amount' => (int) $offer['amount_minor'],
      'currency' => $offer['currency'],
      'gateway_mode' => $gateway->getMode(),
    ]);
  }

  /** Legacy checkout is allowed only when explicitly configured or local. */
  private function legacyCheckoutEnabled(): bool {
    if ((bool) \Drupal::config('famtastic_pipeline.settings')->get('legacy_checkout_enabled')) return TRUE;
    $host = (string) parse_url($this->frontendBase(), PHP_URL_HOST);
    return in_array($host, ['localhost', '127.0.0.1'], TRUE);
  }

  /** Converts a catalog dollar price to minor units without float rounding. */
  private function catalogPriceMinor(string $price): int {
    if (!preg_match('/^(?<dollars>0|[1-9][0-9]*)(?:\\.(?<cents>[0-9]{1,2}))?$/', $price, $matches)) {
      throw new \RuntimeException('invalid_catalog_price');
    }
    return ((int) $matches['dollars'] * 100) + (int) str_pad((string) ($matches['cents'] ?? ''), 2, '0');
  }

  /**
   * POST /api/pipeline/hosting-renewal — separately authorize month-13 billing.
   */
  public function hostingRenewal(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $project = $this->repository->getProject($prospect);
    if (!$project) {
      return $this->error('hosting_unavailable', 409);
    }
    $entitlement = $this->database->select('famtastic_hosting_entitlement', 'h')
      ->fields('h')
      ->condition('project_id', (int) $project->id())
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$entitlement) {
      return $this->error('hosting_unavailable', 409, 'Hosting has not started.');
    }
    $amountMinor = (int) (getenv('FAMTASTIC_HOSTING_MONTHLY_AMOUNT') ?: Settings::get('famtastic_hosting_monthly_amount', 0));
    if ($amountMinor <= 0) {
      return $this->error('renewal_pricing_unavailable', 503, 'Monthly hosting renewal is not available yet.');
    }
    // The later recurring agreement must inherit the launch tier's exact
    // catalog promise. Reject configuration that would authorize a price not
    // backed by that immutable offer contract.
    $order = $project->get('order_ref')->entity;
    $renewalSku = match ((string) ($order?->get('package')->value ?? '')) {
      'essential_199', 'basic_199' => 'FAM-HOST-999',
      'business_499' => 'FAM-HOST-BUSINESS-1999',
      default => '',
    };
    if ($renewalSku === '') {
      return $this->error('renewal_contract_unavailable', 503, 'The hosting renewal contract is unavailable.');
    }
    try {
      $renewalDeal = $this->ledger->dealSnapshotForSkus([$renewalSku]);
      $contractAmountMinor = $this->catalogPriceMinor((string) ($renewalDeal['items'][0]['product']['price'] ?? ''));
    }
    catch (\Throwable $e) {
      $this->getLogger('famtastic_pipeline')->error('Hosting renewal contract unavailable: @m', ['@m' => $e->getMessage()]);
      return $this->error('renewal_contract_unavailable', 503, 'The hosting renewal contract is unavailable.');
    }
    if ($contractAmountMinor !== $amountMinor) {
      return $this->error('renewal_pricing_unavailable', 503, 'Monthly hosting renewal is not available yet.');
    }
    $data = $this->jsonBody($request);
    if (empty($data['recurring_authorized']) || (int) ($data['amount_minor'] ?? 0) !== $amountMinor) {
      return $this->error('recurring_authorization_required', 422, 'Confirm the disclosed monthly amount and recurring billing authorization.');
    }
    $contact = (string) ($prospect->get('contact_value')->value ?: $prospect->get('public_email')->value);
    try {
      $subscription = $this->hostingLifecycle->authorizeRecurring(
        (int) $entitlement['id'],
        $contact,
        $amountMinor,
        [
          'method' => 'customer_portal',
          'accepted_at' => gmdate(DATE_ATOM, $this->time->getRequestTime()),
          'deal_snapshot' => $renewalDeal,
          'deal_checksum' => $renewalDeal['checksum'],
          'ip_hash' => hash('sha256', (string) ($request->getClientIp() ?: 'unknown')),
          'user_agent_hash' => hash('sha256', (string) $request->headers->get('User-Agent', '')),
        ],
      );
    }
    catch (\Throwable $e) {
      $this->getLogger('famtastic_pipeline')->error('Hosting renewal authorization failed: @m', ['@m' => $e->getMessage()]);
      return $this->error('renewal_authorization_failed', 503);
    }
    return new JsonResponse([
      'ok' => TRUE,
      'status' => $subscription['status'],
      'amount_minor' => (int) $subscription['amount_minor'],
      'currency' => $subscription['currency'],
      'starts_at' => (int) $entitlement['renews_at'],
    ]);
  }

  /** POST /api/pipeline/hosting-renewal/cancel — customer cancellation control. */
  public function cancelHostingRenewal(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) return $this->error('invalid_or_expired_token', 404);
    $project = $this->repository->getProject($prospect);
    if (!$project) return $this->error('hosting_unavailable', 409);
    $entitlementId = $this->database->select('famtastic_hosting_entitlement', 'h')->fields('h', ['id'])
      ->condition('project_id', (int) $project->id())->execute()->fetchField();
    if (!$entitlementId) return $this->error('hosting_unavailable', 409);
    $contact = (string) ($prospect->get('contact_value')->value ?: $prospect->get('public_email')->value);
    try {
      $subscription = $this->hostingLifecycle->cancelRecurring((int) $entitlementId, $contact);
    }
    catch (\InvalidArgumentException $e) { return $this->error('subscription_not_found', 404, $e->getMessage()); }
    catch (\Throwable $e) { return $this->error('cancellation_unavailable', 503, $e->getMessage()); }
    return new JsonResponse(['ok' => TRUE, 'status' => $subscription['status'], 'effective' => 'end_of_paid_period']);
  }

  /**
   * GET /api/pipeline/order-status — server-verified payment status.
   */
  public function orderStatus(Request $request): JsonResponse {
    $prospect = $this->resolveProspect($request);
    if (!$prospect) {
      return $this->error('invalid_or_expired_token', 404);
    }
    $order = $this->repository->getLaunchOrder($prospect);
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
          $order = $this->repository->getLaunchOrder($prospect);
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
    $allowedTypes = [
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'webp' => 'image/webp',
    ];
    $ext = strtolower($uploaded->getClientOriginalExtension());
    $realPath = $uploaded->getRealPath();
    $detectedType = $realPath ? (new \finfo(FILEINFO_MIME_TYPE))->file($realPath) : FALSE;
    if (!isset($allowedTypes[$ext]) || $detectedType !== $allowedTypes[$ext]) {
      return $this->error('bad_file_type', 422, 'Only verified PNG, JPEG, and WebP images are accepted.');
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
    $order = $this->repository->getLaunchOrder($prospect);
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
    $order = $this->repository->getLaunchOrder($prospect);
    $intake = $this->repository->getIntake($prospect);
    $project = $this->repository->getProject($prospect);
    $proof = $this->proofCampaigns->getForProspect($prospect);
    $deployment = $project ? $this->database->select('famtastic_deployment', 'd')
      ->fields('d', ['id', 'status', 'public_url', 'deployed_at', 'rolled_back_at'])
      ->condition('project_id', (int) $project->id())
      ->orderBy('created', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() : FALSE;
    $domain = $project ? $this->database->select('famtastic_domain', 'd')
      ->fields('d', ['id', 'domain_name', 'owner_type', 'management_mode', 'dns_status', 'ssl_status', 'last_verified_at'])
      ->condition('project_id', (int) $project->id())
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() : FALSE;
    $hosting = $project ? $this->database->select('famtastic_hosting_entitlement', 'h')
      ->fields('h', ['id', 'status', 'starts_at', 'included_until', 'renews_at', 'suspended_at'])
      ->condition('project_id', (int) $project->id())
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() : FALSE;
    $subscription = $hosting ? $this->database->select('famtastic_subscription', 's')
      ->fields('s', ['status', 'amount_minor', 'currency', 'retry_count', 'next_attempt_at'])
      ->condition('entitlement_id', (int) $hosting['id'])
      ->range(0, 1)
      ->execute()
      ->fetchAssoc() : FALSE;
    $hostingMonthlyAmount = (int) (getenv('FAMTASTIC_HOSTING_MONTHLY_AMOUNT') ?: Settings::get('famtastic_hosting_monthly_amount', 0));
    $addOnOrders = $this->database->select('famtastic_order', 'o')
      ->fields('o', ['package', 'amount', 'currency', 'payment_status', 'paid_at'])
      ->condition('prospect_ref', (int) $prospect->id())
      ->condition('package', ['essential_199', 'business_499', 'basic_199'], 'NOT IN')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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
        'deal' => $this->ledger->dealSnapshotForSkus(['FAM-FOOT-199']),
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
      'proof' => $proof ? [
        'campaign_id' => $proof['campaign']->get('campaign_id')->value,
        'generation_status' => $proof['campaign']->get('generation_status')->value,
        'selected_direction' => $proof['campaign']->get('selected_variant')->value,
        'selected_package' => $proof['campaign']->get('selected_package')->value,
        'variant_count' => count($proof['variants']),
      ] : NULL,
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
      'deployment' => $deployment ?: NULL,
      'domain' => $domain ?: NULL,
      'hosting' => $hosting ?: NULL,
      'subscription' => $subscription ?: NULL,
      'add_ons' => array_map(static fn (array $addOn): array => [
        'package' => $addOn['package'],
        'amount' => (int) $addOn['amount'],
        'currency' => $addOn['currency'],
        'payment_status' => $addOn['payment_status'],
        'paid_at' => $addOn['paid_at'] ? (int) $addOn['paid_at'] : NULL,
      ], $addOnOrders),
      'hosting_renewal_offer' => $hosting && !$subscription && $hostingMonthlyAmount > 0 ? [
        'amount_minor' => $hostingMonthlyAmount,
        'currency' => 'usd',
        'interval' => 'month',
        'starts_at' => (int) $hosting['renews_at'],
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
