<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Utility;

/**
 * Read-only lookups against marketing/campaigns/<slug>/*.json — the
 * git-tracked, Python-authored campaign manifests (posting-schedule.json,
 * scorecard.json). Every method here only ever reads a file already on disk;
 * nothing here writes, mutates, or shells out to anything. Path candidates
 * mirror the pattern already used by MarketingCommandController::campaignAsset()
 * and SocialRecordSyncForm, since the module root's relationship to the repo
 * root differs between a Composer-scaffolded checkout and other layouts.
 */
final class CampaignFileLocator {

  /**
   * @return string[]
   *   Absolute candidate paths for marketing/campaigns/<slug>/<filename>, in
   *   the order other module code already checks them in.
   */
  private static function candidates(string $campaignSlug, string $filename): array {
    if (!preg_match('/^[a-z0-9-]+$/', $campaignSlug)) {
      return [];
    }
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
      return [];
    }
    $rel = 'marketing/campaigns/' . $campaignSlug . '/' . $filename;
    return [
      dirname(\Drupal::root(), 2) . '/' . $rel,
      dirname(\Drupal::root()) . '/' . $rel,
      \Drupal::root() . '/../' . $rel,
    ];
  }

  /**
   * Absolute path to marketing/campaigns/<slug>/<filename>, or NULL if no
   * candidate exists on disk.
   */
  public static function locate(string $campaignSlug, string $filename): ?string {
    foreach (self::candidates($campaignSlug, $filename) as $path) {
      if (is_file($path)) {
        return $path;
      }
    }
    return NULL;
  }

  /**
   * Reads and JSON-decodes marketing/campaigns/<slug>/<filename>.
   *
   * @return array<mixed>|null
   *   The decoded associative array, or NULL when the file is missing or not
   *   valid JSON. Callers must treat NULL as "not found / unreadable", never
   *   as an empty-but-present manifest.
   */
  public static function readJson(string $campaignSlug, string $filename): ?array {
    $path = self::locate($campaignSlug, $filename);
    if ($path === NULL) {
      return NULL;
    }
    $decoded = json_decode((string) file_get_contents($path), TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  /**
   * Every campaign slug under marketing/campaigns/ that has a
   * posting-schedule.json, sorted alphabetically.
   *
   * @return string[]
   */
  public static function listCampaignSlugs(): array {
    $roots = [
      dirname(\Drupal::root(), 2) . '/marketing/campaigns',
      dirname(\Drupal::root()) . '/marketing/campaigns',
      \Drupal::root() . '/../marketing/campaigns',
    ];
    foreach ($roots as $dir) {
      if (!is_dir($dir)) {
        continue;
      }
      $slugs = [];
      foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
          continue;
        }
        if (preg_match('/^[a-z0-9-]+$/', $entry) && is_file($dir . '/' . $entry . '/posting-schedule.json')) {
          $slugs[] = $entry;
        }
      }
      sort($slugs);
      return $slugs;
    }
    return [];
  }

  /**
   * Finds one drop by content_id inside a decoded posting-schedule.json.
   *
   * @param array<mixed> $schedule
   *
   * @return array<mixed>
   *   The drop, or an empty array when not found.
   */
  public static function findDrop(array $schedule, string $contentId): array {
    foreach ((array) ($schedule['drops'] ?? []) as $drop) {
      if (is_array($drop) && (string) ($drop['content_id'] ?? '') === $contentId) {
        return $drop;
      }
    }
    return [];
  }

  /**
   * Every distinct Postiz post id already recorded for one drop:
   * provider_ids.postiz_draft_id, provider_ids.postiz_scheduled_id, and every
   * entry of provider_ids.postiz_scheduled_group (one per connected
   * channel), deduplicated.
   *
   * @param array<mixed> $drop
   *
   * @return string[]
   */
  public static function knownProviderIds(array $drop): array {
    $ids = [];
    $providerIds = (array) ($drop['provider_ids'] ?? []);
    foreach (['postiz_draft_id', 'postiz_scheduled_id'] as $key) {
      if (!empty($providerIds[$key])) {
        $ids[(string) $providerIds[$key]] = TRUE;
      }
    }
    foreach ((array) ($providerIds['postiz_scheduled_group'] ?? []) as $id) {
      if ($id !== '' && $id !== NULL) {
        $ids[(string) $id] = TRUE;
      }
    }
    return array_keys($ids);
  }

}
