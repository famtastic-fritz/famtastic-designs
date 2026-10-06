<?php
declare(strict_types=1);
require __DIR__ . '/campaign-release-files.php';
$base = sys_get_temp_dir() . '/campaign-release-test-' . bin2hex(random_bytes(6));
mkdir($base, 0700);
$checks = 0;
function expect(bool $condition, string $label): void { global $checks; if (!$condition) throw new RuntimeException($label); $checks++; }
function rejects(callable $run, string $match): void {
  try { $run(); } catch (Throwable $error) { expect(str_contains($error->getMessage(), $match), 'Unexpected rejection: ' . $error->getMessage()); return; }
  throw new RuntimeException('Expected rejection: ' . $match);
}
function fixture(string $name): array {
  global $base;
  $repo = "$base/$name"; mkdir($repo); campaignGit($repo, ['init', '-q']);
  campaignGit($repo, ['config', 'user.name', 'Release fixture']); campaignGit($repo, ['config', 'user.email', 'fixture@example.invalid']);
  mkdir("$repo/marketing/campaigns/valid-campaign", 0700, TRUE);
  file_put_contents("$repo/marketing/campaigns/valid-campaign/posting-schedule.json", '{"drops":[]}');
  file_put_contents("$repo/marketing/campaigns/valid-campaign/manifest.json", '{"name":"Fixture"}');
  file_put_contents("$repo/marketing/campaigns/valid-campaign/.env", 'DO_NOT_SHIP=1');
  campaignGit($repo, ['add', '.']); campaignGit($repo, ['commit', '-qm', 'fixture']);
  return [$repo, trim(campaignGit($repo, ['rev-parse', 'HEAD']))];
}
function recommit(string $repo): string { campaignGit($repo, ['add', '-A']); campaignGit($repo, ['commit', '-qm', 'fixture mutation']); return trim(campaignGit($repo, ['rev-parse', 'HEAD'])); }
function removeTree(string $dir): void {
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
    if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
  }
  rmdir($dir);
}
try {
  [$repo, $sha] = fixture('positive');
  $manifest = stageCampaignRelease($repo, $sha, "$base/package");
  expect(count($manifest['files']) === 2 && $manifest['source_commit'] === $sha, 'Exact commit and allowlist');
  expect(!file_exists("$base/package/marketing/campaigns/valid-campaign/.env"), 'Secret excluded');
  foreach ($manifest['files'] as $entry) expect(hash_file('sha256', "$base/package/{$entry['path']}") === $entry['sha256'], 'Exact bytes hashed');
  campaignGit($base, ['clone', '--bare', $repo, "$base/mirror.git"]);
  $bareManifest = stageCampaignRelease("$base/mirror.git", $sha, "$base/bare-package");
  expect($bareManifest['source_commit'] === $sha && $bareManifest['files'] === $manifest['files'], 'Bare mirror yields identical exact source package without checkout');
  expect(!is_dir("$base/mirror.git/marketing"), 'Mirror has no campaign working tree');
  $archive = "$base/backend-source"; mkdir($archive); mkdir("$archive/scripts");
  copy(__DIR__ . '/stage-campaign-release.php', "$archive/scripts/stage-campaign-release.php");
  expect(!is_dir("$archive/.git") && !is_dir("$archive/marketing"), 'Backend archive needs neither Git metadata nor marketing checkout');
  $process = proc_open([PHP_BINARY, "$archive/scripts/stage-campaign-release.php", '--repo', "$base/mirror.git", '--commit', $sha, '--output', "$base/archive-package"], [1=>['pipe','w'],2=>['pipe','w']],$pipes);
  $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  expect(proc_close($process) === 0, 'Canonical archive helper with bare mirror: '.$err);
  expect(hash_file('sha256', "$base/archive-package/marketing/campaigns/valid-campaign/manifest.json") === hash_file('sha256', "$base/package/marketing/campaigns/valid-campaign/manifest.json"), 'Archived helper produces exact campaign bytes');
  rejects(fn() => stageCampaignRelease($repo, '../HEAD', "$base/reject"), 'Full 40-character');
  rejects(fn() => stageCampaignRelease($repo, substr($sha, 0, 8), "$base/reject"), 'Full 40-character');
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/package"), 'new directory');
  file_put_contents("$repo/marketing/campaigns/valid-campaign/scorecard.json", '{}');
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject"), 'Untracked');
  unlink("$repo/marketing/campaigns/valid-campaign/scorecard.json");
  file_put_contents("$repo/marketing/campaigns/valid-campaign/posting-schedule.json", '{}');
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject"), 'Dirty');
  [$repo, $sha] = fixture('invalid-json');
  file_put_contents("$repo/marketing/campaigns/valid-campaign/manifest.json", '{broken'); $sha = recommit($repo);
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject"), 'Syntax error');
  [$repo, $sha] = fixture('symlink');
  unlink("$repo/marketing/campaigns/valid-campaign/manifest.json"); symlink('posting-schedule.json', "$repo/marketing/campaigns/valid-campaign/manifest.json"); $sha = recommit($repo);
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject"), 'Non-regular');
  [$repo, $sha] = fixture('slug');
  rename("$repo/marketing/campaigns/valid-campaign", "$repo/marketing/campaigns/..escape"); $sha = recommit($repo);
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject"), 'Invalid campaign slug');
  [$repo, $sha] = fixture('live');
  $live = "$base/live-target"; mkdir($live); mkdir("$live/marketing/campaigns/valid-campaign", 0700, TRUE);
  $target = "$live/marketing/campaigns/valid-campaign/posting-schedule.json";
  file_put_contents($target, '{"drops":["live-provider-id"]}');
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject", $live), 'Live file changed');
  $prior = ['files' => [['path' => 'marketing/campaigns/valid-campaign/posting-schedule.json', 'sha256' => hash_file('sha256', $target)]]];
  file_put_contents("$live/campaign-release-manifest.json", json_encode($prior));
  $result = stageCampaignRelease($repo, $sha, "$base/live-package", $live);
  $entry = array_values(array_filter($result['files'], fn($file) => str_ends_with($file['path'], 'posting-schedule.json')))[0];
  expect($entry['expected_live_sha256'] === hash_file('sha256', $target), 'Expected old hash retained for promotion CAS');
  file_put_contents($target, '{"new":"provider-write"}');
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject", $live), 'Live file changed');
  unlink($target); symlink('/tmp', $target);
  rejects(fn() => stageCampaignRelease($repo, $sha, "$base/reject", $live), 'Symlink');
  expect(!file_exists("$base/reject"), 'Failed validation leaves no package');
  [$repo, $sha] = fixture('promotion');
  $live = "$base/promote-live"; mkdir($live);
  mkdir("$live/marketing/campaigns/live-only", 0700, TRUE);
  file_put_contents("$live/marketing/campaigns/live-only/posting-schedule.json", '{"keep":true}');
  stageCampaignRelease($repo, $sha, "$base/promote-package", $live);
  promoteCampaignFiles("$base/promote-package", $live, "$base/backup-one");
  expect(file_get_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json") === '{"drops":[]}', 'First install exact bytes');
  expect(is_file("$live/marketing/campaigns/live-only/posting-schedule.json"), 'Unrelated live file preserved');
  rollbackCampaignFiles($live, "$base/backup-one");
  expect(!file_exists("$live/marketing/campaigns/valid-campaign/posting-schedule.json") && !file_exists("$live/campaign-release-manifest.json"), 'Rollback removes initially absent exact files');
  expect(is_file("$live/marketing/campaigns/live-only/posting-schedule.json"), 'Rollback preserves unrelated live');
  promoteCampaignFiles("$base/promote-package", $live, "$base/backup-two");
  $oldReceipt = file_get_contents("$live/campaign-release-manifest.json");
  file_put_contents("$repo/marketing/campaigns/valid-campaign/posting-schedule.json", '{"drops":["new"]}'); $sha = recommit($repo);
  stageCampaignRelease($repo, $sha, "$base/update-package", $live);
  promoteCampaignFiles("$base/update-package", $live, "$base/backup-three");
  expect(file_get_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json") === '{"drops":["new"]}', 'Recorded old hash permits update');
  file_put_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json", '{"external":"writer"}');
  rejects(fn() => rollbackCampaignFiles($live, "$base/backup-three"), 'Rollback refuses changed live');
  expect(file_get_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json") === '{"external":"writer"}', 'Rollback preserves external write');
  file_put_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json", '{"drops":["new"]}');
  rollbackCampaignFiles($live, "$base/backup-three");
  expect(file_get_contents("$live/campaign-release-manifest.json") === $oldReceipt, 'Previous receipt restored');
  expect(file_get_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json") === '{"drops":[]}', 'Previous file restored');
  stageCampaignRelease($repo, $sha, "$base/cas-package", $live);
  file_put_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json", '{"external":"late"}');
  rejects(fn() => promoteCampaignFiles("$base/cas-package", $live, "$base/backup-cas"), 'compare-and-swap mismatch');
  expect(!file_exists("$base/backup-cas"), 'CAS failure before backup or promotion');
  file_put_contents("$live/marketing/campaigns/valid-campaign/posting-schedule.json", '{"drops":[]}');
  file_put_contents("$base/cas-package/marketing/campaigns/valid-campaign/manifest.json", '{}');
  rejects(fn() => promoteCampaignFiles("$base/cas-package", $live, "$base/backup-tamper"), 'Package hash mismatch');
  echo "PASS: $checks campaign release checks; fixtures only, no network or live writes.\n";
} finally { removeTree($base); }
