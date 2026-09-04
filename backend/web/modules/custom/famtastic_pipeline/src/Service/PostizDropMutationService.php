<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Process\Process;

/**
 * Mutates individual Postiz drop posts: create draft, change status, delete.
 *
 * This wraps exactly the three Postiz public API v1 calls proven safe by hand
 * during the 2026-09-03 session (see docs/marketing/CAMPAIGN_POSTING_ARCHITECTURE.md
 * section 4):
 *
 *   - POST   /api/public/v1/posts             create a draft post
 *   - PUT    /api/public/v1/posts/{id}/status  {"status": "draft"|"schedule"}
 *   - DELETE /api/public/v1/posts/{id}         soft-delete (revert to draft
 *     first if it is QUEUE/scheduled — Postiz has no independent "delete a
 *     live schedule" verb, so the safe order is always status->draft, then
 *     delete)
 *
 * Safety rules, all non-negotiable:
 *
 *   1. Every mutation of a post that is NOT part of the caller's own
 *      throwaway-test flow requires $confirmed === TRUE, passed explicitly by
 *      the caller. There is no default-true path. A caller that cannot
 *      truthfully assert this is operating in its own disposable test flow
 *      must pass a real, human-originated confirmation.
 *   2. Every mutation writes one audit log entry to the `famtastic_pipeline`
 *      logger channel recording who (the current Drupal user, or an
 *      explicit $actor override for CLI/cron callers with no session),
 *      when, which content_id/campaign_id, and what changed.
 *   3. Credentials are never hardcoded and never logged. The org API key and
 *      integration ids are resolved exactly as scripts/queue-campaign-drops.py
 *      resolves them: an explicit Settings/env value first, and only when
 *      none is configured AND the base URL is loopback, a direct read from
 *      the local Postiz postgres (the same query queue-campaign-drops.py
 *      uses). A non-loopback base URL with no configured key fails loud
 *      instead of guessing or shelling out to a remote database.
 */
final class PostizDropMutationService {

  private const TIMEOUT_SECONDS = 30;
  private const PG_CONTAINER = 'postiz-postgres';

  private readonly LoggerChannelInterface $logger;

  public function __construct(
    private readonly ClientInterface $httpClient,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly AccountProxyInterface $currentUser,
  ) {
    $this->logger = $loggerFactory->get('famtastic_pipeline');
  }

  /**
   * Configured Postiz public API v1 base URL.
   */
  public function baseUrl(): string {
    $base = rtrim((string) (Settings::get('famtastic_postiz_base_url', 'http://127.0.0.1:4007')), '/');
    return $base . '/api/public/v1';
  }

  /**
   * Creates a draft post on one or more integrations.
   *
   * @param array{
   *   date_utc: string,
   *   posts: array<int, array{integration_identifier: string, content: string, image?: array}>,
   * } $payload
   *   date_utc is an ISO-8601 UTC timestamp (…T…Z). Each posts[] entry names
   *   its integration by Postiz *identifier* (e.g. "facebook", "x",
   *   "instagram-standalone"); the integration id is resolved here.
   * @param bool $confirmed
   *   TRUE only when a human or an already-confirmed automated flow has
   *   explicitly authorized this write. Ignored (treated as implicitly true)
   *   only when $isTestFlow is TRUE.
   * @param bool $isTestFlow
   *   TRUE only for the caller's own disposable verification post — one it
   *   created in this same call chain and intends to delete itself. Never set
   *   TRUE for a post belonging to a real campaign drop.
   * @param string $contentId
   *   The drop's content_id, for the audit trail. Use a synthetic id like
   *   "selftest-<timestamp>" for test-flow posts.
   * @param string $campaignId
   *   The owning campaign_id (or "N/A" for a pure connectivity test).
   * @param string|null $actor
   *   Override for the "who" audit field when there is no Drupal session
   *   (CLI, cron). Defaults to the current Drupal user's account name.
   *
   * @return array{post_id: string, raw: array}
   *
   * @throws \RuntimeException
   *   On a missing confirmation, an unresolvable integration, or a Postiz
   *   error / malformed response.
   */
  public function createDraftPost(
    array $payload,
    bool $confirmed,
    bool $isTestFlow,
    string $contentId,
    string $campaignId,
    ?string $actor = NULL,
  ): array {
    $this->requireConfirmation($confirmed, $isTestFlow, 'create draft', $contentId);

    $key = $this->resolveApiKey();
    $integrationsById = $this->resolveIntegrations($key);

    $postsArray = [];
    foreach ($payload['posts'] as $entry) {
      $identifier = (string) $entry['integration_identifier'];
      $integration = $integrationsById[$identifier] ?? NULL;
      if ($integration === NULL) {
        throw new \RuntimeException("PostizDropMutationService: no connected Postiz integration for identifier '{$identifier}'.");
      }
      $post = [
        'integration' => ['id' => $integration['id']],
        'value' => [['content' => (string) $entry['content']] + (isset($entry['image']) ? ['image' => $entry['image']] : [])],
      ];
      if (!empty($entry['settings'])) {
        $post['settings'] = $entry['settings'];
      }
      $postsArray[] = $post;
    }

    $body = [
      'type' => 'draft',
      'shortLink' => FALSE,
      'date' => $payload['date_utc'],
      'tags' => [],
      'posts' => $postsArray,
    ];

    $response = $this->request('POST', '/posts', $key, $body);
    $postId = $this->extractPostId($response);
    if ($postId === NULL) {
      throw new \RuntimeException('PostizDropMutationService: draft creation returned no post id. Response: ' . substr(json_encode($response) ?: '', 0, 500));
    }

    $this->audit('create_draft', $contentId, $campaignId, $actor, [
      'post_id' => $postId,
      'is_test_flow' => $isTestFlow,
      'integrations' => array_column($payload['posts'], 'integration_identifier'),
      'date_utc' => $payload['date_utc'],
    ]);

    return ['post_id' => $postId, 'raw' => is_array($response) ? $response : []];
  }

