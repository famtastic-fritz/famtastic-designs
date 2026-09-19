<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Form\PrivatePurchaseForm;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once dirname(__DIR__, 3) . '/src/Service/OfflinePrepaymentService.php';
require_once dirname(__DIR__, 3) . '/src/Service/PrivatePurchaseService.php';
require_once dirname(__DIR__, 3) . '/src/Form/PrivatePurchaseForm.php';
require_once dirname(__DIR__, 3) . '/src/EventSubscriber/PrivateScopeCheckoutGuard.php';

/** SQLite binding tests; native entities mocked, provider never contacted. */
final class PrivatePurchaseServiceTest extends UnitTestCase {
  private Connection $db;
  private PrivatePurchaseService $service;
  private UserInterface $owner;
  private array $orders = [];
  private array $metadata = [];
  private array $scope;
  private array $input;
  private ContainerBuilder $container;
  private int $created = 0;

  protected function setUp(): void {
    parent::setUp();
    new Settings(['famtastic_payment_mode' => 'test', 'famtastic_private_reunion_checkout_enabled' => TRUE]);
    $options = ['database' => ':memory:', 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'driver' => 'sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $definitions = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema();
    foreach (['famtastic_customer', 'famtastic_membership', 'famtastic_project_request', 'famtastic_private_offer', 'famtastic_event'] as $table) $this->db->schema()->createTable($table, $definitions[$table]);
    $this->owner = $this->account(14, 'mbshclassof2000@gmail.com');
    $this->db->insert('famtastic_customer')->fields(['id' => 14, 'public_id' => 'fixture-customer', 'uid' => 14, 'display_name' => 'Isolated fixture', 'email' => $this->owner->getEmail(), 'verified_at' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 14, 'organization_id' => 14, 'role' => 'owner', 'status' => 'active'])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 16, 'public_id' => PrivatePurchaseService::REUNION, 'customer_id' => 14, 'organization_id' => 14, 'project_name' => 'Fixture only', 'intake_data' => '{}', 'status' => 'submitted', 'proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'proof_campaign_id' => 55])->execute();
    $offerId = OfflinePrepaymentService::offerId('reunion-private-scope:' . PrivatePurchaseService::REUNION);
    $this->db->insert('famtastic_private_offer')->fields(['id' => 1, 'public_id' => $offerId, 'website_request_id' => 16, 'customer_id' => 14, 'organization_id' => 14, 'sku' => 'PRIVATE-REUNION16-199', 'list_amount_minor' => 19900, 'offered_amount_minor' => 19900, 'created_by_uid' => 0])->execute();
    $this->scope = json_decode(file_get_contents(dirname(__DIR__, 2) . '/fixtures/reunion-private-scope.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->db->insert('famtastic_event')->fields(['event_key' => 'private-scope:request:16:class-of-2000-v1', 'event_type' => 'commerce.private_scope_offered', 'payload' => json_encode(['request_id' => 16, 'customer_id' => 14, 'organization_id' => 14, 'scope' => $this->scope, 'scope_hash' => PrivatePurchaseService::REUNION_HASH, 'offer_public_id' => $offerId, 'authority' => 'Fritz Medine explicit approval'])])->execute();
    $this->input = ['accept_terms' => TRUE, 'terms_version' => $this->scope['version'], 'scope_hash' => PrivatePurchaseService::REUNION_HASH, 'domain_choice' => 'undecided'];
    $this->container = new ContainerBuilder();
    $this->container->set('database', $this->db);
    $this->container->set('current_user', $this->owner);
    $this->container->set('string_translation', $this->getStringTranslationStub());
    $stack = new RequestStack(); $stack->push(Request::create('https://local.example.test/web/customer/private-purchase/' . PrivatePurchaseService::REUNION));
    $this->container->set('request_stack', $stack);
    $this->container->set('famtastic_pipeline.customer_portal', new class { public function claimResource(...$args): void {} });
    $this->container->set('famtastic_pipeline.operational_ledger', new class { public function recordEvent(...$args): void {} });
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $orderStorage = $this->createMock(EntityStorageInterface::class);
    $orderStorage->method('loadUnchanged')->willReturnCallback(fn($id) => $this->orders[$id] ?? NULL);
    $orderStorage->expects(self::never())->method('load');
    $query = $this->createMock(\Drupal\Core\Entity\Query\QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf(); $query->method('condition')->willReturnSelf();
    $query->method('execute')->willReturnCallback(fn() => array_keys($this->orders));
    $orderStorage->method('getQuery')->willReturn($query);
    $orderStorage->method('create')->willReturnCallback(function (array $values) {
      self::assertSame(14, $values['uid']); self::assertSame('draft', $values['state']);
      self::assertFalse($values['cart']);
      $id = 100 + ++$this->created;
      $order = $this->createMock(OrderInterface::class);
      $order->method('id')->willReturn($id);
      $order->method('getCustomerId')->willReturn(14);
      $order->method('getCustomer')->willReturn($this->owner);
      $order->method('getItems')->willReturn($values['order_items']);
      $order->method('getAdjustments')->willReturn([]);
      $order->method('getTotalPrice')->willReturn(new Price('199.00', 'USD'));
      $order->method('getState')->willReturn((object) ['value' => 'draft']);
      $order->method('isPaid')->willReturn(FALSE);
      $order->method('getData')->willReturnCallback(fn($key, $default = NULL) => $this->metadata[$id][$key] ?? $default);
      $order->method('setData')->willReturnCallback(function ($key, $value) use ($order, $id) { $this->metadata[$id][$key] = $value; return $order; });
      $order->method('save')->willReturnCallback(function () use ($order, $id) { $this->orders[$id] = $order; return 1; });
      return $order;
    });
    $itemStorage = $this->createMock(EntityStorageInterface::class);
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('getUnitPrice')->willReturn(new Price('199.00', 'USD'));
    $item->method('getQuantity')->willReturn('1'); $item->method('getAdjustments')->willReturn([]);
    $item->expects(self::atMost(1))->method('setUnitPrice')->with(new Price('199.00', 'USD'), TRUE);
    $itemStorage->method('create')->willReturn($item);
    $gatewayStorage = $this->createMock(EntityStorageInterface::class);
    $gateway = $this->createMock(PaymentGatewayInterface::class); $gateway->method('getPluginId')->willReturn('stripe');
    $gateway->expects(self::never())->method('getPlugin');
    $gatewayStorage->method('loadByProperties')->willReturn([$gateway]);
    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $store = $this->createMock(StoreInterface::class); $store->method('getDefaultCurrencyCode')->willReturn('USD'); $store->method('isPublished')->willReturn(TRUE);
    $storeStorage->method('load')->willReturn($store);
    $manager->method('getStorage')->willReturnCallback(fn($type) => match ($type) {
      'commerce_order' => $orderStorage, 'commerce_order_item' => $itemStorage,
      'commerce_payment_gateway' => $gatewayStorage, 'commerce_store' => $storeStorage,
      default => throw new \LogicException('Unexpected storage or payment creation: ' . $type),
    });
    $this->container->set('entity_type.manager', $manager);
    \Drupal::setContainer($this->container);
    $this->service = new PrivatePurchaseService();
  }

  private function account(int $id, string $email): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn($id); $account->method('getEmail')->willReturn($email); $account->method('isAuthenticated')->willReturn($id > 0);
    return $account;
  }

  public function testReadOnlyFormUsesExactScopeAndNoRenewalOrPaymentMutation(): void {
    $context = $this->service->context($this->owner, PrivatePurchaseService::REUNION);
    self::assertSame(PrivatePurchaseService::REUNION_HASH, hash('sha256', json_encode($this->scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    self::assertNull($context['order']); self::assertSame(0, $this->created);
    $form = (new PrivatePurchaseForm())->buildForm([], new FormState(), PrivatePurchaseService::REUNION);
    self::assertSame(0, $form['#cache']['max-age']);
    self::assertStringContainsString('$199', (string) $form['status']['#value']);
    self::assertStringContainsString('No recurring', (string) $form['scope']['policy']['#value']);
    self::assertArrayHasKey('submit', $form['actions']);
    self::assertSame(0, $this->created);
  }

  public function testCustomerPostAndReplayCreateOneUnpaidOrderWithoutBlockingStaging(): void {
    $first = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $replay = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    self::assertSame($first->id(), $replay->id()); self::assertSame(1, $this->created);
    self::assertFalse($first->isPaid());
    $data = $first->getData(PrivatePurchaseService::KEY);
    self::assertNull($data['client_acceptance']); self::assertFalse($data['launch_authorized']); self::assertFalse($data['recurring_authorized']);
    self::assertNull($first->getData('famtastic_checkout'));
    $request = $this->db->select('famtastic_project_request', 'r')->fields('r')->execute()->fetchAssoc();
    self::assertNull($request['commerce_order_id']); self::assertSame('submitted', $request['status']); self::assertSame('not_started', $request['staging_review_status']);
    self::assertSame('checkout_started', $this->db->select('famtastic_private_offer', 'o')->fields('o', ['status'])->execute()->fetchField());
  }

  public function testCrossAccountGetAndPostAndStockCheckoutFailClosed(): void {
    foreach ([$this->account(0, ''), $this->account(15, 'sprospere@yahoo.com'), $this->account(14, 'other@example.test')] as $account) {
      foreach (['context', 'startReunion'] as $method) {
        try { $this->service->$method($account, PrivatePurchaseService::REUNION, ...($method === 'startReunion' ? [$this->input] : [])); self::fail('Cross-account access accepted'); }
        catch (\RuntimeException) { self::assertSame(0, $this->created); }
      }
    }
    $this->expectException(\RuntimeException::class);
    $this->service->startReunion($this->owner, PrivatePurchaseService::STOCK, $this->input);
  }

  public function testNoSelectionNoCheckoutOrActionAndFeatureGateIsFailClosed(): void {
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'customer_ready', 'selected_proof_direction' => ''])->execute();
    $form = (new PrivatePurchaseForm())->buildForm([], new FormState(), PrivatePurchaseService::REUNION);
    self::assertArrayNotHasKey('actions', $form);
    try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); self::fail('Unselected checkout'); }
    catch (\RuntimeException $e) { self::assertSame('private_scope_selection_required', $e->getMessage()); }
    foreach ([[], ['famtastic_private_reunion_checkout_enabled' => TRUE, 'famtastic_payment_mode' => 'disabled']] as $settings) {
      new Settings($settings); self::assertFalse(PrivatePurchaseService::checkoutEnabled());
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); self::fail('Disabled checkout'); }
      catch (\RuntimeException $e) { self::assertSame('private_checkout_unavailable', $e->getMessage()); }
    }
    self::assertSame(0, $this->created);
  }

