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
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Form\PrivatePurchaseForm;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;
use Drupal\famtastic_pipeline\Service\SelectedSourceIntent;
use Drupal\famtastic_pipeline\EventSubscriber\PrivateScopeCheckoutGuard;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once dirname(__DIR__, 3) . '/src/Service/OfflinePrepaymentService.php';
require_once dirname(__DIR__, 3) . '/src/Service/PrivatePurchaseAuthorityInterface.php';
require_once dirname(__DIR__, 3) . '/src/Service/ApprovedPrivatePurchaseAuthority.php';
require_once dirname(__DIR__, 3) . '/src/Service/PrivatePurchaseService.php';
require_once dirname(__DIR__, 3) . '/src/Service/SelectedSourceIntent.php';
require_once dirname(__DIR__, 3) . '/src/Form/PrivatePurchaseForm.php';
require_once dirname(__DIR__, 3) . '/src/EventSubscriber/PrivateScopeCheckoutGuard.php';

/** SQLite binding tests; native entities mocked, provider never contacted. */
final class PrivatePurchaseServiceTest extends UnitTestCase {
  private const HASH_SALT = 'private-purchase-unit-fixture-only';
  private Connection $db;
  private PrivatePurchaseService $service;
  private UserInterface $owner;
  private array $orders = [];
  private array $metadata = [];
  private array $scope;
  private array $input;
  private ContainerBuilder $container;
  private int $created = 0;
  private array $studio = [];
  private array $entityFields = [];
  private int $orderOwnerId = 14;
  private int $orderStoreId = 1;
  private string $orderTotal = '199.00';
  private string $itemPrice = '199.00';
  private bool $priceOverridden = TRUE;
  private bool $orderHasPaymentGateway = TRUE;
  private ?string $savedGatewayId = NULL;
  private ?PaymentGatewayInterface $cachedOrderGateway = NULL;
  private array $savedGateways = [];
  private array $savedGatewayLoads = [];

