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
  public function dispatch(Prospect $prospect, ProofCampaign $campaign): string {
    $endpoint = $this->endpoint();
    $secret = getenv('SITE_STUDIO_DISPATCH_SECRET') ?: Settings::get('site_studio_dispatch_secret');
    if ($endpoint === '' || !$secret) {
      throw new \RuntimeException('Remote Site Studio requires an endpoint and dispatch secret.');
    }
    $callbackBase = rtrim((string) (getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url')), '/');
    $payload = [
      'schema_version' => 1,
      'idempotency_key' => 'proof:' . $campaign->get('campaign_id')->value,
      'campaign_id' => $campaign->get('campaign_id')->value,
      'prospect' => [
        'business_name' => $prospect->get('business_name')->value,
        'category' => $prospect->get('business_category')->value,
        'description' => $prospect->get('business_description')->value,
        'service_area' => $prospect->get('service_area')->value,
        'phone' => $prospect->get('public_phone')->value,
      ],
      'directions' => ProofCampaignService::DIRECTIONS,
      'required_variant_count' => 3,
      'callback_url' => $callbackBase . '/api/pipeline/site-studio/callback',
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

}
