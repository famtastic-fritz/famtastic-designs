<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Persists the UTM/click-ID attribution snapshot taken at lead creation.
 *
 * One JSON snapshot per prospect (utm_source, utm_medium, utm_campaign,
 * utm_content, utm_term, gclid, fbclid) plus the capture route and time.
 * When a snapshot carries a utm_content that matches a social record's
 * content_id, that record's leads_count counter is incremented so the
 * Marketing Command Center can attribute leads at content grain.
 *
 * content_id (e.g. "drop-01") is only unique WITHIN a campaign — every
 * campaign's posting-schedule.json scaffolds drops named drop-01, drop-02,
 * etc., so the same bare content_id can legitimately exist in two different
 * campaigns. The durable identity of a drop is therefore the compound key
 * "{campaign_id}/{content_id}" (utm_campaign + utm_content together), and
 * famtastic_social_record.recordSocialLead() matches on both columns —
 * never on content_id alone, which could silently credit the wrong
 * campaign's post.
 */
final class AttributionService {

  /**
   * The attribution parameters captured at lead creation.
   */
  public const PARAMS = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
    'gclid',
    'fbclid',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Reads attribution from the request query string and its JSON body.
   *
   * Body values win over query values: the SPA forwards the landing URL
   * params inside the payload, while query support keeps the endpoint
   * testable and server-side clients attribution-capable.
   */
  public function snapshotFromRequest(Request $request, string $via): array {
    return $this->snapshot($request->query->all(), $this->bodyArray($request), $via);
  }

  /**
   * Reads attribution from an already-decoded JSON body array.
   */
  public function snapshotFromArray(array $data, string $via): array {
    return $this->snapshot([], $data, $via);
  }

  /**
   * Encodes a snapshot for the prospect utm_json field; NULL when empty.
   */
  public function toJson(array $snapshot): ?string {
    if ($snapshot === []) {
      return NULL;
    }
    $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) && $encoded !== '' ? mb_substr($encoded, 0, 5000) : NULL;
  }

  /**
   * Increments the social record lead counter for one drop.
   *
   * A drop's durable identity is the compound key
   * "{campaign_id}/{content_id}" — content_id alone (e.g. "drop-01") is only
   * unique WITHIN a campaign, since every campaign's posting-schedule.json
   * scaffolds drops named drop-01, drop-02, etc. $campaignId is normally
   * utm_campaign from the same attribution snapshot as $contentId's
   * utm_content. Matching on content_id alone here would risk crediting a
   * lead to a different campaign's post that happens to reuse the same
   * bare content_id.
   *
   * Missing table/column/record is a no-op so lead capture stays durable
   * regardless of migration or import state. An empty $campaignId matches
   * only rows whose campaign_id is also empty (pre-migration or legacy
   * rows); it never falls back to a content_id-only match.
   */
  public function recordSocialLead(string $contentId, string $campaignId = ''): void {
    $contentId = mb_substr(trim($contentId), 0, 64);
    $campaignId = mb_substr(trim($campaignId), 0, 191);
    if ($contentId === '') {
      return;
    }
    $schema = $this->database->schema();
    if (!$schema->tableExists('famtastic_social_record') || !$schema->fieldExists('famtastic_social_record', 'leads_count')) {
      return;
    }
    if (!$schema->fieldExists('famtastic_social_record', 'campaign_id')) {
      // Pre-migration table: content_id is still the table's only identity.
      $this->database->update('famtastic_social_record')
        ->expression('leads_count', 'leads_count + 1')
        ->condition('content_id', $contentId)
        ->execute();
      return;
    }
    $this->database->update('famtastic_social_record')
      ->expression('leads_count', 'leads_count + 1')
      ->condition('content_id', $contentId)
      ->condition('campaign_id', $campaignId)
      ->execute();
  }

  /**
   * Builds one normalized attribution snapshot.
   *
   * Precedence per parameter (last non-empty wins): request query, then flat
   * body keys, then the body "utm" object. Only present values are stored,
   * plus the capture route and time for later first/last-touch analysis.
   */
  private function snapshot(array $query, array $body, string $via): array {
    $values = [];
    foreach (self::PARAMS as $key) {
      $value = '';
      foreach ([$query[$key] ?? NULL, $body[$key] ?? NULL, $body['utm'][$key] ?? NULL] as $candidate) {
        $cleaned = $this->clean($candidate);
        if ($cleaned !== '') {
          $value = $cleaned;
        }
      }
      if ($value !== '') {
        $values[$key] = $value;
      }
    }
    if ($values === []) {
      return [];
    }
    $values['captured_via'] = mb_substr(trim($via), 0, 64);
    $values['captured_at'] = $this->time->getRequestTime();
    return $values;
  }

  private function bodyArray(Request $request): array {
    $decoded = json_decode($request->getContent() ?: '[]', TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  private function clean(mixed $candidate): string {
    if (!is_scalar($candidate)) {
      return '';
    }
    $value = trim(strip_tags((string) $candidate));
    $value = preg_replace('/[^\P{C}]+/u', '', $value) ?? '';
    return mb_substr($value, 0, 255);
  }

}