  protected function setUp(): void {
    parent::setUp();
    new Settings(['hash_salt' => self::HASH_SALT, 'famtastic_payment_mode' => 'test', 'famtastic_private_reunion_checkout_enabled' => TRUE]);
    $options = ['database' => ':memory:', 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'driver' => 'sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $definitions = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema();
    foreach (['famtastic_customer', 'famtastic_membership', 'famtastic_project_request', 'famtastic_private_offer', 'famtastic_event', 'famtastic_request_asset'] as $table) $this->db->schema()->createTable($table, $definitions[$table]);
    $this->owner = $this->account(14, 'mbshclassof2000@gmail.com');
    $this->db->insert('famtastic_customer')->fields(['id' => 14, 'public_id' => 'fixture-customer', 'uid' => 14, 'display_name' => 'Isolated fixture', 'email' => $this->owner->getEmail(), 'verified_at' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 14, 'organization_id' => 14, 'role' => 'owner', 'status' => 'active'])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 16, 'public_id' => PrivatePurchaseService::REUNION, 'customer_id' => 14, 'organization_id' => 14,
      'project_name' => 'Fixture only', 'project_type' => 'event_site', 'prospect_id' => 298, 'project_id' => 501,
      'intake_data' => json_encode(['page_count' => 1, 'authored_content' => ['pages' => [['record_id' => 'home-v1', 'text' => ['heading' => 'Our reunion']]]]]),
      'status' => 'submitted', 'proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'selected_proof_at' => 123, 'proof_campaign_id' => 55])->execute();
    $offerId = OfflinePrepaymentService::offerId('reunion-private-scope:' . PrivatePurchaseService::REUNION);
    $this->db->insert('famtastic_private_offer')->fields(['id' => 1, 'public_id' => $offerId, 'website_request_id' => 16, 'customer_id' => 14, 'organization_id' => 14, 'sku' => 'PRIVATE-REUNION16-199', 'list_amount_minor' => 19900, 'offered_amount_minor' => 19900, 'created_by_uid' => 0])->execute();
    $this->scope = json_decode(file_get_contents(dirname(__DIR__, 2) . '/fixtures/reunion-private-scope.json'), TRUE, 512, JSON_THROW_ON_ERROR);
    $this->db->insert('famtastic_event')->fields(['event_key' => 'private-scope:request:16:class-of-2000-v1', 'event_type' => 'commerce.private_scope_offered', 'payload' => json_encode(['request_id' => 16, 'customer_id' => 14, 'organization_id' => 14, 'scope' => $this->scope, 'scope_hash' => PrivatePurchaseService::REUNION_HASH, 'offer_public_id' => $offerId, 'authority' => 'Fritz Medine explicit approval'])])->execute();
    $this->input = ['accept_terms' => TRUE, 'terms_version' => $this->scope['version'], 'scope_hash' => PrivatePurchaseService::REUNION_HASH, 'domain_choice' => 'undecided'];
    $this->container = new ContainerBuilder();
    // Hash an existing checked-in fixture; no generated files or runtime install.
    $this->container->setParameter('app.root', dirname(__DIR__, 2) . '/web');
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
      $order->method('getCustomerId')->willReturnCallback(fn() => $this->orderOwnerId);
      $order->method('getCustomer')->willReturn($this->owner);
      $order->method('getStoreId')->willReturnCallback(fn() => $this->orderStoreId);
      $order->method('getItems')->willReturn($values['order_items']);
      $order->method('getAdjustments')->willReturn([]);
      $order->method('getTotalPrice')->willReturnCallback(fn() => new Price($this->orderTotal, 'USD'));
      $order->method('getState')->willReturn((object) ['value' => 'draft']);
      $order->method('isPaid')->willReturn(FALSE);
      $order->method('hasField')->willReturnCallback(fn($field) => $field === 'payment_gateway' && $this->orderHasPaymentGateway);
      $order->method('get')->willReturnCallback(fn($field) => $field === 'payment_gateway'
        ? (object) ['target_id' => $this->savedGatewayId, 'entity' => $this->cachedOrderGateway]
        : throw new \LogicException('Unexpected order field: ' . $field));
      $order->method('getData')->willReturnCallback(fn($key, $default = NULL) => $this->metadata[$id][$key] ?? $default);
      $order->method('setData')->willReturnCallback(function ($key, $value) use ($order, $id) { $this->metadata[$id][$key] = $value; return $order; });
      $order->method('save')->willReturnCallback(function () use ($order, $id) { $this->orders[$id] = $order; return 1; });
      return $order;
    });
    $itemStorage = $this->createMock(EntityStorageInterface::class);
    $item = $this->createMock(OrderItemInterface::class);
    $item->method('getUnitPrice')->willReturnCallback(fn() => new Price($this->itemPrice, 'USD'));
    $item->method('isUnitPriceOverridden')->willReturnCallback(fn() => $this->priceOverridden);
    $item->method('getQuantity')->willReturn('1'); $item->method('getAdjustments')->willReturn([]);
    $item->expects(self::atMost(1))->method('setUnitPrice')->with(new Price('199.00', 'USD'), TRUE);
    $itemStorage->method('create')->willReturn($item);
    $gatewayStorage = $this->createMock(EntityStorageInterface::class);
    $gateway = $this->createMock(PaymentGatewayInterface::class); $gateway->method('getPluginId')->willReturn('stripe');
    $gateway->expects(self::never())->method('getPlugin');
    $gatewayStorage->method('loadByProperties')->willReturn([$gateway]);
    $gatewayStorage->expects(self::never())->method('load');
    $gatewayStorage->method('loadUnchanged')->willReturnCallback(function ($id) {
      $this->savedGatewayLoads[] = $id;
      return $this->savedGateways[$id] ?? NULL;
    });
    $storeStorage = $this->createMock(EntityStorageInterface::class);
    $store = $this->createMock(StoreInterface::class); $store->method('getDefaultCurrencyCode')->willReturn('USD'); $store->method('isPublished')->willReturn(TRUE);
    $storeStorage->method('load')->willReturn($store);
    $this->entityFields = [
      'famtastic_project' => ['prospect_ref' => ['target_id' => 298]],
      'proof_variant' => ['campaign_id' => ['target_id' => 55], 'direction_id' => ['value' => 'a'],
        'artifact_path' => ['value' => 'fixtures/reunion-private-scope.json'], 'design_dna' => ['value' => '{"fixture":true}']],
      'proof_campaign' => ['prospect_id' => ['target_id' => 298], 'selected_variant' => ['value' => 'a'], 'selected_at' => ['value' => 123]],
    ];
    $selectionStorages = [];
    foreach (['famtastic_project' => 501, 'proof_variant' => 601, 'proof_campaign' => 55] as $type => $id) {
      $entity = $this->createMock(ContentEntityInterface::class);
      $entity->method('id')->willReturn($id);
      $entity->method('get')->willReturnCallback(fn($field) => (object) ($field === 'studio_json'
        ? ['value' => json_encode($this->studio)] : $this->entityFields[$type][$field]));
      $entity->expects(self::never())->method('save');
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('loadUnchanged')->willReturnCallback(static fn($requested) => (int) $requested === $id ? $entity : NULL);
      $storage->expects(self::never())->method('load');
      $selectionStorages[$type] = $storage;
    }
    $manager->method('getStorage')->willReturnCallback(fn($type) => match ($type) {
      'commerce_order' => $orderStorage, 'commerce_order_item' => $itemStorage,
      'commerce_payment_gateway' => $gatewayStorage, 'commerce_store' => $storeStorage,
      'famtastic_project', 'proof_variant', 'proof_campaign' => $selectionStorages[$type],
      default => throw new \LogicException('Unexpected storage or payment creation: ' . $type),
    });
    $this->container->set('entity_type.manager', $manager);
    \Drupal::setContainer($this->container);
    $this->service = new PrivatePurchaseService();
    $this->container->set('famtastic_pipeline.private_purchase', $this->service);
    $artifact = dirname(__DIR__, 2) . '/fixtures/reunion-private-scope.json';
    $this->studio['selected_source_intent'] = SelectedSourceIntent::create($this->request(), '501', 601, 'a', 1, gmdate(DATE_ATOM, 123),
      [['role' => 'selected_preview', 'path' => 'fixtures/reunion-private-scope.json', 'sha256' => hash_file('sha256', $artifact), 'bytes' => filesize($artifact)]], ['fixture' => TRUE], [], NULL);
    $this->input['selection_snapshot'] = PrivatePurchaseService::selection($this->request());
  }

  private function request(): array {
    return $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 16)->execute()->fetchAssoc();
  }

  private function account(int $id, string $email): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn($id); $account->method('getEmail')->willReturn($email); $account->method('isAuthenticated')->willReturn($id > 0); $account->method('isActive')->willReturn(TRUE);
    return $account;
  }

  public function testReadOnlyFormUsesExactScopeAndNoRenewalOrPaymentMutation(): void {
    $context = $this->service->context($this->owner, PrivatePurchaseService::REUNION);
    self::assertSame(PrivatePurchaseService::REUNION_HASH, hash('sha256', json_encode($this->scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    self::assertNull($context['order']); self::assertSame(0, $this->created);
    $state = new FormState();
    $before = time();
    $form = (new PrivatePurchaseForm())->buildForm([], $state, PrivatePurchaseService::REUNION);
    self::assertSame(0, $form['#cache']['max-age']);
    self::assertFalse($state->isCached(), 'GET must not write Form API cache.');
    self::assertSame('hidden', $form['scope_snapshot']['#type']);
    $token = $form['scope_snapshot']['#default_value'];
    self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[a-f0-9]{64}$/D', $token);
    [$encoded, $signature] = explode('.', $token, 2);
    self::assertSame(hash_hmac('sha256', 'private-purchase-form-v1|' . $encoded, self::HASH_SALT), $signature);
    $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/'), TRUE), TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertSame(PrivatePurchaseService::REUNION, $payload['request']);
    self::assertSame($this->scope['version'], $payload['version']);
    self::assertSame(PrivatePurchaseService::REUNION_HASH, $payload['hash']);
    self::assertSame($this->input['selection_snapshot'], $payload['selection_snapshot']);
    self::assertSame(14, $payload['uid']);
    self::assertIsInt($payload['issued_at']);
    self::assertGreaterThanOrEqual($before, $payload['issued_at']);
    self::assertLessThanOrEqual(time(), $payload['issued_at']);
    self::assertStringContainsString('$199', (string) $form['status']['#value']);
    self::assertStringContainsString('No recurring', (string) $form['scope']['policy']['#value']);
    self::assertArrayHasKey('submit', $form['actions']);
    self::assertSame(0, $this->created);
  }

  /** Test signing only; deliberately malformed payloads never use real secrets. */
  private function signedFixtureScope(array $payload, string $prefix = 'private-purchase-form-v1|'): string {
    $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    return $encoded . '.' . hash_hmac('sha256', $prefix . $encoded, self::HASH_SALT);
  }

  private function displayedScopeToken(): string {
    $form = (new PrivatePurchaseForm())->buildForm([], new FormState(), PrivatePurchaseService::REUNION);
    return $form['scope_snapshot']['#default_value'];
  }

  private function scopePayload(string $token): array {
    return json_decode(base64_decode(strtr(explode('.', $token, 2)[0], '-_', '+/'), TRUE), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /** Exercise public hooks with a fresh POST state; not an HTTP/CSRF proof. */
  private function validateScopeToken(?string $token, ?array $rawInput = NULL): FormState {
    $formObject = new PrivatePurchaseForm();
    $state = (new FormState())->setRequestMethod('POST');
    $state->clearErrors();
    $form = $formObject->buildForm([], $state, PrivatePurchaseService::REUNION);
    $values = ['accept_terms' => 1, 'domain_choice' => 'undecided', 'domain' => ''];
    if ($token !== NULL) $values['scope_snapshot'] = $token;
    // Model submitted input separately from the normalized Form API values.
    $state->setUserInput($rawInput ?? $values);
    $state->setValues($values);
    $formObject->validateForm($form, $state);
    self::assertSame(0, $this->created, 'Validation must not create an order.');
    self::assertSame([], $this->orders);
    self::assertNull($this->service->context($this->owner, PrivatePurchaseService::REUNION)['order']);
    return $state;
  }

  public function testSignedDisplayedScopeValidatesInFreshPostStateWithoutStagingAcceptance(): void {
    $token = $this->displayedScopeToken();
    self::assertSame([], $this->validateScopeToken($token)->getErrors());
    self::assertSame('not_started', $this->request()['staging_review_status']);
    self::assertSame('', $this->request()['staging_receipt_hash']);
  }

  public function testValidNormalizedDefaultCannotReplaceMissingOrInvalidRawSnapshot(): void {
    $token = $this->displayedScopeToken();
    self::assertSame([], $this->validateScopeToken($token)->getErrors());
    $values = ['accept_terms' => 1, 'domain_choice' => 'undecided', 'domain' => ''];
    foreach ([
      'absent' => $values,
      'null' => $values + ['scope_snapshot' => NULL],
      'array' => $values + ['scope_snapshot' => [$token]],
      'empty' => $values + ['scope_snapshot' => ''],
      'nonstring' => $values + ['scope_snapshot' => 1],
      'tampered' => $values + ['scope_snapshot' => explode('.', $token, 2)[0] . '.' . str_repeat('0', 64)],
    ] as $case => $rawInput) {
      // A POST rebuild may supply a valid hidden default in normalized values.
      // That default is not evidence that the customer submitted the snapshot.
      $state = $this->validateScopeToken($token, $rawInput);
      self::assertSame($token, $state->getValue('scope_snapshot'), $case);
      self::assertSame($rawInput, $state->getUserInput(), $case);
      self::assertArrayHasKey('accept_terms', $state->getErrors(), $case);
    }
  }

  public function testUnsignedMalformedAndTamperedScopeTokensAreRejected(): void {
    $valid = $this->displayedScopeToken();
    [$encoded, $signature] = explode('.', $valid, 2);
    $payload = $this->scopePayload($valid);
    $tampered = $payload; $tampered['hash'] = str_repeat('0', 64);
    $changedEncoded = explode('.', $this->signedFixtureScope($tampered), 2)[0];
    foreach ([
      'missing' => NULL, 'empty' => '', 'unsigned' => $encoded,
      'wrong_signature' => $encoded . '.' . str_repeat('0', 64),
      'payload_changed_without_resigning' => $changedEncoded . '.' . $signature,
      'extra_segment' => $valid . '.extra', 'invalid_encoding' => '***.' . $signature,
      'oversized' => str_repeat('A', 16001) . '.' . $signature,
      'wrong_purpose' => $this->signedFixtureScope($payload, 'another-form|'),
    ] as $case => $token) {
      self::assertArrayHasKey('accept_terms', $this->validateScopeToken($token)->getErrors(), $case);
    }
  }

  public function testSignedScopeRejectsWrongUidRequestExpiredFutureAndChangedTerms(): void {
    $payload = $this->scopePayload($this->displayedScopeToken());
    foreach ([
      'another_account' => ['uid' => 15], 'noninteger_uid' => ['uid' => '14'],
      'another_request' => ['request' => PrivatePurchaseService::STOCK],
      'expired' => ['issued_at' => time() - 21660], 'future' => ['issued_at' => time() + 120],
      'noninteger_time' => ['issued_at' => (string) time()],
      'scope_hash' => ['hash' => str_repeat('0', 64)], 'terms_version' => ['version' => 'old-scope'],
      'missing_selection' => ['selection_snapshot' => NULL],
    ] as $case => $change) {
      $token = $this->signedFixtureScope(array_replace($payload, $change));
      self::assertArrayHasKey('accept_terms', $this->validateScopeToken($token)->getErrors(), $case);
    }
    $withoutIssueTime = $payload; unset($withoutIssueTime['issued_at']);
    self::assertArrayHasKey('accept_terms', $this->validateScopeToken($this->signedFixtureScope($withoutIssueTime))->getErrors());
  }

  public function testSignedScopeWithinAgeAndClockSkewLimitsStillValidates(): void {
    $payload = $this->scopePayload($this->displayedScopeToken());
    // Stay away from second-boundary races while testing both permitted ranges.
    foreach ([time() - 21540, time() + 30] as $issuedAt) {
      $token = $this->signedFixtureScope(array_replace($payload, ['issued_at' => $issuedAt]));
      self::assertSame([], $this->validateScopeToken($token)->getErrors());
    }
  }

  public function testAnotherSignedInAccountCannotValidateOwnersDisplayedScope(): void {
    $formObject = new PrivatePurchaseForm();
    $getState = new FormState();
    $form = $formObject->buildForm([], $getState, PrivatePurchaseService::REUNION);
    $state = (new FormState())->setRequestMethod('POST');
    $state->clearErrors();
    $state->set('private_scope', ['request' => PrivatePurchaseService::REUNION]);
    $values = ['scope_snapshot' => $form['scope_snapshot']['#default_value'], 'accept_terms' => 1, 'domain_choice' => 'undecided', 'domain' => ''];
    $state->setUserInput($values);
    $state->setValues($values);
    $this->container->set('current_user', $this->account(15, 'sprospere@yahoo.com'));
    (new PrivatePurchaseForm())->validateForm($form, $state);
    self::assertArrayHasKey('accept_terms', $state->getErrors());
    self::assertSame(0, $this->created);
    self::assertSame([], $this->orders);
  }

  public function testSignedOldSelectionCannotBeReplacedByFreshFormStateOnPost(): void {
    $token = $this->displayedScopeToken();
    $this->studio['selected_source_intent']['selection']['revision'] = 2;
    $this->studio['selected_source_intent']['intent_id'] = 'selected-source:request:16:revision:2';
    $state = $this->validateScopeToken($token);
    self::assertSame(2, $state->get('private_scope')['selection_snapshot']['selection_revision']);
    self::assertArrayHasKey('accept_terms', $state->getErrors(), 'Fresh server state cannot bless the old signed selection.');
    self::assertSame([], $this->validateScopeToken($this->displayedScopeToken())->getErrors());
  }

  public function testSignedOldScopeRejectsSameDirectionCopyEditWithNewProducerAuthority(): void {
    $token = $this->displayedScopeToken();
    $intake = json_decode($this->request()['intake_data'], TRUE);
    $intake['authored_content']['pages'][0]['text']['heading'] = 'Changed reunion copy';
    $this->db->update('famtastic_project_request')->fields(['intake_data' => json_encode($intake)])->condition('id', 16)->execute();
    $prior = $this->studio['selected_source_intent'];
    $this->studio['selected_source_intent'] = SelectedSourceIntent::create($this->request(), '501', 601, 'a', 2, gmdate(DATE_ATOM, 124), $prior['source']['artifacts'], $prior['source']['design_dna'], [], NULL);
    self::assertArrayHasKey('accept_terms', $this->validateScopeToken($token)->getErrors());
    self::assertSame([], $this->validateScopeToken($this->displayedScopeToken())->getErrors());
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
    catch (\RuntimeException $e) { self::assertSame('private_scope_selection_authority_changed', $e->getMessage()); }
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

  /** Exercise all native entry guards without invoking a provider or workflow. */
  private function assertBoundariesReject(OrderInterface $order): void {
    $before = $this->metadata;
    $guard = new PrivateScopeCheckoutGuard();
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('status')->willReturn(TRUE);
    $gateway->method('getPluginId')->willReturn('stripe');
    $gateway->expects(self::never())->method('getPlugin');
    $event = new \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent(['stripe' => $gateway], $order);
    $guard->gateways($event);
    self::assertSame([], $event->getPaymentGateways());
    $kernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);
    $request = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, Request::create('/checkout/' . $order->id()), 1);
    $placement = $this->createMock(\Drupal\state_machine\Event\WorkflowTransitionEvent::class);
    $placement->method('getEntity')->willReturn($order);
    foreach (['request' => $request, 'place' => $placement] as $method => $boundaryEvent) {
      try { $guard->$method($boundaryEvent); self::fail('Stale authority passed ' . $method); }
      catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) { self::assertTrue(TRUE); }
    }
    self::assertSame($before, $this->metadata, 'Refusal never rewrites an order snapshot.');
    self::assertSame(1, $this->created);
  }

  private function gateway(string $plugin, bool $enabled = TRUE): PaymentGatewayInterface {
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->method('status')->willReturn($enabled);
    $gateway->method('getPluginId')->willReturn($plugin);
    $gateway->expects(self::never())->method('getPlugin');
    return $gateway;
  }

  /** Guard-only positive control; no real provider initialization or placement. */
  private function assertBoundariesAllow(OrderInterface $order): void {
    $before = $this->metadata;
    $guard = new PrivateScopeCheckoutGuard();
    $gateway = $this->gateway('stripe');
    $event = new \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent(['stripe' => $gateway], $order);
    $guard->gateways($event);
    self::assertSame(['stripe' => $gateway], $event->getPaymentGateways());
    $kernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);
    $request = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, Request::create('/checkout/' . $order->id()), 1);
    $guard->request($request);
    self::assertFalse($request->hasResponse());
    $placement = $this->createMock(\Drupal\state_machine\Event\WorkflowTransitionEvent::class);
    $placement->method('getEntity')->willReturn($order);
    $guard->place($placement);
    self::assertSame($before, $this->metadata);
    self::assertSame(1, $this->created);
  }

  private function assertSavedGatewayRevocationRejectsAllBoundaries(?PaymentGatewayInterface $fresh): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $this->savedGatewayId = 'private_saved_stripe';
    $this->cachedOrderGateway = $this->gateway('stripe');
    $this->savedGateways[$this->savedGatewayId] = $this->cachedOrderGateway;
    $this->assertBoundariesAllow($order);
    self::assertSame(array_fill(0, 3, $this->savedGatewayId), $this->savedGatewayLoads);

    // The field's previously loaded entity stays enabled. Only saved storage
    // changes; each boundary must independently loadUnchanged before proceeding.
    if ($fresh === NULL) unset($this->savedGateways[$this->savedGatewayId]);
    else $this->savedGateways[$this->savedGatewayId] = $fresh;
    $this->savedGatewayLoads = [];
    $this->assertBoundariesReject($order);
    self::assertSame(array_fill(0, 3, $this->savedGatewayId), $this->savedGatewayLoads);
    self::assertSame($this->cachedOrderGateway, $order->get('payment_gateway')->entity);
    self::assertTrue($this->cachedOrderGateway->status());
  }

  public function testDisabledSavedGatewayRejectsAllBoundariesDespiteEnabledCachedEntity(): void {
    $this->assertSavedGatewayRevocationRejectsAllBoundaries($this->gateway('stripe', FALSE));
  }

  public function testRemovedSavedGatewayRejectsAllBoundariesDespiteEnabledCachedEntity(): void {
    $this->assertSavedGatewayRevocationRejectsAllBoundaries(NULL);
  }

  public function testUnsupportedSavedGatewayRejectsAllBoundariesDespiteEnabledCachedEntity(): void {
    $this->assertSavedGatewayRevocationRejectsAllBoundaries($this->gateway('manual'));
  }

  public function testEnabledSavedStripeGatewaysAreFreshlyCheckedAtAllBoundaries(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    foreach (['stripe', 'stripe_payment_element'] as $plugin) {
      $this->savedGatewayId = 'private_saved_' . $plugin;
      $this->savedGateways[$this->savedGatewayId] = $this->gateway($plugin);
      $this->savedGatewayLoads = [];
      $this->assertBoundariesAllow($order);
      self::assertSame(array_fill(0, 3, $this->savedGatewayId), $this->savedGatewayLoads);
    }
  }

  public function testInitialUnassignedGatewayNeedsNoSavedGatewayLookup(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    foreach ([[TRUE, NULL], [TRUE, ''], [FALSE, NULL]] as [$hasField, $target]) {
      $this->orderHasPaymentGateway = $hasField;
      $this->savedGatewayId = $target;
      $this->assertBoundariesAllow($order);
      self::assertSame([], $this->savedGatewayLoads);
    }
  }

  public function testPrivatePolicyAllowsAllBoundariesBeforeStagingStarts(): void {
    $row = $this->request();
    self::assertSame('not_started', $row['staging_status']);
    self::assertSame('not_started', $row['staging_review_status']);
    self::assertSame('', $row['staging_receipt_hash']);
    $snapshot = PrivatePurchaseService::selection($row);
    self::assertSame(PrivatePurchaseService::REUNION_POLICY, $snapshot['policy']);
    self::assertSame(1, $snapshot['selection_revision']);
    self::assertSame(SelectedSourceIntent::requestBinding($row, []), $snapshot['request_binding']);
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    self::assertSame($snapshot, $order->getData(PrivatePurchaseService::KEY)['selection']);
    $this->service->assertReunionOrder($order);
    $guard = new PrivateScopeCheckoutGuard();
    $placement = $this->createMock(\Drupal\state_machine\Event\WorkflowTransitionEvent::class);
    $placement->method('getEntity')->willReturn($order);
    // Placement can be reconciled by a server callback without impersonating
    // the owner; fresh order-owner membership still must pass.
    $this->container->set('current_user', $this->account(0, ''));
    $guard->place($placement);
    self::assertSame(1, $this->created);
  }

  public function testMissingPartialAndNonStrictDisplayedSnapshotsCannotCreateOrder(): void {
    foreach ([NULL, [], ['campaign_id' => 55, 'direction' => 'a'], array_replace($this->input['selection_snapshot'], ['selection_revision' => '1'])] as $snapshot) {
      $input = $this->input;
      if ($snapshot === NULL) unset($input['selection_snapshot']);
      else $input['selection_snapshot'] = $snapshot;
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $input); self::fail('Missing/stale snapshot accepted'); }
      catch (\RuntimeException $e) { self::assertSame('private_scope_selection_snapshot_changed', $e->getMessage()); }
    }
    self::assertSame(0, $this->created);
  }

  public function testSameDirectionScopeContentAndProjectTypeEditsRejectCreationAndAllBoundaries(): void {
    $original = $this->request();
    $intake = json_decode($original['intake_data'], TRUE);
    $morePages = $intake; $morePages['page_count'] = 2;
    $copyEdit = $intake; $copyEdit['authored_content']['pages'][0]['text']['heading'] = 'Changed copy';
    $changes = [['intake_data' => json_encode($morePages)], ['intake_data' => json_encode($copyEdit)], ['project_type' => 'online_store']];
    foreach ($changes as $change) {
      $this->db->update('famtastic_project_request')->fields($change)->condition('id', 16)->execute();
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); self::fail('Changed input accepted'); }
      catch (\RuntimeException $e) { self::assertSame('private_scope_selection_authority_changed', $e->getMessage()); }
      self::assertSame(0, $this->created);
      $this->db->update('famtastic_project_request')->fields(['intake_data' => $original['intake_data'], 'project_type' => $original['project_type']])->condition('id', 16)->execute();
    }
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    foreach ($changes as $change) {
      $this->db->update('famtastic_project_request')->fields($change)->condition('id', 16)->execute();
      $this->assertBoundariesReject($order);
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); self::fail('Stale purchase resumed'); }
      catch (\RuntimeException $e) { self::assertSame('private_scope_selection_authority_changed', $e->getMessage()); }
      $this->db->update('famtastic_project_request')->fields(['intake_data' => $original['intake_data'], 'project_type' => $original['project_type']])->condition('id', 16)->execute();
    }
  }

  public function testSameDirectionReselectionCannotRebaseExistingOrderEvenWithFreshForm(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $this->studio['selected_source_intent']['selection']['revision'] = 2;
    $this->studio['selected_source_intent']['intent_id'] = 'selected-source:request:16:revision:2';
    $fresh = PrivatePurchaseService::selection($this->request());
    self::assertSame('a', $fresh['direction']);
    self::assertSame(2, $fresh['selection_revision']);
    foreach ([$this->input, array_replace($this->input, ['selection_snapshot' => $fresh])] as $input) {
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $input); self::fail('Reselection rebased the purchase'); }
      catch (\RuntimeException $e) { self::assertContains($e->getMessage(), ['private_scope_selection_snapshot_changed', 'private_order_reconciliation_required']); }
    }
    $this->assertBoundariesReject($order);
    self::assertSame(1, $order->getData(PrivatePurchaseService::KEY)['selection']['selection_revision']);
  }

  public function testFreshProducerIntentAfterEditStillRejectsThePreviouslyDisplayedForm(): void {
    $row = $this->request(); $intake = json_decode($row['intake_data'], TRUE); $intake['page_count'] = 2;
    $this->db->update('famtastic_project_request')->fields(['intake_data' => json_encode($intake)])->condition('id', 16)->execute();
    $prior = $this->studio['selected_source_intent'];
    $this->studio['selected_source_intent'] = SelectedSourceIntent::create($this->request(), '501', 601, 'a', 2, gmdate(DATE_ATOM, 124), $prior['source']['artifacts'], $prior['source']['design_dna'], [], NULL);
    self::assertNotSame($this->input['selection_snapshot'], PrivatePurchaseService::selection($this->request()));
    $this->expectExceptionMessage('private_scope_selection_snapshot_changed');
    try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); }
    finally { self::assertSame(0, $this->created); }
  }

  public function testMissingOrTamperedProducerAuthorityNeverCreatesPurchase(): void {
    $original = $this->studio['selected_source_intent'];
    $variants = [[], array_replace_recursive($original, ['selection' => ['revision' => 0]]),
      array_replace_recursive($original, ['customer_id' => '15']), array_replace_recursive($original, ['proof_campaign_id' => '999']),
      array_replace_recursive($original, ['scope' => ['snapshot_sha256' => str_repeat('0', 64)]]),
      array_replace_recursive($original, ['source' => ['artifacts' => [0 => ['sha256' => str_repeat('0', 64)]]]]),
      array_replace_recursive($original, ['source' => ['artifacts' => [0 => ['path' => '../outside.html']]]])];
    foreach ($variants as $intent) {
      $this->studio['selected_source_intent'] = $intent;
      try { $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input); self::fail('Invalid authority accepted'); }
      catch (\RuntimeException $e) { self::assertSame('private_scope_selection_authority_changed', $e->getMessage()); }
      self::assertSame(0, $this->created);
    }
  }

  public function testSourceRecordAndRecordedByteDriftRejectAllBoundaries(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $this->entityFields['proof_variant']['design_dna']['value'] = '{"fixture":"changed"}';
    $this->assertBoundariesReject($order);
    $this->entityFields['proof_variant']['design_dna']['value'] = '{"fixture":true}';
    $this->studio['selected_source_intent']['source']['artifacts'][0]['sha256'] = str_repeat('0', 64);
    $this->assertBoundariesReject($order);
  }

  public function testAssetAdditionWithdrawalAndConsentDriftInvalidateSelection(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $this->db->insert('famtastic_request_asset')->fields(['id' => 1, 'public_id' => 'reference', 'website_request_id' => 16, 'customer_id' => 14,
      'file_id' => 1, 'original_name' => 'reference.png', 'mime_type' => 'image/png', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'ownership_confirmed' => 1])->execute();
    $this->assertBoundariesReject($order);
    // Producer authority for this exact reference; all original order authority
    // remains immutable, even if a new form now displays the changed selection.
    $record = ['public_id' => 'reference', 'kind' => 'reference', 'role' => 'other', 'name' => 'reference.png', 'mime_type' => 'image/png',
      'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'website_request_id' => '16', 'customer_id' => '14', 'status' => 'active',
      'ownership_confirmed' => TRUE, 'ai_use_consent' => FALSE, 'likeness_consent_version' => '', 'likeness_consent_at' => NULL,
      'subject_permission_confirmed' => FALSE, 'ai_transformation_consent' => FALSE];
    $this->studio['selected_source_intent']['asset_authority']['records'] = [$record];
    self::assertNotSame($this->input['selection_snapshot']['request_binding'], PrivatePurchaseService::selection($this->request())['request_binding']);
    foreach ([['status' => 'withdrawn'], ['ownership_confirmed' => 0], ['ai_use_consent' => 1], ['customer_id' => 15]] as $change) {
      $this->db->update('famtastic_request_asset')->fields($change)->condition('id', 1)->execute();
      $this->assertBoundariesReject($order);
      $this->db->update('famtastic_request_asset')->fields(['status' => 'active', 'ownership_confirmed' => 1, 'ai_use_consent' => 0, 'customer_id' => 14])->condition('id', 1)->execute();
    }
  }

  public function testRevokedMembershipAndVerificationRecheckStaleContextAndAllBoundaries(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $oldContext = $this->service->context($this->owner, PrivatePurchaseService::REUNION);
    $this->db->update('famtastic_membership')->fields(['status' => 'revoked'])->execute();
    try { $this->service->assertReunionOrder($order, $oldContext); self::fail('Stale context used after revocation'); }
    catch (\RuntimeException $e) { self::assertSame('private_purchase_not_found', $e->getMessage()); }
    $this->assertBoundariesReject($order);
    $this->db->update('famtastic_membership')->fields(['status' => 'active'])->execute();
    $this->db->update('famtastic_customer')->fields(['verified_at' => NULL])->execute();
    $this->assertBoundariesReject($order);
  }

  public function testOrderIdentityMetadataAndDefaultOffCannotBypassBoundaryGuards(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $original = $this->metadata[$order->id()][PrivatePurchaseService::KEY];
    foreach ([['version' => 1], ['request_id' => 17], ['offer_public_id' => 'different'], ['accepted_by_uid' => 15],
      ['recurring_authorized' => TRUE], ['launch_authorized' => TRUE], ['client_acceptance' => 'invented'], ['hold' => 'released'], ['selection' => []]] as $change) {
      $this->metadata[$order->id()][PrivatePurchaseService::KEY] = array_replace($original, $change);
      $this->assertBoundariesReject($order);
    }
    // Missing marker still resolves through the immutable offer/order binding.
    unset($this->metadata[$order->id()][PrivatePurchaseService::KEY]);
    $this->assertBoundariesReject($order);
    $this->metadata[$order->id()][PrivatePurchaseService::KEY] = $original;
    foreach ([[], ['famtastic_private_reunion_checkout_enabled' => TRUE, 'famtastic_payment_mode' => 'disabled']] as $settings) {
      new Settings($settings);
      self::assertFalse(PrivatePurchaseService::checkoutEnabled());
      $this->assertBoundariesReject($order);
    }
  }

  public function testNativeOrderOwnershipStoreAndPriceTamperingRejectsAllBoundaries(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    foreach (['orderOwnerId' => 15, 'orderStoreId' => 2, 'orderTotal' => '1.00', 'itemPrice' => '1.00', 'priceOverridden' => FALSE] as $field => $bad) {
      $original = $this->$field;
      $this->$field = $bad;
      $this->assertBoundariesReject($order);
      $this->$field = $original;
    }
    $this->service->assertReunionOrder($order);
  }

  public function testGatewayResolutionRejectsWrongSignedInAccount(): void {
    $order = $this->service->startReunion($this->owner, PrivatePurchaseService::REUNION, $this->input);
    $this->container->set('current_user', $this->account(15, 'sprospere@yahoo.com'));
    $gateway = $this->createMock(PaymentGatewayInterface::class);
    $gateway->expects(self::never())->method('getPlugin');
    $event = new \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent(['stripe' => $gateway], $order);
    (new PrivateScopeCheckoutGuard())->gateways($event);
    self::assertSame([], $event->getPaymentGateways());
    self::assertSame(1, $this->created);
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
