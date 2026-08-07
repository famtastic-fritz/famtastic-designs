<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Reads a small, cached GA4 report for the operator dashboard.
 */
final class GoogleAnalyticsReportingService {

  private const CACHE_ID = 'famtastic_pipeline:google_analytics:dashboard';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns dashboard metrics without leaking credential or API details.
   */
  public function dashboardReport(): array {
    if ($cached = $this->cache->get(self::CACHE_ID)) {
      return $cached->data;
    }

    $propertyId = (string) Settings::get('famtastic_google_analytics_property_id', '');
    $credentialsPath = (string) Settings::get('famtastic_google_analytics_credentials_path', '');
    if ($propertyId === '' || $credentialsPath === '' || !is_readable($credentialsPath)) {
      return ['available' => FALSE, 'message' => 'Google Analytics reporting is not configured.'];
    }

    try {
      $credentials = json_decode((string) file_get_contents($credentialsPath), TRUE, 512, JSON_THROW_ON_ERROR);
      $token = $this->accessToken($credentials);
      $overview = $this->runReport($propertyId, $token, [
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'metrics' => array_map(static fn(string $name): array => ['name' => $name], [
          'activeUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'keyEvents',
        ]),
      ]);
      $pages = $this->runReport($propertyId, $token, [
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'pagePath']],
        'metrics' => [['name' => 'screenPageViews']],
        'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => TRUE]],
        'limit' => 8,
      ]);
      $sources = $this->runReport($propertyId, $token, [
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => TRUE]],
        'limit' => 8,
      ]);

      $values = array_map(static fn(array $value): int => (int) ($value['value'] ?? 0), $overview['rows'][0]['metricValues'] ?? []);
      $report = [
        'available' => TRUE,
        'generated_at' => time(),
        'metrics' => [
          'Users' => $values[0] ?? 0,
          'Sessions' => $values[1] ?? 0,
          'Page Views' => $values[2] ?? 0,
          'Engaged Sessions' => $values[3] ?? 0,
          'Key Events' => $values[4] ?? 0,
        ],
        'pages' => $this->rows($pages, 'Page', 'Views'),
        'sources' => $this->rows($sources, 'Channel', 'Sessions'),
      ];
      $this->cache->set(self::CACHE_ID, $report, time() + 900);
      return $report;
    }
    catch (\Throwable $exception) {
      $this->logger->error('Google Analytics dashboard refresh failed: @message', ['@message' => $exception->getMessage()]);
      return ['available' => FALSE, 'message' => 'Google Analytics data is temporarily unavailable.'];
    }
  }

  private function accessToken(array $credentials): string {
    $now = time();
    $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $claim = $this->base64Url(json_encode([
      'iss' => $credentials['client_email'],
      'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
      'aud' => 'https://oauth2.googleapis.com/token',
      'iat' => $now,
      'exp' => $now + 3600,
    ], JSON_THROW_ON_ERROR));
    $unsigned = $header . '.' . $claim;
    $key = openssl_pkey_get_private((string) $credentials['private_key']);
    if ($key === FALSE || !openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
      throw new \RuntimeException('Unable to sign the Google service-account assertion.');
    }
    $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
      'form_params' => [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $unsigned . '.' . $this->base64Url($signature),
      ],
      'timeout' => 10,
    ]);
    $payload = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    return (string) ($payload['access_token'] ?? throw new \RuntimeException('Google did not return an access token.'));
  }

  private function runReport(string $propertyId, string $token, array $body): array {
    $response = $this->httpClient->request('POST', "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
      'headers' => ['Authorization' => 'Bearer ' . $token],
      'json' => $body,
      'timeout' => 12,
    ]);
    return json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  private function rows(array $report, string $dimensionLabel, string $metricLabel): array {
    return array_map(static fn(array $row): array => [
      $dimensionLabel => (string) ($row['dimensionValues'][0]['value'] ?? '—'),
      $metricLabel => (int) ($row['metricValues'][0]['value'] ?? 0),
    ], $report['rows'] ?? []);
  }

  private function base64Url(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
  }

}
