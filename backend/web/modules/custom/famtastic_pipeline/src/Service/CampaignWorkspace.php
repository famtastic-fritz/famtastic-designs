<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Utility\CampaignFileLocator;

/** Drupal owns editable briefs; CLI schedules and provider receipts stay read-only. */
final class CampaignWorkspace {
  public const LEGACY = '55-cents-17-day';
  public const CHANNELS = ['email', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok', 'threads', 'pinterest', 'website'];
  public function __construct(private readonly Connection $database, private readonly TimeInterface $time) {}

  public static function validKey(string $key): bool {
    return strlen($key) <= 128 && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key);
  }

  public function all(): array {
    $all = [];
    foreach ($this->database->select('famtastic_campaign', 'c')->fields('c')->orderBy('name')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $row['plan'] = json_decode($row['plan_json'] ?? '{}', TRUE) ?: [];
      $all[$row['campaign_key']] = $row;
    }
    foreach (array_unique(array_merge([self::LEGACY], CampaignFileLocator::listCampaignSlugs())) as $key) {
      $all[$key] ??= ['campaign_key' => $key, 'name' => $key, 'status' => 'source-only', 'revision' => 0, 'plan' => []];
    }
    return $all;
  }

  public function get(string $key): ?array { return $this->all()[$key] ?? NULL; }

  /** Validation is shared by every writer, including callers outside Form API. */
  public static function validate(array $data): array {
    $errors = [];
    if (!self::validKey((string) ($data['campaign_key'] ?? ''))) $errors['campaign_key'] = 'Use up to 128 lowercase letters, numbers and single dashes.';
    if (trim($data['name'] ?? '') === '' || mb_strlen($data['name']) > 255) $errors['name'] = 'Enter a campaign name of 255 characters or fewer.';
    $plan = $data['plan'] ?? [];
    foreach (['start_date', 'end_date'] as $field) {
      $date = $plan[$field] ?? '';
      if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || date('Y-m-d', strtotime($date)) !== $date)) $errors[$field] = 'Enter a valid calendar date.';
    }
    if (!empty($plan['start_date']) && !empty($plan['end_date']) && $plan['start_date'] > $plan['end_date']) $errors['end_date'] = 'End date must be on or after the start date.';
    if (array_diff($plan['channels'] ?? [], self::CHANNELS)) $errors['channels'] = 'Choose supported planning channels.';
    foreach ($plan['content_items'] ?? [] as $i => $item) {
      $date = (string) ($item['date'] ?? '');
      if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || date('Y-m-d', strtotime($date)) !== $date)) $errors['content_items'] = 'Content rows need valid dates.';
      if (!empty($item['channel']) && !in_array($item['channel'], self::CHANNELS, TRUE)) $errors['content_items'] = 'Choose a supported content channel.';
      if ($date !== '' && ((!empty($plan['start_date']) && $date < $plan['start_date']) || (!empty($plan['end_date']) && $date > $plan['end_date']))) $errors['content_items'] = 'Content dates must fall within the campaign dates.';
    }
    if (mb_strlen(json_encode($plan)) > 100000) $errors['content_plan'] = 'This plan is too large. Keep it below 100 KB.';
    return $errors;
  }

  public function save(array $data, ?int $revision = NULL): string {
    if ($errors = self::validate($data)) throw new \InvalidArgumentException(implode(' ', $errors));
    $key = $data['campaign_key'];
    $fields = ['name' => trim($data['name']), 'plan_json' => json_encode($data['plan'], JSON_THROW_ON_ERROR), 'channel' => implode(',', $data['plan']['channels'] ?? []), 'changed' => $this->time->getRequestTime()];
    $transaction = $this->database->startTransaction();
    try {
      if ($revision === NULL) {
        $fields += ['campaign_key' => $key, 'status' => 'draft', 'revision' => 1, 'source_filter' => '', 'created' => $fields['changed']];
        $this->database->insert('famtastic_campaign')->fields($fields)->execute();
      }
      else {
        $fields['revision'] = $revision + 1;
        $updated = $this->database->update('famtastic_campaign')->fields($fields)->condition('campaign_key', $key)->condition('revision', $revision)->condition('status', 'archived', '<>')->execute();
        if (!$updated) throw new \UnexpectedValueException('This campaign changed or was archived. Reload before saving; your unsaved text is still shown.');
      }
    }
    catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
    return $key;
  }

  public function changeStatus(string $key, int $revision, bool $archive): void {
    $updated = $this->database->update('famtastic_campaign')->fields(['status' => $archive ? 'archived' : 'draft', 'revision' => $revision + 1, 'changed' => $this->time->getRequestTime()])->condition('campaign_key', $key)->condition('revision', $revision)->execute();
    if (!$updated) throw new \UnexpectedValueException('Campaign changed. Reload before trying again.');
  }

  /** Stable source IDs remain separate from editable planning lines. */
  public function items(string $key): array {
    $items = [];
    $campaign = $this->get($key);
    foreach (preg_split('/\R/', $campaign['plan']['content_plan'] ?? '') as $i => $line) {
      if (trim($line) === '') continue;
      $items[] = ['content_id' => 'plan-' . ($i + 1), 'scheduled_time' => '', 'theme' => trim($line), 'channels' => $campaign['plan']['channels'] ?? [], 'state' => 'draft', 'source' => 'Drupal plan'];
    }
    foreach ($campaign['plan']['content_items'] ?? [] as $i => $item) {
      $items[] = ['content_id' => 'draft-' . ($i + 1), 'scheduled_time' => ($item['date'] ?? '') . ' (planned, not scheduled)', 'theme' => $item['copy'], 'channels' => array_filter([$item['channel'] ?? '']), 'state' => 'draft', 'source' => 'Drupal plan'];
    }
    $schedule = CampaignFileLocator::readJson($key, 'posting-schedule.json');
    foreach ($schedule['drops'] ?? [] as $drop) if (is_array($drop)) $items[] = $drop + ['source' => 'CLI schedule'];
    if ($key === self::LEGACY) {
      foreach ($this->database->select('famtastic_social_record', 'r')->fields('r')->orderBy('day')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $record) {
        $items[] = $record + ['theme' => $record['promise'], 'scheduled_time' => 'Day ' . $record['day'] . ' · ' . $record['scheduled_time_et'], 'channels' => [], 'source' => 'Imported legacy manifest'];
      }
    }
    return $items;
  }
}
