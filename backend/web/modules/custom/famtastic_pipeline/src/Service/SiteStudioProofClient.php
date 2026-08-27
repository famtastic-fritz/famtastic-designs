<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\Prospect;
use GuzzleHttp\ClientInterface;

/**
 * Signed outbound boundary for asynchronous Site Studio proof generation.
 */
final class SiteStudioProofClient {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function isRemote(): bool {
    return $this->endpoint() !== '';
  }

  /**
   * Dispatches an exactly-three proof request and returns the remote job id.
   */
  public function dispatch(Prospect $prospect, ProofCampaign $campaign, array $context = []): string {
    $endpoint = $this->endpoint();
    $secret = getenv('SITE_STUDIO_DISPATCH_SECRET') ?: Settings::get('site_studio_dispatch_secret');
    if ($endpoint === '' || !$secret) {
      throw new \RuntimeException('Remote Site Studio requires an endpoint and dispatch secret.');
    }
    $callbackBase = rtrim((string) (getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url')), '/');
    $isPublicPreview = !empty($context['public_preview_delivery_id']);
    $prospectPayload = [
      'business_name' => $isPublicPreview ? $this->publicProofText((string) $prospect->get('business_name')->value, 255) : $prospect->get('business_name')->value,
      'category' => $isPublicPreview ? $this->publicProofText((string) $prospect->get('business_category')->value, 255) : $prospect->get('business_category')->value,
      'description' => $isPublicPreview ? $this->publicProofText((string) $prospect->get('business_description')->value) : $prospect->get('business_description')->value,
      'service_area' => $isPublicPreview ? $this->publicProofText((string) $prospect->get('service_area')->value, 255) : $prospect->get('service_area')->value,
    ];
    if (!$isPublicPreview) {
      $prospectPayload += [
        'phone' => $prospect->get('public_phone')->value,
        'email' => $prospect->get('public_email')->value,
        'address' => $prospect->get('address')->value,
        'hours' => $prospect->get('hours')->value,
      ];
    }
    $payload = [
      'schema_version' => 2,
      'routine' => (string) ($context['routine'] ?? 'website_proof.generate.v1'),
      'idempotency_key' => 'proof:' . $campaign->get('campaign_id')->value,
      'campaign_id' => $campaign->get('campaign_id')->value,
      'prospect' => $prospectPayload,
      'directions' => ProofCampaignService::CORE_DIRECTIONS,
      'required_variant_count' => 3,
      'direction_contract' => $this->directionContract($context) ?? [
        'a' => ['name' => 'Safe', 'intent' => 'polished, familiar, credible, low-risk'],
        'b' => ['name' => 'Wild', 'intent' => 'expressive, energetic, clearly differentiated'],
        'c' => ['name' => 'OMG', 'intent' => 'campaign-level concept with the strongest visual idea'],
      ],
      'callback_url' => $callbackBase . '/api/pipeline/site-studio/callback',
      'project' => [
        'project_id' => (int) ($context['project_id'] ?? 0),
        'commerce_order_id' => (int) ($context['commerce_order_id'] ?? 0),
        'website_request_public_id' => (string) ($context['website_request_public_id'] ?? ''),
      ],
      'website_discovery_v2' => (array) ($context['website_discovery_v2'] ?? []),
      'website_discovery_v3' => (array) ($context['website_discovery_v3'] ?? $context['website_discovery_v2'] ?? []),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $signature = 'sha256=' . hash_hmac('sha256', $body, (string) $secret);
    $response = $this->httpClient->request('POST', $endpoint, [
      'headers' => [
        'Content-Type' => 'application/json',
        'X-FAMtastic-Signature' => $signature,
        'Idempotency-Key' => $payload['idempotency_key'],
      ],
      'body' => $body,
      'timeout' => 30,
    ]);
    $decoded = json_decode((string) $response->getBody(), TRUE);
    $jobId = is_array($decoded) ? (string) ($decoded['job_id'] ?? '') : '';
    if ($jobId === '') {
      throw new \RuntimeException('Site Studio did not return a job_id.');
    }
    return mb_substr($jobId, 0, 255);
  }

  private function endpoint(): string {
    return rtrim((string) (getenv('SITE_STUDIO_URL') ?: $this->configFactory->get('famtastic_pipeline.settings')->get('studio_url')), '/');
  }

  /** Keeps anonymous public proof inputs free of contact identifiers. */
  private function publicProofText(string $value, int $maximum = 1200): string {
    $value = preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? '';
    $value = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '[redacted email]', $value) ?? '';
    $value = preg_replace('/(?<!\w)(?:\+?1[ .-]?)?(?:\(?\d{3}\)?[ .-]?)\d{3}[ .-]?\d{4}(?!\w)/', '[redacted phone]', $value) ?? '';
    return mb_substr($value, 0, $maximum);
  }

  /** Uses a configured/public-run direction contract only when it is complete. */
  private function directionContract(array $context): ?array {
    $contract = $context['public_preview_direction_contract'] ?? NULL;
    if (!is_array($contract) || array_keys($contract) !== ['a', 'b', 'c']) {
      return NULL;
    }
    $result = [];
    foreach ($contract as $direction => $definition) {
      if (!is_array($definition)) {
        return NULL;
      }
      $name = mb_substr(trim(strip_tags((string) ($definition['name'] ?? ''))), 0, 255);
      $intent = mb_substr(trim(strip_tags((string) ($definition['intent'] ?? ''))), 0, 1000);
      if ($name === '' || $intent === '') {
        return NULL;
      }
      $result[$direction] = ['name' => $name, 'intent' => $intent];
    }
    return $result;
  }

}
