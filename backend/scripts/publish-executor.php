<?php

declare(strict_types=1);

/**
 * Publish executor for the 17-day campaign (SOCIAL_POSTING steps 4-5):
 * converts owner-approved Postiz DRAFT posts into SCHEDULED posts and
 * verifies every conversion by read-back.
 *
 * ARMING SWITCH — the script refuses to run unless:
 *   environment FAMTASTIC_MARKETING_PUBLISH === 'true'
 * Owner directive 2026-09-03: the second gate (CLI flag
 * --i-have-owner-publish-approval) is removed so an unattended scheduler
 * (launchd/cron on the operator host) can run this without a human typing a
 * flag. FAMTASTIC_MARKETING_PUBLISH remains the single arming switch and is
 * deliberately NOT defaulted on anywhere in the repo: an agent session that has
 * not been armed still cannot send. The flag is accepted but ignored so older
 * runbooks and scripts keep working.
 *
 * STALE-DATE GUARD — a draft whose stored date is already in the past is never
 * converted. Postiz keeps the stored date when a draft becomes scheduled, so
 * converting a backdated draft fires it immediately; a whole campaign's backlog
 * would blast at once. Such records are reported BLOCKED (provider_state=
 * stale_date) and must be re-dated first (scripts/retime-campaign-drafts.py).
 * Override for a deliberate immediate send with --allow-stale-dates.
 *
 * Conversion is IN PLACE: PUT /posts/{id}/status {"status":"schedule"} keeps
 * the stored post id, content, media, and date and restarts the publishing
 * workflow (docs.postiz.com/public-api/posts/change-status). No
 * delete+recreate is needed on any supported Postiz build.
 *
 * Idempotency contract:
 * - Records already carrying provider_state scheduled/published are skipped.
 * - A draft already in state QUEUE counts as success with zero mutations.
 * - A missing draft id is adopted by utm_content match when possible;
 *   otherwise the record is marked provider_state=missing_draft and reported
 *   BLOCKED — a day is never skipped silently.
 *
 * Provider configuration:
 * - FAMTASTIC_POSTIZ_BASE_URL (default http://127.0.0.1:4007/api/public/v1)
 * - FAMTASTIC_POSTIZ_API_KEY (required off-loopback); on loopback hosts the
 *   key may be fetched from the local Postiz postgres container exactly like
 *   scripts/queue-55-cent-days-1-3-drafts.sh. Keys are never printed,
 *   logged, or committed.
 *
 * Run:
 *   FAMTASTIC_MARKETING_PUBLISH=true \
 *     drush -r backend/web php:script backend/scripts/publish-executor.php -- --limit=12
 * --selftest performs
 * a full synthetic create->schedule->verify->revert->delete loop against the
 * configured instance (still gate-protected, far-future dated, fully cleaned).
 */

$scriptStart = microtime(TRUE);
$runId = gmdate('Ymd\THis') . 'Z-' . getmypid();
$repoRoot = dirname(\Drupal::root(), 2);

// ---------------------------------------------------------------------------
// Arming switch (single gate as of 2026-09-03).
// ---------------------------------------------------------------------------
$envGate = getenv('FAMTASTIC_MARKETING_PUBLISH') === 'true';
$argvRaw = implode(' ', $_SERVER['argv'] ?? []) . ' ' . implode(' ', $extra ?? []);
// Accepted for backwards compatibility with existing runbooks; no longer required.
$flagGate = str_contains($argvRaw, '--i-have-owner-publish-approval');
$selftest = str_contains($argvRaw, '--selftest');
$allowStaleDates = str_contains($argvRaw, '--allow-stale-dates');
if (!$envGate) {
  fwrite(STDERR, "REFUSED: publish executor did not run.\n");
  fwrite(STDERR, "Missing: env FAMTASTIC_MARKETING_PUBLISH=true\n");
  fwrite(STDERR, "This is the single arming switch for real sends. Set it in the operator\n");
  fwrite(STDERR, "host environment (or the scheduled job) to publish. Nothing was read,\n");
  fwrite(STDERR, "sent, or changed.\n");
  exit(2);
}

