<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Reads connected publishing channels from the local Postiz public API.
 *
 * Powers the Campaign Operations "Channel health" card so owners see
 * connected/expiring/error state without opening Postiz. Credentials are
 * never stored in the repository: the org API key comes from
 * Settings::get('famtastic_postiz_api_key') or the
 * FAMTASTIC_POSTIZ_API_KEY environment variable.
 */
final class PostizChannelsService {

  private const TIMEOUT_SECONDS = 5;
  /**
   * A token younger than this many days is flagged "expiring" on the card.
   */
  private const EXPIRING_WINDOW_DAYS = 21;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Returns the configured Postiz base URL.
   */
  public function baseUrl(): string {
    return rtrim((string) (Settings::get('famtastic_postiz_base_url', 'http://127.0.0.1:4007')), '/');
  }

  /**
   * Channel health snapshot for the operations dashboard.
   *
   * @return array{configured: bool, reachable: bool, error: string, checked_at: int, platforms: array<int, array{identifier: string, name: string, state: string, detail: string}>}
   *   Platforms carry state connected|disabled|expiring|error plus a human
   *   detail line. When unconfigured/unreachable, platforms is empty and
   *   error explains why.
   */
  public function channels(): array {
    $snapshot = [
      'configured' => FALSE,
      'reachable' => FALSE,
      'error' => '',
      'checked_at' => $this->time->getRequestTime(),
      'platforms' => [],
    ];

    $key = (string) (Settings::get('famtastic_postiz_api_key') ?? (getenv('FAMTASTIC_POSTIZ_API_KEY') ?: ''));
    if ($key === '') {
      $snapshot['error'] = 'No Postiz API key configured (set FAMTASTIC_POSTIZ_API_KEY).';
      return $snapshot;
    }
    $snapshot['configured'] = TRUE;

    $base = rtrim((string) (Settings::get('famtastic_postiz_base_url', 'http://127.0.0.1:4007')), '/');
    try {
      $response = $this->httpClient->request('GET', $base . '/api/public/v1/integrations', [
        'headers' => ['Authorization' => $key],
        'timeout' => self::TIMEOUT_SECONDS,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (GuzzleException |\JsonException $e) {
      $snapshot['error'] = 'Postiz unreachable: ' . substr((string) $e->getMessage(), 0, 160);
      return $snapshot;
    }
    if (!is_array($data)) {
      $snapshot['error'] = 'Postiz returned an unreadable response.';
      return $snapshot;
    }

    $snapshot['reachable'] = TRUE;
    foreach ($data as $integration) {
      if (!is_array($integration) || empty($integration['identifier'])) {
        continue;
      }
      // The public v1 payload carries no token expiry yet; when upstream adds
      // it, map it here. Until then only connected/disabled states ship and
      // expiry stays recorded in marketing/providers.json notes.
      $state = !empty($integration['disabled']) ? 'disabled' : 'connected';
      $snapshot['platforms'][] = [
        'identifier' => (string) $integration['identifier'],
        'name' => (string) ($integration['name'] ?? $integration['identifier']),
        'state' => $state,
        'detail' => $state === 'connected' ? 'OAuth token active' : 'Channel disabled in Postiz',
      ];
    }
    return $snapshot;
  }

}
