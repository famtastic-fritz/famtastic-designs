<?php
/** Record an actual post-payment release; never fabricate staging or payment. */
declare(strict_types=1);
$file = getenv('LOCS_RELEASE_RECEIPT') ?: '';
if (!$file || !is_file($file) || (fileperms($file) & 0077)) throw new RuntimeException('Private receipt required');
$receipt = json_decode(file_get_contents($file), TRUE, 32, JSON_THROW_ON_ERROR);
$expected = ['index.html','styles.css','site.js','config.js','assets/archive-private-concept.png'];
$names = array_keys($receipt['files'] ?? []); sort($names); sort($expected);
if ($names !== $expected || !preg_match('/^[a-f0-9]{40}$/D', $receipt['source_sha'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $receipt['package_sha256'] ?? '')) throw new RuntimeException('Invalid exact artifact receipt');
if (($receipt['domain'] ?? '') !== 'tightenupyourlocs.com' || ($receipt['installer_status'] ?? '') !== 'files_installed_unverified') throw new RuntimeException('Wrong provider receipt');
$db = \Drupal::database();
$request = $db->select('famtastic_project_request','r')->fields('r')->condition('id',12)->execute()->fetchAssoc();
$binding = $db->select('famtastic_booking_site_owner','b')->fields('b')->condition('site_key','site-dffd4cb9c3aa47fd')->execute()->fetchAssoc();
$customer = \Drupal::service('famtastic_pipeline.customer_portal')->customerForId(11);
if (!$request || (int)$request['customer_id'] !== 11 || (int)$request['organization_id'] !== 11 || (int)$request['commerce_order_id'] !== 19 || (int)$request['project_id'] !== 5 || $request['status'] !== 'converted' || !$binding || (int)$binding['customer_id'] !== 11 || (int)$binding['organization_id'] !== 11 || $binding['status'] !== 'active' || empty($customer['verified_at'])) throw new RuntimeException('Customer linkage changed');
$project = \Drupal::entityTypeManager()->getStorage('famtastic_project')->load(5);
if (!$project) throw new RuntimeException('Missing project');
if (!$project->get('live_url')->isEmpty() && $project->get('live_url')->value !== 'https://tightenupyourlocs.com/') throw new RuntimeException('Conflicting project live URL');
foreach ($receipt['files'] as $path => $hash) {
  if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Invalid hash');
  $response = \Drupal::httpClient()->get('https://tightenupyourlocs.com/'.$path, ['timeout'=>30, 'allow_redirects'=>FALSE, 'verify'=>TRUE]);
  if ($response->getStatusCode() !== 200 || !hash_equals($hash, hash('sha256',(string)$response->getBody()))) throw new RuntimeException('Public artifact hash mismatch');
}
$result = ['status'=>'public_hashes_and_customer_linkage_verified','project'=>5,'files_verified'=>count($expected),'financial_changes'=>0];
if (getenv('LOCS_RELEASE_ACTION') === 'record-exact-locs') {
  $transaction = $db->startTransaction();
  try {
    $receipt['verification_method'] = 'strict TLS live HTTP SHA256 plus existing customer/request/order/project linkage';
    $receipt['approval_source'] = 'Fritz explicit approved Locs design and launch in task 019ff226-b4a2-7450-a62c-5ca1ee0c9868; recorded now, not a fabricated customer portal click';
    \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('deployment.external_verified:project:5:'.$receipt['package_sha256'], 'deployment.external_verified', $receipt, campaignId:50, orderId:19, projectId:5, provider:'cpanel', providerEventId:$receipt['package_sha256']);
    $project->set('live_url','https://tightenupyourlocs.com/')->set('delivery_status','launched')->set('release_sha',$receipt['source_sha'])->set('artifact_checksum',$receipt['package_sha256']);
    $project->save();
    $result['status'] = 'post_payment_external_release_recorded';
  } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
  unset($transaction);
}
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