// ---------------------------------------------------------------------------
// Options.
// ---------------------------------------------------------------------------
$limit = 12;
if (preg_match('/--limit=(\d+)/', $argvRaw, $m)) {
  $limit = max(1, min(25, (int) $m[1]));
}
$manifestPath = $repoRoot . '/marketing/campaigns/55-cents-17-day/manifest.json';
if (preg_match('/--manifest=((?:[^ \t"]|\\\\ )+)/', $argvRaw, $m)) {
  $manifestPath = stripslashes($m[1]);
}

// ---------------------------------------------------------------------------
// Provider configuration.
// ---------------------------------------------------------------------------
$baseUrl = rtrim((string) (getenv('FAMTASTIC_POSTIZ_BASE_URL') ?: 'http://127.0.0.1:4007/api/public/v1'), '/');
$host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
$isLoopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], TRUE);
$apiKey = (string) getenv('FAMTASTIC_POSTIZ_API_KEY');
$keySource = 'env';
if ($apiKey === '') {
  if (!$isLoopback) {
    fwrite(STDERR, "FAIL: FAMTASTIC_POSTIZ_API_KEY is not set and base URL is not loopback.\n");
    fwrite(STDERR, "Set the key via settings/env on this host; never commit it.\n");
    exit(3);
  }
  $pgContainer = (string) (getenv('POSTIZ_PG_CONTAINER') ?: 'postiz-postgres');
  $cmd = sprintf(
    'docker exec %s sh -c %s 2>/dev/null',
    escapeshellarg($pgContainer),
    escapeshellarg('psql -U "$POSTGRES_USER" -d "${POSTGRES_DB:-postiz-db-local}" -t -A -c "SELECT \"apiKey\" FROM \"Organization\" WHERE \"apiKey\" IS NOT NULL LIMIT 1"')
  );
  $apiKey = trim((string) shell_exec($cmd));
  $keySource = 'local-postgres:' . $pgContainer;
}
if ($apiKey === '') {
  fwrite(STDERR, "FAIL: no Postiz API key resolvable for {$baseUrl}\n");
  exit(3);
}

