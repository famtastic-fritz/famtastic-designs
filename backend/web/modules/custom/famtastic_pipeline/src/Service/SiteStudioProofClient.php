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
   * Dispatches a source-bound proof request and returns the remote job id.
   *
   * The runner owns provider selection. This boundary carries the requested
   * direction contract verbatim so Site Studio can build a public three-pack,
   * a portal refinement pack, or a later revision without inferring intent
   * from the campaign's previous job.
   */
  public function dispatch(Prospect $prospect, ProofCampaign $campaign, array $context = []): string {
    $endpoint = $this->endpoint();
    $secret = getenv('SITE_STUDIO_DISPATCH_SECRET') ?: Settings::get('site_studio_dispatch_secret');
    if ($endpoint === '' || !$secret) {
      throw new \RuntimeException('Remote Site Studio requires an endpoint and dispatch secret.');
    }
    $callbackBase = rtrim((string) (getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: $this->configFactory->get('famtastic_pipeline.settings')->get('frontend_base_url')), '/');
    $proofRunner = (array) ($context['proof_runner'] ?? []);
    $runnerContract = (array) ($proofRunner['contract'] ?? []);
    $runnerBound = $proofRunner !== [];
    $runnerProfile = (string) ($runnerContract['profile']['id'] ?? '');
    $refinedSix = $runnerBound && $runnerProfile === 'portal_refined_six.v1';
    $directions = (array) ($context['directions'] ?? ProofCampaignService::CORE_DIRECTIONS);
    $directionContract = (array) ($context['direction_contract'] ?? ProofCampaignService::CORE_DIRECTION_CONTRACT);
    $directionIds = array_keys($directions);
    sort($directionIds);
    $contractIds = array_keys($directionContract);
    sort($contractIds);
    $allowedDirectionSets = [
      array_keys(ProofCampaignService::CORE_DIRECTIONS),
      array_keys(ProofCampaignService::SHOWCASE_DIRECTIONS),
    ];
    $refinedIds = ['a', 'b', 'c', 'd', 'e', 'f'];
    $validThreePack = count($directions) === 3 && in_array($directionIds, $allowedDirectionSets, TRUE);
    $validRefinedSix = $refinedSix && count($directions) === 6 && $directionIds === $refinedIds;
    if ($directionIds !== $contractIds || (!$validThreePack && !$validRefinedSix)) {
      throw new \InvalidArgumentException('A Site Studio proof dispatch requires the exact runner-bound a/b/c, d/e/f, or refined a-f direction contract.');
    }
    $resolvedDirections = [];
    foreach ($directionContract as $directionId => $contract) {
      if (!is_array($contract)) {
        throw new \InvalidArgumentException('Each Site Studio direction contract must be an object.');
      }
      $name = mb_substr(trim(strip_tags((string) ($contract['name'] ?? ''))), 0, 255);
      $intent = mb_substr(trim(strip_tags((string) ($contract['intent'] ?? ''))), 0, 1000);
      if ($name === '' || $intent === '') {
        throw new \InvalidArgumentException('Each Site Studio direction contract requires a name and intent.');
      }
      // The contract, rather than an unrelated global fallback label, is the
      // only source for the direction names that leave this dispatch boundary.
      $directionContract[$directionId]['name'] = $name;
      $directionContract[$directionId]['intent'] = $intent;
      $resolvedDirections[$directionId] = $name;
    }
    ksort($directionContract);
    ksort($resolvedDirections);
    $directions = $resolvedDirections;
    if ($runnerBound) {
      $this->assertRemoteSafeRunnerEnvelope($proofRunner, $runnerContract);
      $source = (array) ($runnerContract['source'] ?? []);
      // The full normalized source contract is sent inline and signed with
      // this request. Do not also leak the Drupal prospect's contact fields or
      // the original unfiltered portal payload to a remote proof worker.
      $prospectPayload = [
        'business_name' => (string) ($source['business_name'] ?? ''),
        'category' => (string) ($source['business_category'] ?? ''),
        'description' => (string) ($source['business_description'] ?? ''),
        'service_area' => (string) ($source['service_area'] ?? ''),
      ];
      $websiteDiscovery = (array) ($source['facts'] ?? []);
    }
    else {
      $prospectPayload = [
        'business_name' => $prospect->get('business_name')->value,
        'category' => $prospect->get('business_category')->value,
        'description' => $prospect->get('business_description')->value,
        'service_area' => $prospect->get('service_area')->value,
        'phone' => $prospect->get('public_phone')->value,
        'email' => $prospect->get('public_email')->value,
        'address' => $prospect->get('address')->value,
        'hours' => $prospect->get('hours')->value,
      ];
      $websiteDiscovery = (array) ($context['website_discovery_v3'] ?? $context['website_discovery_v2'] ?? []);
    }
    $payload = [
      'schema_version' => 2,
      'routine' => (string) ($context['routine'] ?? 'website_proof.generate.v1'),
      'idempotency_key' => (string) ($context['idempotency_key'] ?? ('proof:' . $campaign->get('campaign_id')->value)),
      'campaign_id' => $campaign->get('campaign_id')->value,
      'prospect' => $prospectPayload,
      'directions' => $directions,
      'required_variant_count' => count($directions),
      'direction_contract' => $directionContract,
      'callback_url' => $callbackBase . '/api/pipeline/site-studio/callback',
      'project' => [
        'project_id' => (int) ($context['project_id'] ?? 0),
        'commerce_order_id' => (int) ($context['commerce_order_id'] ?? 0),
        'website_request_public_id' => (string) ($context['website_request_public_id'] ?? ''),
      ],
      'website_discovery_v2' => $websiteDiscovery,
      'website_discovery_v3' => $websiteDiscovery,
      // Additive FAMtastic-side correlation only. Site Studio need not change
      // its recipe engine to preserve and return this opaque build lineage.
      'proof_runner' => $proofRunner,
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

  /**
   * Rejects a malformed canonical envelope before the signed remote dispatch.
   *
   * This boundary intentionally accepts the inline contract, not a Drupal
   * private:// pointer. A remote provider must never receive personal contact
   * fields merely because legacy dispatches included them.
   */
  private function assertRemoteSafeRunnerEnvelope(array $envelope, array $contract): void {
    if (trim((string) ($envelope['build_id'] ?? '')) === '' || trim((string) ($envelope['contract_sha256'] ?? '')) === '') {
      throw new \InvalidArgumentException('Runner-bound Site Studio dispatch requires build_id and contract checksum.');
    }
    if (($contract['schema'] ?? '') !== ProofRunnerContractService::CONTRACT_SCHEMA || !ProofRunnerContractService::isSupportedRoutine((string) ($contract['routine'] ?? ''))) {
      throw new \InvalidArgumentException('Runner-bound Site Studio dispatch requires the inline canonical proof contract.');
    }
    $serialized = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (str_contains($serialized, 'private://')) {
      throw new \InvalidArgumentException('Runner-bound Site Studio dispatch may not contain a Drupal private:// URI.');
    }
    $forbidden = ['email', 'public_email', 'phone', 'public_phone', 'password', 'token', 'access_token', 'refresh_token', 'api_key', 'secret'];
    $walk = function (mixed $value, string $key = '') use (&$walk, $forbidden): void {
      if (in_array(mb_strtolower($key), $forbidden, TRUE)) {
        throw new \InvalidArgumentException('Runner-bound Site Studio dispatch may not contain raw contact or credential fields.');
      }
      if (is_array($value)) {
        foreach ($value as $childKey => $childValue) {
          $walk($childValue, (string) $childKey);
        }
      }
    };
    $walk($contract);
  }

}