  public function testChangedScopeRenewalUnsafeDomainAndStaleSelectionAreRejected(): void {
    foreach ([['scope_hash' => 'wrong'], ['accept_terms' => FALSE], ['recurring_authorized' => TRUE], ['domain' => 'https://bad.test/path'], ['terms_version' => 'legacy']] as $bad) {
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, array_replace($this->input, $bad)); self::fail('Unsafe checkout'); }
      catch (\RuntimeException) { self::assertSame(0, $this->created); }
    }
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $this->db->update('famtastic_project_request')->fields(['selected_proof_direction' => 'b'])->execute();
    try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); self::fail('Selection silently changed'); }
    catch (\RuntimeException $e) { self::assertSame('private_order_reconciliation_required', $e->getMessage()); }
    self::assertSame(1, $this->created);
    self::assertSame('a', $order->getData(PrivatePurchaseService::KEY)['selection']['direction']);
  }

  public function testRevokedMembershipAndChangedOfferCannotExposeScope(): void {
    $this->db->update('famtastic_membership')->fields(['status' => 'revoked'])->execute();
    try { $this->service->context($this->owner, PrivatePurchaseService::REUNION); self::fail('Revoked membership allowed'); }
    catch (\RuntimeException $e) { self::assertSame('private_purchase_not_found', $e->getMessage()); }
    $this->db->update('famtastic_membership')->fields(['status' => 'active'])->execute();
    foreach ([['status' => 'revoked'], ['offered_amount_minor' => 100], ['sku' => 'FAM-CUSTOM-1999']] as $bad) {
      $this->db->update('famtastic_private_offer')->fields($bad)->execute();
      try { $this->service->context($this->owner, PrivatePurchaseService::REUNION); self::fail('Changed offer exposed'); }
      catch (\RuntimeException $e) { self::assertSame('private_scope_changed', $e->getMessage()); }
      $this->db->update('famtastic_private_offer')->fields(['status' => 'active', 'offered_amount_minor' => 19900, 'sku' => 'PRIVATE-REUNION16-199'])->execute();
    }
    self::assertSame(0, $this->created);
  }

  public function testNativeCheckoutFiltersManualAndDisabledGatewaysAndRechecksSelection(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $guard = new \Drupal\famtastic_pipeline\EventSubscriber\PrivateScopeCheckoutGuard();
    $gateways = [];
    foreach (['stripe' => TRUE, 'manual' => TRUE, 'stripe_payment_element' => FALSE] as $plugin => $enabled) {
      $gateway = $this->createMock(PaymentGatewayInterface::class);
      $gateway->method('status')->willReturn($enabled); $gateway->method('getPluginId')->willReturn($plugin);
      $gateway->expects(self::never())->method('getPlugin');
      $gateways[$plugin] = $gateway;
    }
    $event = new \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent($gateways, $order);
    $guard->gateways($event);
    self::assertSame(['stripe'], array_keys($event->getPaymentGateways()));
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'revision_requested'])->execute();
    $event = new \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent($gateways, $order);
    $guard->gateways($event);
    self::assertSame([], $event->getPaymentGateways());
  }

  public function testNativeCheckoutRequestRejectsAnotherAccountBeforeProviderInitialization(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $guard = new \Drupal\famtastic_pipeline\EventSubscriber\PrivateScopeCheckoutGuard();
    $kernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);
    $event = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, Request::create('/checkout/' . $order->id()), 1);
    $guard->request($event);
    self::assertFalse($event->hasResponse());
    $this->container->set('current_user', $this->account(15, 'sprospere@yahoo.com'));
    $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
    $guard->request($event);
  }

  public function testPrepaidFormStatesNeverCreateAnotherOrderOrShowAPayButton(): void {
    $definitions = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema();
    $this->db->schema()->createTable('famtastic_notification_outbox', _famtastic_pipeline_automation_schema()['famtastic_notification_outbox']);
    $this->db->schema()->createTable('famtastic_commerce_fulfillment', $definitions['famtastic_commerce_fulfillment']);
    $stock = $this->account(15, 'sprospere@yahoo.com');
    $this->container->set('current_user', $stock);
    $this->db->insert('famtastic_customer')->fields(['id' => 15, 'public_id' => 'fixture-stock', 'uid' => 15, 'display_name' => 'Fixture', 'email' => $stock->getEmail(), 'verified_at' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 15, 'organization_id' => 15, 'role' => 'owner'])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 17, 'public_id' => PrivatePurchaseService::STOCK, 'customer_id' => 15, 'organization_id' => 15, 'project_name' => 'Fixture', 'intake_data' => '{}', 'status' => 'submitted'])->execute();
    $offerId = OfflinePrepaymentService::offerId(PrivatePurchaseService::STOCK);
    $this->db->insert('famtastic_private_offer')->fields(['id' => 2, 'public_id' => $offerId, 'website_request_id' => 17, 'customer_id' => 15, 'organization_id' => 15, 'sku' => 'PRIVATE-STOCKANDSHIP98-200', 'list_amount_minor' => 20000, 'offered_amount_minor' => 20000, 'created_by_uid' => 0, 'commerce_order_id' => 21, 'status' => 'prepaid_held'])->execute();
    $data = ['request_id' => 17, 'request_public_id' => PrivatePurchaseService::STOCK, 'customer_id' => 15, 'scope' => OfflinePrepaymentService::stockandshipScope(), 'scope_hash' => 'fixture-hash', 'completion' => ['state' => 'not_issued'], 'offer_public_id' => $offerId, 'recorded_at' => 123, 'evidence_source' => 'Fritz Medine explicit confirmation', 'hold' => 'awaiting_customer_terms_domain_and_final_acceptance'];
    $order = $this->createMock(OrderInterface::class);
    $order->method('getData')->willReturnCallback(static function () use (&$data) { return $data; });
    $order->method('id')->willReturn(21); $order->method('getOrderNumber')->willReturn('FIXTURE-21');
    $order->method('getCustomerId')->willReturn(15); $order->method('getState')->willReturn((object) ['value' => 'draft']);
    $order->method('getTotalPrice')->willReturn(new Price('200.00', 'USD')); $order->method('getBalance')->willReturn(new Price('0.00', 'USD'));
    $order->expects(self::never())->method('save');
    $orderStorage = $this->createMock(EntityStorageInterface::class); $orderStorage->method('loadUnchanged')->willReturn($order); $orderStorage->expects(self::never())->method('load'); $orderStorage->expects(self::never())->method('create');
    $payment = $this->createMock(\Drupal\commerce_payment\Entity\PaymentInterface::class);
    $payment->method('id')->willReturn(5); $payment->method('isCompleted')->willReturn(TRUE);
    $payment->method('getPaymentGatewayId')->willReturn(OfflinePrepaymentService::GATEWAY);
    $payment->method('getAmount')->willReturn(new Price('200.00', 'USD')); $payment->method('getRefundedAmount')->willReturn(new Price('0.00', 'USD'));
    $payment->method('getState')->willReturn((object) ['value' => 'completed']);
    $paymentStorage = $this->createMock(\Drupal\commerce_payment\PaymentStorageInterface::class);
    $paymentStorage->method('loadMultipleByOrder')->willReturn([$payment]); $paymentStorage->expects(self::never())->method('create');
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnCallback(static fn($type) => match ($type) { 'commerce_order' => $orderStorage, 'commerce_payment' => $paymentStorage, default => throw new \LogicException('Unexpected ' . $type) });
    $this->container->set('entity_type.manager', $manager);
    foreach ([['state' => 'not_issued'], ['state' => 'issued', 'expires_at' => 1], ['state' => 'consumed']] as $state) {
      $data['completion'] = $state;
      $form = (new PrivatePurchaseForm())->buildForm([], new FormState(), PrivatePurchaseService::STOCK);
      self::assertArrayNotHasKey('actions', $form);
      self::assertStringContainsString('$0.00 outstanding', (string) $form['status']['#value']);
    }
    $data['completion'] = ['state' => 'issued', 'expires_at' => time() + 3600];
    $form = (new PrivatePurchaseForm())->buildForm([], new FormState(), PrivatePurchaseService::STOCK);
    self::assertSame('password', $form['completion_code']['#type']);
    self::assertStringContainsString('no charge', (string) $form['actions']['submit']['#value']);
    self::assertArrayNotHasKey('payment_method', $form);
    self::assertSame(0, $this->created);
  }
}