// ---------------------------------------------------------------------------
// HTTP helpers with 5xx retry (local Postiz cold starts answer 502).
// ---------------------------------------------------------------------------
$http = static function (string $method, string $url, ?array $json = NULL, int $attempts = 4) use ($apiKey): array {
  $backoff = 2;
  $lastError = '';
  for ($attempt = 1; $attempt <= $attempts; $attempt++) {
    $ch = curl_init($url);
    $headers = ['Authorization: ' . $apiKey];
    if ($json !== NULL) {
      $headers[] = 'Content-Type: application/json';
    }
    $opts = [
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_HTTPHEADER => $headers,
    ];
    if ($json !== NULL) {
      $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($ch, $opts);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($status >= 500 || ($status === 0 && $curlErr !== '')) {
      $lastError = "HTTP {$status} {$curlErr}";
      sleep($backoff);
      $backoff = min(10, $backoff * 2);
      continue;
    }
    $decoded = NULL;
    if ($body !== '') {
      try {
        $decoded = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException) {
        $decoded = ['_raw' => mb_substr($body, 0, 300)];
      }
    }
    return ['status' => $status, 'body' => $decoded];
  }
  return ['status' => 0, 'body' => ['_error' => $lastError]];
};

$evidenceDirBase = getenv('FAMTASTIC_PUBLISH_EVIDENCE_DIR') ?: ($repoRoot . '/.artifacts/publish-executor');
$evidenceDir = $evidenceDirBase . '/' . $runId;
if (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0770, TRUE)) {
  fwrite(STDERR, "FAIL: cannot create evidence directory {$evidenceDir}\n");
  exit(4);
}
$writeEvidence = static function (array $evidence) use ($evidenceDir): string {
  $evidence['generated_at'] = gmdate(DATE_ATOM);
  $path = $evidenceDir . '/evidence.json';
  file_put_contents($path, json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
  return $path;
};

$fail = static function (string $message, array $partial) use ($writeEvidence): void {
  $partial['status'] = FALSE;
  $path = $writeEvidence($partial);
  fwrite(STDERR, "FAIL: {$message}\nEvidence: {$path}\n");
  exit(5);
};

// ---------------------------------------------------------------------------
// Connectivity check.
// ---------------------------------------------------------------------------
$connected = $http('GET', $baseUrl . '/is-connected');
$connectedOk = ($connected['status'] ?? 0) === 200
  && (($connected['body']['connected'] ?? FALSE) === TRUE);
if (!$connectedOk) {
  $fail('Postiz is-connected failed', [
    'schema' => 'famtastic.publish-executor.v1',
    'base_url_host' => $host,
    'key_source' => $keySource,
    'connectivity' => $connected,
    'run_id' => $runId,
  ]);
}

$db = \Drupal::database();
$time = \Drupal::time()->getRequestTime();

// ---------------------------------------------------------------------------
// SELFTEST: synthetic records through the full loop, then total cleanup.
// Scheduled 2099 so even an abandoned QUEUE state can never fire on the live
// integrations; reverted to DRAFT (terminates the publishing workflow) before
// deletion per docs.postiz.com/public-api/posts/change-status.
// ---------------------------------------------------------------------------
$selftestIds = [];
$candidates = [];
$syntheticRows = [];

if ($selftest) {
  // 1x1 transparent PNG — enough for the upload endpoint.
  $png = (string) base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    TRUE
  );
  $tmpPng = tempnam(sys_get_temp_dir(), 'pubexec') . '.png';
  file_put_contents($tmpPng, $png);

  $integrations = $http('GET', $baseUrl . '/integrations');
  $integrationId = '';
  foreach (($integrations['body'] ?? []) as $integration) {
    if (($integration['identifier'] ?? '') === 'facebook' && empty($integration['disabled'])) {
      $integrationId = (string) $integration['id'];
      break;
    }
  }
  if ($integrationId === '') {
    unlink($tmpPng);
    $fail('selftest: no enabled facebook integration', ['schema' => 'famtastic.publish-executor.v1', 'run_id' => $runId]);
  }

  foreach (['55c-synth-exec-a', '55c-synth-exec-b'] as $index => $syntheticId) {
    $ch = curl_init($baseUrl . '/upload');
    curl_setopt_array($ch, [
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey],
      CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmpPng, 'image/png', 'publish-executor-selftest.png')],
    ]);
    $uploadBody = (string) curl_exec($ch);
    curl_close($ch);
    $uploaded = json_decode($uploadBody, TRUE);
    if (!isset($uploaded['id'], $uploaded['path'])) {
      unlink($tmpPng);
      $fail("selftest: upload failed for {$syntheticId}", [
        'schema' => 'famtastic.publish-executor.v1', 'run_id' => $runId, 'raw' => mb_substr($uploadBody, 0, 300),
      ]);
    }
    $iso = '2099-06-01T12:0' . $index . ':00.000Z';
    $content = "SELFTEST {$syntheticId} — never publishes. https://example.test/?utm_content={$syntheticId}";
    $create = $http('POST', $baseUrl . '/posts', [
      'type' => 'draft', 'shortLink' => FALSE, 'date' => $iso, 'tags' => [],
      'posts' => [['integration' => ['id' => $integrationId],
                   'value' => [['content' => $content, 'image' => [$uploaded]]]]],
    ]);
    $createdId = $create['body']['postId']
      ?? $create['body']['id']
      ?? ($create['body'][0]['postId'] ?? ($create['body'][0]['id'] ?? NULL));
    if (!is_string($createdId) || $createdId === '') {
      unlink($tmpPng);
      $fail("selftest: draft creation failed for {$syntheticId}", [
        'schema' => 'famtastic.publish-executor.v1', 'run_id' => $runId, 'response' => $create,
      ]);
    }
    $db->merge('famtastic_social_record')->key('content_id', $syntheticId)->insertFields([
      'content_id' => $syntheticId, 'day' => 99, 'moment' => 'selftest',
      'theme' => 'selftest', 'promise' => 'publish-executor selftest row',
      'scheduled_time_et' => '12:0' . $index, 'state' => 'media_ready',
      'approval_content' => 1, 'approval_media' => 1, 'approval_publish' => 1,
      'postiz_draft_id' => $createdId, 'asset_variants' => '{}', 'changed' => $time,
    ])->execute();
    $row = $db->select('famtastic_social_record', 'r')->fields('r', ['id'])->condition('content_id', $syntheticId)->execute()->fetchField();
    $selftestIds[] = $createdId;
    $syntheticRows[$syntheticId] = (int) $row;
  }
  unlink($tmpPng);
  echo "SELFTEST: created 2 synthetic drafts (" . implode(', ', $selftestIds) . ") dated 2099\n";
}

