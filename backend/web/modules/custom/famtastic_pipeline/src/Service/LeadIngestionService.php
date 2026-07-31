<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Validates, normalizes, deduplicates, scores, and persists imported leads.
 */
final class LeadIngestionService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OperationalLedger $ledger,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Imports a bounded UTF-8 CSV and returns aggregate plus row-level results.
   */
  public function importCsv(
    string $path,
    string $source,
    string $campaignKey,
    bool $dryRun = FALSE,
    int $maxRows = 10000,
  ): array {
    if (!is_file($path) || !is_readable($path)) {
      throw new \InvalidArgumentException('CSV file is not readable.');
    }
    if (filesize($path) > 20 * 1024 * 1024) {
      throw new \InvalidArgumentException('CSV exceeds the 20 MB import limit.');
    }
    $handle = fopen($path, 'rb');
    if (!$handle) {
      throw new \RuntimeException('Could not open CSV.');
    }
    try {
      $headers = fgetcsv($handle, escape: '\\');
      if (!$headers) {
        throw new \InvalidArgumentException('CSV has no header row.');
      }
      $headers = array_map(fn ($value) => $this->fieldKey((string) $value), $headers);
      if (!in_array('business_name', $headers, TRUE)) {
        throw new \InvalidArgumentException('CSV requires a business_name column.');
      }
      $results = [];
      $line = 1;
      while (($values = fgetcsv($handle, escape: '\\')) !== FALSE) {
        $line++;
        if ($line > $maxRows + 1) {
          throw new \InvalidArgumentException(sprintf('CSV exceeds the %d-row import limit.', $maxRows));
        }
        $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
        $row = array_combine($headers, $values);
        $row['_line'] = $line;
        $results[] = $this->importRow($row, $source, $campaignKey, $dryRun);
      }
    }
    finally {
      fclose($handle);
    }
    $counts = array_count_values(array_column($results, 'status'));
    return [
      'dry_run' => $dryRun,
      'source' => $source,
      'campaign' => $campaignKey,
      'total' => count($results),
      'counts' => $counts,
      'rows' => $results,
    ];
  }

  /**
   * Imports one normalized source row.
   */
  public function importRow(array $row, string $source, string $campaignKey, bool $dryRun = FALSE): array {
    $source = $this->clean($source, 128);
    $campaignKey = $this->clean($campaignKey, 128);
    if ($source === '' || $campaignKey === '') {
      throw new \InvalidArgumentException('Source and campaign are required.');
    }
    $normalized = $this->normalize($row);
    $assessment = $this->assess($normalized);
    $dedupeKey = $this->dedupeKey($normalized);
    $sourceRecordId = $this->clean((string) ($row['source_record_id'] ?? $row['id'] ?? ''), 255);
    $importKey = hash('sha256', implode('|', [$source, $campaignKey, $sourceRecordId, $dedupeKey]));

    if ($this->alreadyImported($importKey, $dedupeKey)) {
      return $this->result('duplicate', $assessment, NULL, $dedupeKey, ['Already imported or matches an existing lead.']);
    }
    if ($normalized['email'] !== '' && $this->ledger->isSuppressed($normalized['email'])) {
      $assessment['status'] = 'suppressed';
      $assessment['reasons'][] = 'Contact is on the suppression list.';
    }
    if ($this->matchesExistingProspect($normalized)) {
      $assessment['status'] = 'duplicate';
      $assessment['reasons'][] = 'Matches an existing prospect.';
    }

    if ($dryRun) {
      return $this->result($assessment['status'], $assessment, NULL, $dedupeKey);
    }

    $transaction = $this->database->startTransaction();
    try {
      $campaignId = $this->ensureCampaign($campaignKey, $source);
      $prospectId = NULL;
      if ($assessment['status'] === 'qualified') {
        $prospect = Prospect::create([
          'business_name' => $normalized['business_name'],
          'business_category' => $normalized['business_category'],
          'business_description' => $normalized['business_description'],
          'address' => $normalized['address'],
          'service_area' => $normalized['service_area'],
          'public_phone' => $normalized['phone'],
          'public_email' => $normalized['email'],
          'website_url' => $normalized['website_url'],
          'campaign' => $campaignKey,
          'source' => $source,
          'discovery_notes' => sprintf(
            'Imported source record %s; score %d; target %s; reasons: %s',
            $sourceRecordId ?: 'n/a',
            $assessment['score'],
            $assessment['target_offer'],
            implode(' ', $assessment['reasons']),
          ),
          'status' => 'new',
        ]);
        $prospect->save();
        $prospectId = (int) $prospect->id();
        $this->ledger->recordEvent(
          'lead.imported:' . $importKey,
          'lead.imported',
          [
            'source' => $source,
            'source_record_id' => $sourceRecordId,
            'score' => $assessment['score'],
            'target_offer' => $assessment['target_offer'],
          ],
          $prospectId,
          $campaignId,
        );
        $this->ledger->enqueue(
          'proof.generate:prospect:' . $prospectId,
          'proof.generate',
          ['prospect_id' => $prospectId, 'required_variants' => 3],
          $prospectId,
        );
      }
      $this->database->insert('famtastic_lead_import')
        ->fields([
          'import_key' => $importKey,
          'dedupe_key' => $dedupeKey,
          'source_name' => $source,
          'source_record_id' => $sourceRecordId,
          'campaign_key' => $campaignKey,
          'contact_hash' => $normalized['email'] !== '' ? $this->ledger->contactHash($normalized['email']) : '',
          'business_hash' => hash('sha256', $normalized['business_name']),
          'status' => $assessment['status'],
          'score' => $assessment['score'],
          'target_offer' => $assessment['target_offer'],
          'reasons' => json_encode($assessment['reasons'], JSON_THROW_ON_ERROR),
          'prospect_id' => $prospectId,
          'imported_at' => $this->time->getRequestTime(),
        ])
        ->execute();
      return $this->result($assessment['status'], $assessment, $prospectId, $dedupeKey);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Normalizes source-specific headings and values.
   */
  private function normalize(array $row): array {
    $email = mb_strtolower(trim((string) ($row['email'] ?? $row['public_email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $email = '';
    }
    $website = trim((string) ($row['website_url'] ?? $row['website'] ?? ''));
    if ($website !== '' && !preg_match('#^https?://#i', $website)) {
      $website = 'https://' . $website;
    }
    $phone = preg_replace('/[^0-9+]/', '', (string) ($row['phone'] ?? $row['public_phone'] ?? ''));
    return [
      'business_name' => $this->clean((string) ($row['business_name'] ?? ''), 255),
      'business_category' => $this->clean((string) ($row['business_category'] ?? $row['category'] ?? ''), 255),
      'business_description' => $this->clean((string) ($row['business_description'] ?? $row['description'] ?? ''), 5000),
      'address' => $this->clean((string) ($row['address'] ?? ''), 5000),
      'service_area' => $this->clean((string) ($row['service_area'] ?? $row['city'] ?? ''), 255),
      'email' => $email,
      'phone' => $phone,
      'website_url' => $website,
      'upgrade_signal' => filter_var($row['upgrade_signal'] ?? FALSE, FILTER_VALIDATE_BOOL)
        || in_array(mb_strtolower(trim((string) ($row['website_quality'] ?? ''))), ['poor', 'outdated', 'broken'], TRUE),
    ];
  }

  /**
   * Produces deterministic qualification and target-offer facts.
   */
  private function assess(array $lead): array {
    $reasons = [];
    if ($lead['business_name'] === '') {
      return ['status' => 'invalid', 'score' => 0, 'target_offer' => '', 'reasons' => ['Missing business name.']];
    }
    if ($lead['email'] === '') {
      return ['status' => 'invalid', 'score' => 0, 'target_offer' => '', 'reasons' => ['Missing or invalid outreach email.']];
    }
    if ($lead['website_url'] === '') {
      $reasons[] = 'No website detected.';
      return ['status' => 'qualified', 'score' => 100, 'target_offer' => 'essential_199', 'reasons' => $reasons];
    }
    if ($lead['upgrade_signal']) {
      $reasons[] = 'Existing website has an explicit upgrade signal.';
      return ['status' => 'qualified', 'score' => 80, 'target_offer' => 'business_499', 'reasons' => $reasons];
    }
    return ['status' => 'unqualified', 'score' => 30, 'target_offer' => '', 'reasons' => ['Existing website has no verified upgrade signal.']];
  }

  /**
   * Uses strongest available identity without storing its raw value.
   */
  private function dedupeKey(array $lead): string {
    if ($lead['email'] !== '') {
      return 'email:' . hash('sha256', $lead['email']);
    }
    if ($lead['website_url'] !== '') {
      $host = parse_url($lead['website_url'], PHP_URL_HOST) ?: $lead['website_url'];
      return 'domain:' . hash('sha256', mb_strtolower((string) $host));
    }
    if ($lead['phone'] !== '') {
      return 'phone:' . hash('sha256', $lead['phone']);
    }
    return 'business:' . hash('sha256', mb_strtolower($lead['business_name'] . '|' . $lead['address']));
  }

  private function alreadyImported(string $importKey, string $dedupeKey): bool {
    $query = $this->database->select('famtastic_lead_import', 'i');
    $or = $query->orConditionGroup()
      ->condition('import_key', $importKey)
      ->condition('dedupe_key', $dedupeKey);
    $query->condition($or);
    return (bool) $query->countQuery()->execute()->fetchField();
  }

  private function matchesExistingProspect(array $lead): bool {
    $query = $this->entityTypeManager->getStorage('famtastic_prospect')
      ->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1);
    $or = $query->orConditionGroup();
    if ($lead['email'] !== '') {
      $or->condition('public_email', $lead['email']);
    }
    if ($lead['phone'] !== '') {
      $or->condition('public_phone', $lead['phone']);
    }
    if ($lead['website_url'] !== '') {
      $or->condition('website_url', $lead['website_url']);
    }
    if (count($or->conditions()) === 0) {
      return FALSE;
    }
    return (bool) $query->condition($or)->execute();
  }

  private function ensureCampaign(string $campaignKey, string $source): int {
    $existing = $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_key', $campaignKey)
      ->execute()
      ->fetchField();
    if ($existing) {
      return (int) $existing;
    }
    $now = $this->time->getRequestTime();
    return (int) $this->database->insert('famtastic_campaign')
      ->fields([
        'campaign_key' => $campaignKey,
        'name' => $campaignKey,
        'status' => 'draft',
        'channel' => 'email',
        'source_filter' => json_encode(['source' => $source], JSON_THROW_ON_ERROR),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
  }

  private function result(string $status, array $assessment, ?int $prospectId, string $dedupeKey, array $extraReasons = []): array {
    return [
      'status' => $status,
      'score' => (int) $assessment['score'],
      'target_offer' => (string) $assessment['target_offer'],
      'prospect_id' => $prospectId,
      'dedupe_key' => $dedupeKey,
      'reasons' => array_values(array_merge($assessment['reasons'], $extraReasons)),
    ];
  }

  private function fieldKey(string $value): string {
    return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
  }

  private function clean(string $value, int $length): string {
    return mb_substr(trim(strip_tags(preg_replace('/[^\P{C}\n\t]+/u', '', $value))), 0, $length);
  }

}
