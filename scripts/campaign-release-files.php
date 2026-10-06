<?php
/** Exact-file promotion/rollback primitive, called only by canonical deployment. */
declare(strict_types=1);
require_once __DIR__ . '/stage-campaign-release.php';

function campaignReceipt(string $path): array {
  if (is_link($path) || !is_file($path)) throw new RuntimeException('Receipt missing or symlinked');
  $data = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
  if (!is_array($data)) throw new RuntimeException('Receipt must be an object');
  return $data;
}
function campaignHash(string $path): ?string {
  if (is_link($path)) throw new RuntimeException('Symlink destination rejected');
  if (!file_exists($path)) return NULL;
  if (!is_file($path)) throw new RuntimeException('Non-file destination rejected');
  return hash_file('sha256', $path);
}
function campaignAtomicWrite(string $path, string $bytes): void {
  $dir = dirname($path);
  if (!is_dir($dir) && !mkdir($dir, 0700, TRUE)) throw new RuntimeException('Cannot create destination');
  $temp = tempnam($dir, '.campaign-release-');
  if ($temp === FALSE) throw new RuntimeException('Cannot create temporary sibling');
  try {
    chmod($temp, 0600);
    if (file_put_contents($temp, $bytes) !== strlen($bytes)) throw new RuntimeException('Incomplete write');
    if (!rename($temp, $path)) throw new RuntimeException('Atomic rename failed');
  } finally { if (file_exists($temp)) unlink($temp); }
}
function campaignReleasePath(string $root, string $path): string {
  if ($path !== 'campaign-release-manifest.json' && !preg_match('~\Amarketing/campaigns/[a-z0-9]+(?:-[a-z0-9]+)*/(?:posting-schedule|manifest|scorecard)\.json\z~', $path)) throw new RuntimeException('Non-allowlisted release path');
  return campaignSafePath($root, $path);
}
function campaignLiveRoot(string $root): string {
  if (is_link($root)) throw new RuntimeException('Live root symlink rejected');
  $real = realpath($root);
  if ($real === FALSE || !is_dir($real) || basename($real) === 'web') throw new RuntimeException('Live root must exist outside web document root');
  return $real;
}
function campaignWithLock(string $root, callable $run): mixed {
  $path = campaignSafePath($root, '.campaign-release.lock');
  $handle = fopen($path, 'c');
  if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('Campaign release lock unavailable');
  try { chmod($path, 0600); return $run(); } finally { flock($handle, LOCK_UN); fclose($handle); }
}
function rollbackCampaignFilesUnlocked(string $live, string $backup): void {
  $journal = campaignReceipt($backup . '/journal.json');
  if (($journal['live_root'] ?? NULL) !== $live) throw new RuntimeException('Rollback root mismatch');
  // Preflight all entries: do not partially roll back if any writer intervened.
  foreach ($journal['files'] as $entry) {
    $target = campaignReleasePath($live, $entry['path']);
    $actual = campaignHash($target);
    if ($actual !== $entry['after'] && $actual !== $entry['before']) throw new RuntimeException('Rollback refuses changed live file: ' . $entry['path']);
    if ($entry['before'] !== NULL && campaignHash(campaignReleasePath($backup, $entry['path'])) !== $entry['before']) throw new RuntimeException('Backup hash mismatch');
  }
  foreach (array_reverse($journal['files']) as $entry) {
    $target = campaignReleasePath($live, $entry['path']);
    $actual = campaignHash($target);
    if ($actual === $entry['before']) continue;
    if ($actual !== $entry['after']) throw new RuntimeException('Rollback destination changed');
    if ($entry['before'] === NULL) { if (!unlink($target)) throw new RuntimeException('Rollback unlink failed'); }
    else campaignAtomicWrite($target, (string) file_get_contents(campaignReleasePath($backup, $entry['path'])));
  }
}
function rollbackCampaignFiles(string $live, string $backup): void {
  $live = campaignLiveRoot($live);
  campaignWithLock($live, fn() => rollbackCampaignFilesUnlocked($live, $backup));
}
function promoteCampaignFiles(string $package, string $live, string $backup): array {
  $live = campaignLiveRoot($live);
  if (is_link($package) || !is_dir($package)) throw new RuntimeException('Package directory absent or symlinked');
  $package = realpath($package);
  if (file_exists($backup) || is_link($backup)) throw new RuntimeException('Backup directory must be new');
  return campaignWithLock($live, function () use ($package, $live, $backup): array {
    $manifest = campaignReceipt($package . '/campaign-release-manifest.json');
    if (($manifest['schema'] ?? '') !== 'famtastic.campaign-release.v1' || !preg_match('/\A[a-f0-9]{40}\z/', $manifest['source_commit'] ?? '') || empty($manifest['files'])) throw new RuntimeException('Invalid release manifest');
    $entries = []; $seen = [];
    foreach ($manifest['files'] as $entry) {
      if (isset($seen[$entry['path']]) || $entry['path'] === 'campaign-release-manifest.json') throw new RuntimeException('Duplicate/reserved package path');
      $seen[$entry['path']] = TRUE;
      $source = campaignReleasePath($package, $entry['path']);
      $target = campaignReleasePath($live, $entry['path']);
      if (!preg_match('/\A[a-f0-9]{64}\z/', $entry['sha256'] ?? '') || campaignHash($source) !== $entry['sha256']) throw new RuntimeException('Package hash mismatch');
      if (!array_key_exists('expected_live_sha256', $entry) || campaignHash($target) !== $entry['expected_live_sha256']) throw new RuntimeException('Live compare-and-swap mismatch: ' . $entry['path']);
      $entries[] = ['path' => $entry['path'], 'before' => $entry['expected_live_sha256'], 'after' => $entry['sha256']];
    }
    $receiptPath = 'campaign-release-manifest.json';
    $entries[] = ['path' => $receiptPath, 'before' => campaignHash(campaignReleasePath($live, $receiptPath)), 'after' => campaignHash($package . '/' . $receiptPath)];
    if (!mkdir($backup, 0700, TRUE)) throw new RuntimeException('Cannot create backup');
    foreach ($entries as $entry) if ($entry['before'] !== NULL) {
      $target = campaignReleasePath($live, $entry['path']);
      if (campaignHash($target) !== $entry['before']) throw new RuntimeException('Live file changed during backup');
      campaignAtomicWrite(campaignReleasePath($backup, $entry['path']), (string) file_get_contents($target));
      if (campaignHash(campaignReleasePath($backup, $entry['path'])) !== $entry['before']) throw new RuntimeException('Incomplete backup');
    }
    $journal = ['schema' => 'famtastic.campaign-rollback.v1', 'source_commit' => $manifest['source_commit'], 'live_root' => $live, 'files' => $entries];
    campaignAtomicWrite($backup . '/journal.json', json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    try {
      foreach ($entries as $entry) {
        $target = campaignReleasePath($live, $entry['path']);
        if (campaignHash($target) !== $entry['before']) throw new RuntimeException('Live file changed before promotion');
        $bytes = (string) file_get_contents(campaignReleasePath($package, $entry['path']));
        if (hash('sha256', $bytes) !== $entry['after']) throw new RuntimeException('Package changed before promotion');
        campaignAtomicWrite($target, $bytes);
      }
    } catch (Throwable $error) {
      try { rollbackCampaignFilesUnlocked($live, $backup); }
      catch (Throwable $rollback) { throw new RuntimeException($error->getMessage() . '; rollback requires reconciliation: ' . $rollback->getMessage()); }
      throw $error;
    }
    return $journal;
  });
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  try {
    $args = getopt('', ['action:', 'package:', 'live-root:', 'backup:']);
    foreach (['action', 'live-root', 'backup'] as $key) if (!is_string($args[$key] ?? NULL)) throw new RuntimeException('Required --action promote|rollback --live-root --backup [--package]');
    if ($args['action'] === 'promote' && isset($args['package'])) promoteCampaignFiles($args['package'], $args['live-root'], $args['backup']);
    elseif ($args['action'] === 'rollback') rollbackCampaignFiles($args['live-root'], $args['backup']);
    else throw new RuntimeException('Invalid action/package');
    echo $args['action'] . " complete\n";
  } catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
}