  /**
   * Changes a post's status: {"status": "draft"} or {"status": "schedule"}.
   */
  public function changeStatus(
    string $postId,
    string $status,
    bool $confirmed,
    bool $isTestFlow,
    string $contentId,
    string $campaignId,
    ?string $actor = NULL,
  ): array {
    if (!in_array($status, ['draft', 'schedule'], TRUE)) {
      throw new \RuntimeException("PostizDropMutationService: status must be 'draft' or 'schedule', got '{$status}'.");
    }
    $this->requireConfirmation($confirmed, $isTestFlow, "change status to '{$status}'", $contentId);

    $key = $this->resolveApiKey();
    $response = $this->request('PUT', "/posts/{$postId}/status", $key, ['status' => $status]);

    $this->audit('change_status', $contentId, $campaignId, $actor, [
      'post_id' => $postId,
      'new_status' => $status,
      'is_test_flow' => $isTestFlow,
    ]);

    return is_array($response) ? $response : [];
  }

  /**
   * Deletes (soft-deletes) a post.
   *
   * Postiz has no independent "cancel a live schedule" verb, so a post whose
   * status is anything other than draft must be reverted to draft first — the
   * same order proven safe manually this session. This method enforces that
   * order itself rather than trusting the caller to have done it, by taking
   * an explicit $currentStatus and calling changeStatus('draft', ...) first
   * when it is not already 'DRAFT'.
   */
  public function deletePost(
    string $postId,
    string $currentStatus,
    bool $confirmed,
    bool $isTestFlow,
    string $contentId,
    string $campaignId,
    ?string $actor = NULL,
  ): array {
    $this->requireConfirmation($confirmed, $isTestFlow, 'delete', $contentId);

    if (strtoupper($currentStatus) !== 'DRAFT') {
      // Revert to draft first so it cannot fire in the window between here
      // and the delete call. Pass $confirmed through unchanged — this is one
      // logical mutation, not a second independent one to re-approve.
      $this->changeStatus($postId, 'draft', $confirmed, $isTestFlow, $contentId, $campaignId, $actor);
    }

    $key = $this->resolveApiKey();
    $response = $this->request('DELETE', "/posts/{$postId}", $key, NULL);

    $this->audit('delete', $contentId, $campaignId, $actor, [
      'post_id' => $postId,
      'was_status' => $currentStatus,
      'is_test_flow' => $isTestFlow,
    ]);

    return is_array($response) ? $response : [];
  }

  /**
   * Resolves connected integrations, keyed by Postiz `identifier`.
   *
   * @return array<string, array{id: string, identifier: string, name: string}>
   */
  public function resolveIntegrations(?string $key = NULL): array {
    $key ??= $this->resolveApiKey();
    $response = $this->request('GET', '/integrations', $key, NULL);
    $out = [];
    foreach (is_array($response) ? $response : [] as $integration) {
      if (!is_array($integration) || empty($integration['identifier']) || !empty($integration['disabled'])) {
        continue;
      }
      $out[(string) $integration['identifier']] = [
        'id' => (string) $integration['id'],
        'identifier' => (string) $integration['identifier'],
        'name' => (string) ($integration['name'] ?? $integration['identifier']),
      ];
    }
    return $out;
  }

