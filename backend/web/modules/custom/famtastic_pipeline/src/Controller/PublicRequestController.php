<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\AttributionService;
use Drupal\famtastic_pipeline\Service\ConciergeWebhookService;
use Drupal\famtastic_pipeline\Service\OutreachMailer;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;
use Drupal\famtastic_pipeline\Service\TokenManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Public, no-token request capture for contact and quote forms.
 */
class PublicRequestController extends ControllerBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected TokenManager $tokenManager,
    protected OutreachMailer $mailer,
    protected ConciergeWebhookService $concierge,
    protected PublicPreviewDeliveryService $previews,
    protected ProofCampaignService $proofCampaigns,
    protected ConfigFactoryInterface $pipelineConfigFactory,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
    protected FloodInterface $flood,
    protected AttributionService $attribution,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('famtastic_pipeline.token_manager'),
      $container->get('famtastic_pipeline.mailer'),
      $container->get('famtastic_pipeline.concierge_webhooks'),
      $container->get('famtastic_pipeline.public_preview_deliveries'),
      $container->get('famtastic_pipeline.proof_campaign_service'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('logger.channel.famtastic_pipeline'),
      $container->get('flood'),
      $container->get('famtastic_pipeline.attribution'),
    );
  }

  /**
   * POST /api/public/quote.
   */
  public function quote(Request $request): JsonResponse {
    return $this->capture($request, 'quote');
  }

  /**
   * POST /api/public/contact.
   */
  public function contact(Request $request): JsonResponse {
    return $this->capture($request, 'contact');
  }

  /**
   * Captures a public request without requiring a prospect token.
   */
  protected function capture(Request $request, string $type): JsonResponse {
    if (strlen($request->getContent()) > 65536) {
      return $this->error('request_too_large', 413, 'The request is too large.');
    }
    $data = $this->jsonBody($request);
    $answers = is_array($data['answers'] ?? NULL) ? $data['answers'] : [];
    $email = $this->sanitize((string) ($answers['email'] ?? $data['email'] ?? ''));
    $phone = $this->sanitize((string) ($answers['phone'] ?? $data['phone'] ?? ''));
    $businessName = $this->sanitize((string) ($answers['businessName'] ?? $answers['business_name'] ?? $data['business_name'] ?? $data['businessName'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $this->error('email_required', 422, 'A valid email is required.');
    }

    $ipIdentifier = $type . ':ip:' . ($request->getClientIp() ?: 'unknown');
    $emailIdentifier = $type . ':email:' . hash('sha256', strtolower($email));
    if (
      !$this->flood->isAllowed('famtastic_public_request', 10, 3600, $ipIdentifier)
      || !$this->flood->isAllowed('famtastic_public_request', 3, 3600, $emailIdentifier)
    ) {
      return $this->error('rate_limited', 429, 'Too many requests. Please try again later.');
    }
    $this->flood->register('famtastic_public_request', 3600, $ipIdentifier);
    $this->flood->register('famtastic_public_request', 3600, $emailIdentifier);

    if ($businessName === '') {
      $businessName = $this->sanitize((string) ($answers['businessDescription'] ?? $data['name'] ?? 'Public request'));
    }

    $prospect = $this->loadDuplicateProspect($email, $type);
    $duplicate = (bool) $prospect;
    if (!$prospect) {
      $token = $this->tokenManager->generate();
      $slaDays = max(1, (int) ($this->pipelineConfigFactory->get('famtastic_pipeline.settings')->get('lead_response_sla_days') ?: 3));
      $attribution = $this->attribution->snapshotFromRequest($request, 'public_' . $type);
      $prospect = Prospect::create([
        'business_name' => $businessName,
        'business_category' => $this->sanitize((string) ($answers['industry'] ?? $data['branch'] ?? '')),
        'business_description' => $this->sanitize($this->describeBusiness($data, $answers), TRUE),
        'service_area' => $this->sanitize((string) ($answers['location'] ?? '')),
        'public_phone' => $phone,
        'public_email' => $email,
        'campaign' => 'public_' . $type,
        'source' => $this->sanitize((string) ($data['source'] ?? 'public-form')),
        'discovery_notes' => $this->sanitize($this->requestSummary($data, $type), TRUE),
        'utm_json' => $this->attribution->toJson($attribution),
        'token_hash' => $token['hash'],
        'token_expires' => $token['expires'],
        'token_revoked' => FALSE,
        'contact_method' => 'email',
        'contact_value' => $email,
        'status' => 'lead',
        'owner_uid' => 1,
        'first_response_due' => $this->time->getRequestTime() + ($slaDays * 86400),
        'next_followup_due' => $this->time->getRequestTime() + ($slaDays * 86400),
      ]);
      $prospect->save();
      // A new lead carrying utm_content counts once for that social record.
      if (!empty($attribution['utm_content'])) {
        $this->attribution->recordSocialLead((string) $attribution['utm_content']);
      }
    }

    $intake = $this->saveRequest($prospect, $data, $answers, $type);
    $previewDelivery = $this->previews->createForPublicLead((int) $prospect->id(), (int) $intake->id());
    // Public intake uses the same delivery-scoped campaign binding as a
    // verified cold lead. The dedicated job must never create a generic
    // campaign after claim, otherwise its callback cannot be correlated to
    // this public delivery.
    $this->proofCampaigns->createQueuedPublicPreviewCampaign(
      $prospect,
      $this->previews->publicIntakeProofContext((int) $previewDelivery['id']),
    );
    $this->previews->queueInitialProof((int) $previewDelivery['id']);
    $registrationUrl = $this->registrationUrl((string) $previewDelivery['public_id']);
    try {
      $this->concierge->recordPublicLead((int) $prospect->id(), (int) $intake->id(), 'public_' . $type);
    }
    catch (\Throwable $error) {
      // The customer lead must remain durable even if the operator timeline is unavailable.
      $this->logger->error('Concierge lead timeline failed for request @id: @message', [
        '@id' => $intake->id(),
        '@message' => $error->getMessage(),
      ]);
    }
    $notification = $this->notify($prospect, $intake, $data, $type, $duplicate, $registrationUrl);

    $payload = [
      'ok' => TRUE,
      'status' => $notification['ok'] ? 'received' : 'partial_success',
      'request_id' => (int) $intake->id(),
      'prospect_id' => (int) $prospect->id(),
      'duplicate' => $duplicate,
      'notification_sent' => $notification['ok'],
      'preview_level' => $type === 'quote' ? 'basic' : NULL,
      'next_step' => $type === 'quote' ? 'register_for_detailed_demo' : 'staff_follow_up',
      'registration_url' => $type === 'quote' ? $registrationUrl : NULL,
      'message' => $notification['ok']
        ? ($type === 'quote'
          ? 'Your starter recommendation is ready and your request is saved. Create a free account for a more detailed brief and working design demos.'
          : 'We received your request and our team has been notified.')
        : 'We received your request, but our notification system encountered an issue. Our team will still follow up.',
    ];

    return new JsonResponse($payload, $notification['ok'] ? 200 : 202);
  }

  /**
   * Finds a recent public request for duplicate-submit protection.
   */
  protected function loadDuplicateProspect(string $email, string $type): ?Prospect {
    $cutoff = $this->time->getRequestTime() - 86400;
    $storage = $this->entityTypeManagerService->getStorage('famtastic_prospect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('public_email', $email)
      ->condition('campaign', 'public_' . $type)
      ->condition('created', $cutoff, '>=')
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  /**
   * Saves request details in the existing intake/request entity.
   */
  protected function saveRequest(Prospect $prospect, array $data, array $answers, string $type) {
    $storage = $this->entityTypeManagerService->getStorage('famtastic_intake');
    $intake = $storage->create([
      'prospect_ref' => $prospect->id(),
      'primary_goal' => $this->sanitize($this->primaryGoal($data, $type)),
      'primary_cta' => $type === 'quote' ? 'Request a quote' : 'Contact me',
      'services' => $this->sanitize((string) ($data['branch'] ?? ''), TRUE),
      'about' => $this->sanitize($this->requestSummary($data, $type), TRUE),
      'brand_colors' => '',
      'style_preferences' => $this->sanitize((string) ($answers['referenceSites'] ?? ''), TRUE),
      'reference_sites' => $this->sanitize((string) ($answers['referenceSites'] ?? ''), TRUE),
      'existing_domain' => $this->sanitize((string) ($answers['domainDetails'] ?? '')),
      'submitted_at' => $this->time->getRequestTime(),
    ]);
    if (!empty($answers['businessDescription'])) {
      $intake->set('ideal_customer', $this->sanitize((string) $answers['businessDescription'], TRUE));
    }
    if (!empty($data['estimate']) && is_array($data['estimate'])) {
      $intake->set('customer_problem', $this->sanitize('Estimated range: ' . json_encode($data['estimate']), TRUE));
    }
    $intake->save();
    return $intake;
  }

  /**
   * Attempts the notification boundary and reports partial success on failure.
   */
  protected function notify(Prospect $prospect, $intake, array $data, string $type, bool $duplicate, string $registrationUrl): array {
    $settings = $this->pipelineConfigFactory->get('famtastic_pipeline.settings');
    $to = (string) ($settings->get('notification_to_email') ?: 'hello@famtasticdesigns.com');
    $subject = sprintf('FAMtastic Designs %s request #%d%s', $type, (int) $intake->id(), $duplicate ? ' (duplicate)' : '');
    $body = $this->ownerNotificationBody($prospect, $intake, $data, $type, $duplicate);

    try {
      $this->mailer->send($to, $subject, $body);
      $customerEmail = (string) $prospect->get('public_email')->value;
      $customerSubject = $type === 'quote'
        ? 'We received your FAMtastic Designs quote request'
        : 'We received your message — FAMtastic Designs';
      $slaDays = max(1, (int) ($settings->get('lead_response_sla_days') ?: 3));
      $customerBody = "Thanks for reaching out to FAMtastic Designs.\n\n"
        . "Your request #" . $intake->id() . " is safely recorded.\n\n"
        . ($type === 'quote'
          ? "The public Solution Finder gives you a useful starter recommendation from the basic information you shared. For a more detailed experience—and working website demos instead of a basic mockup—create your free customer account with this same email address:\n{$registrationUrl}\n\nYour saved lead and intake will follow you into the portal, where you can add brand, domain, content, feature, reference-site, and business details without starting over.\n\n"
          : '')
        . "We will review your request and reply within {$slaDays} business days. You can reply directly to this email if you need to add context.\n\n"
        . "Shay Shay\nFAMtastic Concierge\nFAMtastic Designs · https://famtasticdesigns.com";
      $this->mailer->send($customerEmail, $customerSubject, $customerBody);
      $prospect->set('status', 'acknowledged')->save();
      return ['ok' => TRUE];
    }
    catch (\Throwable $e) {
      $this->logger->error('Public request notification failed for request @id: @m', [
        '@id' => $intake->id(),
        '@m' => $e->getMessage(),
      ]);
      return ['ok' => FALSE, 'error' => $e->getMessage()];
    }
  }

  /**
   * Builds the public-to-member continuation link without exposing a secret.
   */
  protected function registrationUrl(string $previewPublicId): string {
    $base = rtrim((string) (getenv('FRONTEND_BASE_URL') ?: $this->pipelineConfigFactory->get('famtastic_pipeline.settings')->get('frontend_base_url')), '/');
    // No email, business name, intake id, or public-lead identifier is exposed
    // in a browser URL. The opaque continuation is verified again server-side.
    $signature = hash_hmac('sha256', 'public-preview-continuation-v1|' . $previewPublicId, \Drupal\Core\Site\Settings::getHashSalt());
    return $base . '/login?mode=register&continuation=' . rawurlencode($previewPublicId . '.' . $signature) . '&redirect=%2Fportal%3Fstart%3Dwebsite';
  }

  protected function primaryGoal(array $data, string $type): string {
    if ($type === 'quote') {
      return 'Public quote request from Solution Finder';
    }
    return 'Public contact request';
  }

  protected function describeBusiness(array $data, array $answers): string {
    return (string) ($answers['businessDescription'] ?? $answers['description'] ?? $data['branch'] ?? '');
  }

  protected function requestSummary(array $data, string $type): string {
    $safe = $data;
    unset($safe['token'], $safe['access_token'], $safe['refresh_token']);
    return strtoupper($type) . " request\n" . json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Gives the owner the decision-ready request facts without emailing raw JSON.
   *
   * The complete, sanitized intake remains on the Prospect and Intake records.
   * This notification intentionally contains a compact triage view only.
   */
  protected function ownerNotificationBody(Prospect $prospect, $intake, array $data, string $type, bool $duplicate): string {
    $answers = is_array($data['answers'] ?? NULL) ? $data['answers'] : [];
    $value = static function (mixed $candidate): string {
      if (is_array($candidate)) {
        $candidate = implode(', ', array_filter(array_map(static fn ($item): string => is_scalar($item) ? trim((string) $item) : '', $candidate)));
      }
      return trim((string) $candidate);
    };
    $facts = [
      'Business' => $prospect->label(),
      'Contact email' => (string) $prospect->get('public_email')->value,
      'Phone' => (string) $prospect->get('public_phone')->value,
      'Industry' => (string) $prospect->get('business_category')->value,
      'Location' => $value($answers['location'] ?? ''),
      'What they need' => $value($answers['businessDescription'] ?? $answers['description'] ?? $data['branch'] ?? ''),
      'Services' => $value($answers['services'] ?? $data['services'] ?? ''),
      'Timeline' => $value($answers['timeline'] ?? ''),
      'Budget / recommendation' => $value($answers['budget'] ?? $data['estimate'] ?? ''),
      'Reference sites' => $value($answers['referenceSites'] ?? ''),
    ];
    $lines = [
      sprintf('A new public %s request is ready for review%s.', $type, $duplicate ? ' (duplicate submission)' : ''),
      '',
      'Quick intake',
    ];
    foreach ($facts as $label => $fact) {
      if ($fact !== '') {
        $lines[] = $label . ': ' . $fact;
      }
    }
    $base = rtrim((string) (getenv('FRONTEND_BASE_URL') ?: $this->pipelineConfigFactory->get('famtastic_pipeline.settings')->get('frontend_base_url')), '/');
    $operationsUrl = $base !== '' ? $base . '/web/admin/famtastic/metric/website-requests' : '';
    $lines[] = '';
    $lines[] = 'Request ID: ' . $intake->id() . ' · Prospect ID: ' . $prospect->id();
    if ($operationsUrl !== '') {
      $lines[] = 'Open Website Requests: ' . $operationsUrl;
    }
    $lines[] = 'Your complete submitted intake is saved to your secure request record; raw data dumps are never emailed.';
    return implode("\n", $lines);
  }

  protected function jsonBody(Request $request): array {
    $decoded = json_decode($request->getContent() ?: '[]', TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  protected function sanitize(string $value, bool $multiline = FALSE): string {
    $value = trim($value);
    $value = $multiline
      ? preg_replace('/[^\P{C}\n\t]+/u', '', $value)
      : preg_replace('/[^\P{C}]+/u', '', $value);
    $value = strip_tags((string) $value);
    return mb_substr($value, 0, $multiline ? 5000 : 500);
  }

  protected function error(string $code, int $status, string $message): JsonResponse {
    return new JsonResponse(['ok' => FALSE, 'error' => $code, 'message' => $message], $status);
  }

}