// ---------------------------------------------------------------------------
// Candidate selection.
// ---------------------------------------------------------------------------
$candidateQuery = $db->select('famtastic_social_record', 'r')->fields('r')
  ->condition('approval_publish', 1)
  ->condition('postiz_draft_id', '', '<>')
  ->condition('postiz_scheduled_id', '')
  // '' (untouched) passes too — NOT IN covers both fresh and retryable states.
  ->condition('provider_state', ['scheduled', 'published'], 'NOT IN');
if ($selftest) {
  $candidateQuery->condition('moment', 'selftest');
}
else {
  $candidateQuery->condition('moment', 'selftest', '<>');
}
$rows = $candidateQuery->orderBy('day')->orderBy('id')->range(0, $limit)->execute()->fetchAll(\PDO::FETCH_ASSOC);

echo sprintf(
  "ARMED: FAMTASTIC_MARKETING_PUBLISH=true | stale_dates=%s | provider=%s (key: %s) | candidates=%d limit=%d mode=%s\n",
  $allowStaleDates ? 'ALLOWED' : 'blocked', $baseUrl, $keySource, count($rows), $limit, $selftest ? 'SELFTEST' : 'CAMPAIGN'
);

// ---------------------------------------------------------------------------
// List posts once; index by id and by utm_content for adoption.
// ---------------------------------------------------------------------------
$listWindow = $selftest
  ? ['startDate' => '2099-01-01T00:00:00.000Z', 'endDate' => '2100-01-01T00:00:00.000Z']
  : ['startDate' => gmdate('Y-m-d\T00:00:00.000Z', $time - 1209600), 'endDate' => gmdate('Y-m-d\T00:00:00.000Z', $time + 10368000)];
$listUrl = $baseUrl . '/posts?' . http_build_query($listWindow);
$listing = $http('GET', $listUrl);
if (($listing['status'] ?? 0) !== 200 || !isset($listing['body']['posts'])) {
  $fail('posts listing failed', ['schema' => 'famtastic.publish-executor.v1', 'run_id' => $runId, 'window' => $listWindow, 'response_status' => $listing['status'] ?? 0]);
}
$byId = [];
$byUtm = [];
foreach ($listing['body']['posts'] as $post) {
  $pid = (string) ($post['id'] ?? '');
  if ($pid !== '') {
    $byId[$pid] = $post;
  }
  if (preg_match('/utm_content=([a-zA-Z0-9_-]+)/', (string) ($post['content'] ?? ''), $m)) {
    $byUtm[$m[1]] = $post;
  }
}

