<?php
/** Exact disposable-runtime release source/rollback lookup proof. */
use Drupal\famtastic_pipeline\Utility\CampaignFileLocator;
if (\Drupal::database()->driver() !== 'sqlite') throw new RuntimeException('Disposable SQLite only.');
$module = DRUPAL_ROOT . '/modules/custom/famtastic_pipeline';
$root = dirname(DRUPAL_ROOT);
$sha = str_repeat('a', 40); $other = str_repeat('b', 40);
foreach ([$sha => 'new', $other => 'old'] as $commit => $value) {
 $dir = $root . '/campaign-releases/' . $commit . '/marketing/campaigns/release-fixture';
 @mkdir($dir, 0700, TRUE); file_put_contents($dir . '/posting-schedule.json', json_encode(['drops' => [], 'proof' => $value]));
}
@mkdir($root . '/marketing/campaigns/release-fixture', 0700, TRUE);
$mutable = $root . '/marketing/campaigns/release-fixture/posting-schedule.json';
file_put_contents($mutable, '{"proof":"mutable-writer"}');
try {
 foreach ([$sha => 'new', $other => 'old'] as $commit => $value) {
  file_put_contents($module . '/campaign-source.json', json_encode(['source_commit' => $commit, 'synced_at' => 'fixture']));
  if (CampaignFileLocator::readJson('release-fixture', 'posting-schedule.json')['proof'] !== $value) throw new RuntimeException('Snapshot lookup/rollback failed');
  if (!in_array('release-fixture', CampaignFileLocator::listCampaignSlugs(), TRUE)) throw new RuntimeException('Discovery missed snapshot');
 }
 if (file_get_contents($mutable) !== '{"proof":"mutable-writer"}') throw new RuntimeException('Mutable writer overwritten');
 print "PASS snapshot priority, discovery, code-marker rollback, mutable writer unchanged\n";
} finally { unlink($module . '/campaign-source.json'); }
