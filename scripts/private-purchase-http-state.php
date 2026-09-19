<?php
declare(strict_types=1);
// CLI-only fixture operations. No HTTP control endpoint or production fallback.
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService as Purchase;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService as Offline;
$sandbox = realpath(getenv('SELECTED_DRUPAL_SANDBOX') ?: '') ?: '';
$db = \Drupal::database(); $options = $db->getConnectionOptions();
if (PHP_SAPI !== 'cli' || !preg_match('#/famtastic-selected-drupal\.[A-Za-z0-9]{6}$#', $sandbox)
  || realpath(__DIR__) !== $sandbox . '/scripts' || realpath(\Drupal::root()) !== $sandbox . '/backend/web'
  || $options['driver'] !== 'sqlite' || realpath($options['database']) !== $sandbox . '/backend/web/sites/default/files/.ht.sqlite'
  || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory' || ini_get('allow_url_fopen') !== '0') throw new RuntimeException('Unsafe HTTP fixture state operation.');
$fixture = json_decode(file_get_contents($sandbox . '/private-http-fixture.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$action = getenv('PRIVATE_HTTP_ACTION') ?: 'snapshot';
$storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
$offer = $db->select('famtastic_private_offer', 'p')->fields('p')->condition('website_request_id', 16)->execute()->fetchAssoc();
switch ($action) {
  case 'scope-change': $db->update('famtastic_project_request')->fields(['intake_data' => '{"page_count":20}'])->condition('id', 16)->execute(); break;
  case 'scope-restore': $db->update('famtastic_project_request')->fields(['intake_data' => $fixture['original_intake']])->condition('id', 16)->execute(); break;
  case 'gateway-disable':
    if (empty($offer['commerce_order_id'])) throw new RuntimeException('Missing synthetic reunion order');
    $storage->loadUnchanged($offer['commerce_order_id'])->set('payment_gateway', 'private_fixture_stripe')->save();
    \Drupal\commerce_payment\Entity\PaymentGateway::load('private_fixture_stripe')->disable()->save(); break;
  case 'gateway-clear':
    if (empty($offer['commerce_order_id'])) throw new RuntimeException('Missing synthetic reunion order');
    $storage->loadUnchanged($offer['commerce_order_id'])->set('payment_gateway', NULL)->save();
    \Drupal\commerce_payment\Entity\PaymentGateway::load('private_fixture_stripe')->enable()->save(); break;
  case 'checkout-off': unlink($sandbox . '/private-http-checkout-enabled'); break;
  case 'checkout-on': touch($sandbox . '/private-http-checkout-enabled'); break;
  case 'snapshot': break;
  default: throw new RuntimeException('Unknown fixture action');
}
$counts = [];
foreach (['commerce_order', 'commerce_order_item', 'commerce_payment', 'famtastic_job', 'famtastic_notification_outbox', 'famtastic_commerce_fulfillment', 'famtastic_entitlement'] as $table) {
  $counts[$table] = (int) $db->select($table, 'x')->countQuery()->execute()->fetchField();
}
$stock = $storage->loadUnchanged($fixture['stock_order']);
$receipt = (new Offline())->receipt($stock);
$orderValidation = NULL;
if (!empty($offer['commerce_order_id'])) {
  try { (new Purchase())->assertReunionOrder($storage->loadUnchanged($offer['commerce_order_id'])); $orderValidation = 'valid'; }
  catch (Throwable $error) { $orderValidation = $error->getMessage(); }
}
print json_encode(['counts' => $counts, 'stock_order' => (int) $stock->id(), 'received' => $receipt['received'], 'outstanding' => $receipt['outstanding'],
  'completion' => $stock->getData(Offline::KEY)['completion']['state'], 'launch_authorized' => $receipt['launch_authorized'],
  'reunion_order' => (int) ($offer['commerce_order_id'] ?? 0), 'reunion_validation' => $orderValidation], JSON_THROW_ON_ERROR) . "\n";