// ---------------------------------------------------------------------------
// Per-record conversion.
// ---------------------------------------------------------------------------
$results = [];
foreach ($rows as $record) {
  $cid = (string) $record['content_id'];
  $draftId = (string) $record['postiz_draft_id'];
  $entry = [
    'content_id' => $cid, 'day' => (int) $record['day'], 'draft_id' => $draftId,
    'action' => NULL, 'post_id' => NULL, 'state_after' => NULL, 'verified' => FALSE,
  ];

  $post = $byId[$draftId] ?? NULL;
  if ($post === NULL) {
    // Adopt a re-created/renamed draft by its utm_content marker.
    $adopted = $byUtm[$cid] ?? NULL;
    if ($adopted !== NULL && in_array($adopted['state'] ?? '', ['DRAFT', 'QUEUE'], TRUE)) {
      $entry['action'] = 'adopted_by_utm';
      $draftId = (string) $adopted['id'];
      $post = $adopted;
      $entry['draft_id'] = $draftId;
    }
  }

  if ($post === NULL) {
    $entry['action'] = 'missing_draft_blocked';
    $db->update('famtastic_social_record')
      ->fields(['provider_state' => 'missing_draft', 'changed' => $time])
      ->condition('id', (int) $record['id'])->execute();
    $results[] = $entry;
    echo "BLOCKED: {$cid} — draft {$record['postiz_draft_id']} not found in provider window; marked missing_draft\n";
    continue;
  }

  // Stale-date guard. Postiz preserves the stored date across the status
  // change, so converting a backdated draft publishes it on the spot — an
  // entire queued backlog would fire at once the moment publishing is armed.
  // Re-date first (scripts/retime-campaign-drafts.py), never convert blind.
  $postDate = (string) ($post['publishDate'] ?? ($post['date'] ?? ''));
  $postTs = $postDate !== '' ? strtotime($postDate) : FALSE;
  $entry['provider_date'] = $postDate;
  if (!$allowStaleDates && ($post['state'] ?? '') === 'DRAFT') {
    if ($postTs === FALSE) {
      $entry['action'] = 'undated_draft_blocked';
      $db->update('famtastic_social_record')
        ->fields(['provider_state' => 'undated', 'changed' => $time])
        ->condition('id', (int) $record['id'])->execute();
      $results[] = $entry;
      echo "BLOCKED: {$cid} — draft has no readable date; refusing to convert\n";
      continue;
    }
    if ($postTs <= $time) {
      $entry['action'] = 'stale_date_blocked';
      $db->update('famtastic_social_record')
        ->fields(['provider_state' => 'stale_date', 'changed' => $time])
        ->condition('id', (int) $record['id'])->execute();
      $results[] = $entry;
      printf(
        "BLOCKED: %s — draft dated %s is in the past; would publish immediately. Re-date it first.\n",
        $cid,
        gmdate(DATE_ATOM, $postTs)
      );
      continue;
    }
  }

  $state = (string) ($post['state'] ?? '');
  if ($state === 'QUEUE') {
    // Already scheduled (previous run died between mutation and bookkeeping).
    $entry['action'] = 'already_scheduled';
    $entry['post_id'] = $draftId;
    $entry['state_after'] = 'QUEUE';
    $entry['verified'] = TRUE;
  }
  elseif ($state !== 'DRAFT') {
    $entry['action'] = 'unexpected_provider_state';
    $entry['state_after'] = $state;
    $results[] = $entry;
    echo "SKIP: {$cid} — provider state {$state} is neither DRAFT nor QUEUE; left untouched\n";
    continue;
  }
  else {
    $converted = $http('PUT', $baseUrl . '/posts/' . rawurlencode($draftId) . '/status', ['status' => 'schedule']);
    if (($converted['status'] ?? 0) !== 200) {
      $entry['action'] = 'convert_failed';
      $entry['http'] = $converted['status'];
      $results[] = $entry;
      echo "FAIL: {$cid} — status conversion HTTP {$converted['status']}\n";
      continue;
    }
    $entry['action'] = 'scheduled_in_place';
    $entry['post_id'] = $draftId;
    $entry['state_after'] = (string) ($converted['body']['state'] ?? 'UNKNOWN');
  }

  $results[] = $entry;
  $db->update('famtastic_social_record')->fields([
    'postiz_scheduled_id' => (string) $entry['post_id'],
    'provider_state' => 'scheduled',
    'changed' => $time,
  ])->condition('id', (int) $record['id'])->execute();
}

