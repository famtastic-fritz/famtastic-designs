<?php
/** Local packaging primitive for the canonical backend deployer; never deploys. */
declare(strict_types=1);

function campaignGit(string $repo, array $args): string {
  $process = proc_open(array_merge(['git', '-C', $repo], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  if (!is_resource($process)) throw new RuntimeException('Cannot start git');
  $out = stream_get_contents($pipes[1]);
  $error = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]);
  if (proc_close($process) !== 0) throw new RuntimeException('Git validation failed: ' . trim($error));
  return $out;
}

function campaignSafePath(string $root, string $relative): string {
  $path = $root;
  foreach (explode('/', $relative) as $part) {
    if ($part === '' || $part === '.' || $part === '..') throw new RuntimeException('Unsafe path');
    $path .= '/' . $part;
    if (is_link($path)) throw new RuntimeException('Symlink rejected: ' . $relative);
  }
  return $path;
}

function stageCampaignRelease(string $repo, string $commit, string $output, ?string $liveRoot = NULL): array {
  if (!preg_match('/\A[a-f0-9]{40}\z/', $commit)) throw new RuntimeException('Full 40-character source commit required');
  $repo = realpath($repo) ?: throw new RuntimeException('Repository absent');
  if (trim(campaignGit($repo, ['rev-parse', '--verify', $commit . '^{commit}'])) !== $commit) throw new RuntimeException('Source commit mismatch');
  if (trim(campaignGit($repo, ['rev-parse', 'HEAD'])) !== $commit) throw new RuntimeException('Checkout must match exact source commit');
  $output = rtrim($output, '/');
  if ($output === '' || file_exists($output) || is_link($output)) throw new RuntimeException('Output must be a new directory');
  $parent = realpath(dirname($output));
  if ($parent === FALSE || !is_dir($parent)) throw new RuntimeException('Output parent must exist');
  $output = $parent . '/' . basename($output);
  $prior = [];
  if ($liveRoot !== NULL) {
    if (is_link($liveRoot)) throw new RuntimeException('Live root cannot be a symlink');
    $liveRoot = realpath($liveRoot) ?: throw new RuntimeException('Live root absent');
    if (basename($liveRoot) === 'web') throw new RuntimeException('Live root must be outside web document root');
    $receipt = campaignSafePath($liveRoot, 'campaign-release-manifest.json');
    if (is_file($receipt)) {
      $decoded = json_decode((string) file_get_contents($receipt), TRUE, 512, JSON_THROW_ON_ERROR);
      foreach ($decoded['files'] ?? [] as $entry) $prior[$entry['path']] = $entry['sha256'];
    }
  }
  $allowed = '~\Amarketing/campaigns/([a-z0-9]+(?:-[a-z0-9]+)*)/(posting-schedule|manifest|scorecard)\.json\z~';
  $candidate = '~\Amarketing/campaigns/[^/]+/(posting-schedule|manifest|scorecard)\.json\z~';
  // Include ignored files: an allowlisted local draft must not silently enter a release.
  $untracked = campaignGit($repo, ['ls-files', '--others', '-z', '--', 'marketing/campaigns']);
  foreach (explode("\0", $untracked) as $path) if (preg_match($candidate, $path)) throw new RuntimeException('Untracked release candidate rejected: ' . $path);
  $files = []; $blobs = [];
  foreach (explode("\0", campaignGit($repo, ['ls-tree', '-rz', '--full-tree', $commit, '--', 'marketing/campaigns'])) as $row) {
    if ($row === '') continue;
    [$meta, $path] = explode("\t", $row, 2);
    if (!preg_match($candidate, $path)) continue;
    if (!preg_match($allowed, $path)) throw new RuntimeException('Invalid campaign slug: ' . $path);
    [$mode, $type, $oid] = explode(' ', $meta);
    if ($type !== 'blob' || !in_array($mode, ['100644', '100755'], TRUE)) throw new RuntimeException('Non-regular Git file rejected: ' . $path);
    $local = campaignSafePath($repo, $path);
    $bytes = campaignGit($repo, ['cat-file', 'blob', $oid]);
    if (!is_file($local) || hash_file('sha256', $local) !== hash('sha256', $bytes)) throw new RuntimeException('Dirty or missing release source: ' . $path);
    $decoded = json_decode($bytes, TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Campaign JSON must be an object or array: ' . $path);
    $hash = hash('sha256', $bytes);
    $entry = ['path' => $path, 'sha256' => $hash, 'bytes' => strlen($bytes), 'git_blob' => $oid, 'expected_live_sha256' => NULL];
    if ($liveRoot !== NULL) {
      $live = campaignSafePath($liveRoot, $path);
      if (file_exists($live)) {
        if (!is_file($live)) throw new RuntimeException('Non-file live target: ' . $path);
        $actual = hash_file('sha256', $live);
        if ($actual !== $hash && ($prior[$path] ?? NULL) !== $actual) throw new RuntimeException('Live file changed outside recorded release; reconcile: ' . $path);
        $entry['expected_live_sha256'] = $actual;
      }
    }
    $files[] = $entry; $blobs[$path] = $bytes;
  }
  if (!$files) throw new RuntimeException('No eligible campaign JSON found');
  $manifest = ['schema' => 'famtastic.campaign-release.v1', 'source_commit' => $commit, 'files' => $files];
  // Validate the entire package before creating output. The manifest is written last.
  if (!mkdir($output, 0700)) throw new RuntimeException('Cannot create output');
  foreach ($blobs as $path => $bytes) {
    $dest = $output . '/' . $path;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0700, TRUE)) throw new RuntimeException('Cannot create package directory');
    if (file_put_contents($dest, $bytes) !== strlen($bytes)) throw new RuntimeException('Incomplete package write');
    chmod($dest, 0600);
  }
  $receipt = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
  if (file_put_contents($output . '/campaign-release-manifest.json', $receipt) !== strlen($receipt)) throw new RuntimeException('Incomplete receipt write');
  chmod($output . '/campaign-release-manifest.json', 0600);
  return $manifest;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  try {
    $args = getopt('', ['repo:', 'commit:', 'output:', 'live-root:']);
    foreach (['repo', 'commit', 'output'] as $key) if (!isset($args[$key]) || !is_string($args[$key])) throw new RuntimeException('Required: --repo --commit --output [--live-root]');
    $manifest = stageCampaignRelease($args['repo'], $args['commit'], $args['output'], $args['live-root'] ?? NULL);
    echo json_encode(['source_commit' => $manifest['source_commit'], 'files' => count($manifest['files']), 'status' => 'staged_only'], JSON_THROW_ON_ERROR) . "\n";
  }
  catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
}