  /**
   * Resolves the Postiz org API key the same way queue-campaign-drops.py's
   * resolve_api_key() does: an explicitly configured value first, and only
   * when the base URL is loopback and nothing is configured, a direct read
   * from the local Postiz postgres. Never hardcoded, never logged.
   */
  private function resolveApiKey(): string {
    $configured = (string) (Settings::get('famtastic_postiz_api_key') ?? (getenv('FAMTASTIC_POSTIZ_API_KEY') ?: ''));
    if ($configured !== '') {
      return $configured;
    }

    $host = (string) parse_url($this->baseUrl(), \PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], TRUE)) {
      throw new \RuntimeException("PostizDropMutationService: no API key configured and {$this->baseUrl()} is not loopback. Set famtastic_postiz_api_key (settings.php) or FAMTASTIC_POSTIZ_API_KEY (env).");
    }

    // Loopback only, matching scripts/queue-campaign-drops.py::resolve_api_key().
    // Symfony\Process with an argument array — never a shell string — so
    // nothing here is shell-injectable regardless of caller input, since no
    // caller-supplied value ever reaches this command.
    $process = new Process([
      'docker', 'exec', self::PG_CONTAINER, 'sh', '-c',
      'psql -U "$POSTGRES_USER" -d "${POSTGRES_DB:-postiz-db-local}" -t -A '
      . '-c \'SELECT "apiKey" FROM "Organization" WHERE "apiKey" IS NOT NULL LIMIT 1\'',
    ]);
    $process->setTimeout(15);
    $process->run();
    $key = trim($process->getOutput());
    if ($key === '') {
      throw new \RuntimeException('PostizDropMutationService: could not resolve a Postiz org API key from the local postgres.');
    }
    return $key;
  }

  private function requireConfirmation(bool $confirmed, bool $isTestFlow, string $action, string $contentId): void {
    if ($isTestFlow) {
      return;
    }
    if (!$confirmed) {
      throw new \RuntimeException("PostizDropMutationService: refusing to {$action} for '{$contentId}' — this is not a test-flow post and \$confirmed was not TRUE. Pass explicit confirmation from a human-authorized caller.");
    }
  }

  private function audit(string $action, string $contentId, string $campaignId, ?string $actor, array $detail): void {
    $who = $actor ?? ($this->currentUser->getAccountName() ?: 'uid:' . $this->currentUser->id());
    $this->logger->info('Postiz drop mutation: @action content_id=@content_id campaign_id=@campaign_id by @who — @detail', [
      '@action' => $action,
      '@content_id' => $contentId,
      '@campaign_id' => $campaignId,
      '@who' => $who,
      '@detail' => json_encode($detail),
    ]);
  }

  private function extractPostId(mixed $response): ?string {
    if (is_array($response) && isset($response[0]) && is_array($response[0])) {
      $first = $response[0];
      return isset($first['postId']) ? (string) $first['postId'] : (isset($first['id']) ? (string) $first['id'] : NULL);
    }
    if (is_array($response)) {
      if (isset($response['postId'])) {
        return (string) $response['postId'];
      }
      if (isset($response['id'])) {
        return (string) $response['id'];
      }
      if (isset($response['posts']) && is_array($response['posts']) && isset($response['posts'][0])) {
        $first = $response['posts'][0];
        return isset($first['postId']) ? (string) $first['postId'] : (isset($first['id']) ? (string) $first['id'] : NULL);
      }
    }
    return NULL;
  }

  /**
   * @return array|null
   *   Decoded JSON body, or NULL when the response body was empty.
   */
  private function request(string $method, string $path, string $key, ?array $body): ?array {
    try {
      $options = [
        'headers' => ['Authorization' => $key],
        'timeout' => self::TIMEOUT_SECONDS,
      ];
      if ($body !== NULL) {
        $options['json'] = $body;
      }
      $response = $this->httpClient->request($method, $this->baseUrl() . $path, $options);
      $raw = (string) $response->getBody();
      if ($raw === '') {
        return NULL;
      }
      $decoded = json_decode($raw, TRUE, 512, \JSON_THROW_ON_ERROR);
      return is_array($decoded) ? $decoded : ['_raw' => substr($raw, 0, 500)];
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException("PostizDropMutationService: Postiz {$method} {$path} failed: " . substr($e->getMessage(), 0, 300), 0, $e);
    }
    catch (\JsonException $e) {
      throw new \RuntimeException("PostizDropMutationService: Postiz {$method} {$path} returned unparseable JSON: " . substr($e->getMessage(), 0, 300), 0, $e);
    }
  }

}