// ---------------------------------------------------------------------------
// Read-back verification (fresh listing; assert QUEUE for every conversion).
// ---------------------------------------------------------------------------
$readback = $http('GET', $listUrl);
$readbackPosts = $readback['body']['posts'] ?? [];
$rbById = [];
foreach ($readbackPosts as $post) {
  $rbById[(string) ($post['id'] ?? '')] = $post;
}
$allVerified = TRUE;
foreach ($results as &$entry) {
  if (!in_array($entry['action'], ['scheduled_in_place', 'already_scheduled', 'adopted_by_utm'], TRUE)) {
    continue;
  }
  $remoteState = (string) ($rbById[(string) $entry['post_id']]['state'] ?? 'NOT_FOUND');
  $entry['read_back_state'] = $remoteState;
  $entry['verified'] = $remoteState === 'QUEUE';
  if (!$entry['verified']) {
    $allVerified = FALSE;
    $db->update('famtastic_social_record')
      ->fields(['provider_state' => 'verify_failed_' . strtolower($remoteState), 'changed' => $time])
      ->condition('content_id', $entry['content_id'])->execute();
  }
}
unset($entry);

$scheduledCount = count(array_filter($results, static fn(array $e): bool => $e['verified']));
$blockedCount = count(array_filter($results, static fn(array $e): bool => $e['action'] === 'missing_draft_blocked'));
$staleCount = count(array_filter(
  $results,
  static fn(array $e): bool => in_array($e['action'], ['stale_date_blocked', 'undated_draft_blocked'], TRUE)
));

// ---------------------------------------------------------------------------
// Manifest sync (best-effort, mirrors queue-script conventions).
// ---------------------------------------------------------------------------
$manifestSynced = FALSE;
if (!$selftest && is_file($manifestPath) && is_writable($manifestPath) && $scheduledCount > 0) {
  $manifest = json_decode((string) file_get_contents($manifestPath), TRUE);
  if (is_array($manifest)) {
    $nowUtc = gmdate('Y-m-d\TH:i:s\Z');
    foreach ($manifest['records'] ?? [] as &$manifestRecord) {
      foreach ($results as $entry) {
        if (($manifestRecord['content_id'] ?? '') === $entry['content_id'] && $entry['verified']) {
          $manifestRecord['provider_ids']['postiz_scheduled_id'] = (string) $entry['post_id'];
          $manifestRecord['evidence'][] = [
            'kind' => 'postiz_scheduled', 'at' => $nowUtc,
            'postiz_post_id' => (string) $entry['post_id'],
            'note' => 'draft converted to schedule in place by publish-executor; read-back QUEUE verified',
          ];
        }
      }
    }
    unset($manifestRecord);
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT) . "\n");
    $manifestSynced = TRUE;
  }
}

// ---------------------------------------------------------------------------
// SELFTEST teardown: revert to DRAFT (terminates workflow), delete, verify gone
// against a FRESH post-delete listing (never the pre-teardown snapshot).
// ---------------------------------------------------------------------------
$teardown = [];
if ($selftest) {
  foreach ($selftestIds as $sid) {
    $revert = $http('PUT', $baseUrl . '/posts/' . rawurlencode($sid) . '/status', ['status' => 'draft']);
    $delete = $http('DELETE', $baseUrl . '/posts/' . rawurlencode($sid));
    $teardown[$sid] = [
      'revert_http' => $revert['status'] ?? 0,
      'delete_http' => $delete['status'] ?? 0,
      // DELETE returning 404 means already deleted — also acceptable.
      'deleted_ok' => in_array($delete['status'] ?? 0, [200, 204, 404], TRUE),
    ];
  }
  // Post-delete verification window with retries; local instances can be slow.
  $postDeleteAbsent = array_fill_keys($selftestIds, FALSE);
  foreach ([2, 5, 10] as $waitSeconds) {
    sleep($waitSeconds === 2 ? 2 : 3);
    $afterDelete = $http('GET', $listUrl);
    if (($afterDelete['status'] ?? 0) !== 200 || !isset($afterDelete['body']['posts'])) {
      continue;
    }
    $remaining = [];
    foreach ($afterDelete['body']['posts'] as $post) {
      $remaining[(string) ($post['id'] ?? '')] = TRUE;
    }
    foreach (array_keys($postDeleteAbsent) as $sid) {
      $postDeleteAbsent[$sid] = !isset($remaining[$sid]);
    }
    if (!in_array(FALSE, $postDeleteAbsent, TRUE)) {
      break;
    }
  }
  foreach ($selftestIds as $sid) {
    $teardown[$sid]['absent_from_fresh_listing'] = $postDeleteAbsent[$sid];
  }
  foreach ($syntheticRows as $scid => $rowId) {
    $db->delete('famtastic_social_record')->condition('id', $rowId)->execute();
    $db->delete('famtastic_social_record')->condition('content_id', $scid)->execute();
  }
  $residual = (int) $db->select('famtastic_social_record', 'r')
    ->condition('content_id', ['55c-synth-exec-a', '55c-synth-exec-b'], 'IN')
    ->countQuery()->execute()->fetchField();
  $teardown['residual_db_rows'] = $residual;
}

// ---------------------------------------------------------------------------
// Evidence + verdict.
// ---------------------------------------------------------------------------
$checks = [
  'connectivity_ok' => $connectedOk,
  'every_conversion_verified_queue' => $allVerified,
  'no_silent_blocks' => count($results) === count($rows)
    && !in_array('', array_column($results, 'action'), TRUE),
  'selftest_cleaned' => !$selftest || ($teardown['residual_db_rows'] === 0 && !in_array(FALSE, array_column(array_filter($teardown, 'is_array'), 'deleted_ok'), TRUE) && !in_array(FALSE, array_column(array_filter($teardown, 'is_array'), 'absent_from_fresh_listing'), TRUE)),
];
$status = $checks['every_conversion_verified_queue'] && $checks['no_silent_blocks'] && $checks['selftest_cleaned'];

$evidence = [
  'schema' => 'famtastic.publish-executor.v1',
  'status' => $status,
  'run_id' => $runId,
  'mode' => $selftest ? 'selftest' : 'campaign',
  'gates' => [
    'env_gate' => $envGate,
    'owner_flag_gate_present' => $flagGate,
    'owner_flag_gate_required' => FALSE,
    'stale_dates_allowed' => $allowStaleDates,
  ],
  'provider' => ['base_url_host' => $host, 'key_source' => $keySource],
  'batch_limit' => $limit,
  'candidates' => count($rows),
  'scheduled_verified' => $scheduledCount,
  'blocked_missing_draft' => $blockedCount,
  'blocked_stale_or_undated' => $staleCount,
  'read_back_window' => $listWindow,
  'manifest_synced' => $manifestSynced,
  'checks' => $checks,
  'results' => $results,
  'selftest_teardown' => $teardown,
  'duration_seconds' => round(microtime(TRUE) - $scriptStart, 2),
];
$evidencePath = $writeEvidence($evidence);

printf(
  "%s — scheduled+verified=%d blocked_missing=%d blocked_stale=%d candidates=%d checks=%s\nEvidence: %s\n",
  $status ? 'PASS' : 'FAIL', $scheduledCount, $blockedCount, $staleCount, count($rows),
  json_encode($checks), $evidencePath
);
exit($status ? 0 : 1);
