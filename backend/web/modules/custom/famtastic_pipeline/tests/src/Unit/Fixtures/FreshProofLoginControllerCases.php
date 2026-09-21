<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\{CustomerPortalController, PortalLoginSessionDouble};
use Drupal\famtastic_pipeline\Service\DeepDiveInvitationService;
use Drupal\user\{UserAuthInterface, UserInterface};
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/PortalLoginSessionDouble.php';

/** Actual final controller/services; only credential, user and session doubles. */
trait FreshProofLoginControllerCases {
  private function loginContext(bool $credentials = TRUE, bool $active = TRUE, bool $floodAllowed = TRUE): array {
    // The offline bootstrap intentionally does not install or boot user.module.
    foreach (['UserAuthInterface', 'UserInterface'] as $interface) {
      if (!interface_exists('Drupal\\user\\' . $interface)) require_once dirname((string) getenv('FAMTASTIC_BACKEND_VENDOR')) . '/web/core/modules/user/src/' . $interface . '.php';
    }
    $this->db->schema()->createTable('famtastic_deep_dive_invitation', _famtastic_pipeline_deep_dive_invitation_schema());
    $this->db->insert('famtastic_deep_dive_invitation')->fields([
      'public_id' => $this->uuid(), 'customer_id' => 1, 'prospect_id' => 1, 'website_request_id' => 1,
      'email' => 'synthetic@example.test', 'business_name' => 'Synthetic', 'secret_hash' => str_repeat('0', 64),
      'status' => 'claimed', 'answers' => '{}', 'created' => $this->now, 'changed' => $this->now,
    ])->execute();
    $state = (object) ['uid' => 0, 'finalized' => 0, 'cleared' => 0];
    PortalLoginSessionDouble::$finalize = static function (UserInterface $user) use ($state): void {
      $state->uid = (int) $user->id(); $state->finalized++;
    };
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $state->uid > 0);
    $account->method('id')->willReturnCallback(fn() => $state->uid);
    $account->method('hasPermission')->willReturn(FALSE);
    $auth = $this->createMock(UserAuthInterface::class);
    $auth->expects($floodAllowed ? $this->once() : $this->never())->method('authenticate')
      ->with('synthetic@example.test', 'synthetic-test-only')->willReturn($credentials ? 1 : FALSE);
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(1);
    $user->method('isActive')->willReturn($active);
    $user->method('hasPermission')->willReturn(FALSE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(1)->willReturn($user);
    $storage->method('loadByProperties')->with(['mail' => 'synthetic@example.test'])->willReturn([]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user')->willReturn($storage);
    $flood = $this->createMock(FloodInterface::class);
    $flood->method('isAllowed')->willReturn($floodAllowed);
    $flood->method('clear')->willReturnCallback(function () use ($state): void { $state->cleared++; });
    $deepDives = new DeepDiveInvitationService($this->db, $this->entities, $this->clock, $this->createMock(UuidInterface::class));
    // Final controller and final services are real, not mocked/subclassed. Unused
    // mailer and Commerce dependencies stay uninitialized to prohibit side effects.
    $controller = (new \ReflectionClass(CustomerPortalController::class))->newInstanceWithoutConstructor();
    foreach (['portal' => $this->portal, 'userAuth' => $auth, 'account' => $account, 'flood' => $flood,
      'deepDives' => $deepDives, 'entityTypeManager' => $entities] as $property => $value) {
      (new \ReflectionProperty($controller, $property))->setValue($controller, $value);
    }
    $request = Request::create('https://example.test/web/api/customer/login', 'POST', server: ['CONTENT_TYPE' => 'application/json'],
      content: json_encode(['email' => 'synthetic@example.test', 'password' => 'synthetic-test-only'], JSON_THROW_ON_ERROR));
    return [$controller, $request, $state];
  }

  #[DataProvider('loginManagedChanges')]
  public function testRealLoginDoesNotResumeManagedProofWork(bool $enabled, string $change): void {
    $draft = $this->create('save'); $this->asset(); $this->enable();
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    if ($change === 'brief') $this->portal->updateWebsiteRequest(1, $draft['public_id'], ['primary_goal' => 'Changed brief before login'] + $this->input());
    if ($change === 'rights') $this->db->update('famtastic_request_asset')->fields(['ownership_confirmed' => 0])->condition('id', 1)->execute();
    if ($change === 'withdrawn') $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->condition('id', 1)->execute();
    [$controller, $request, $state] = $this->loginContext();
    $before = $this->proofRecords(); $invitation = $this->row('famtastic_deep_dive_invitation');
    $asset = $this->row('famtastic_request_asset');
    $activityCount = $this->tableCount('famtastic_portal_activity');
    $response = $controller->login($request);
    self::assertSame(200, $response->getStatusCode());
    $body = json_decode($response->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertTrue($body['ok']); self::assertTrue($body['customer']['verified']);
    self::assertSame('synthetic@example.test', $body['customer']['email']);
    self::assertSame(1, $state->finalized); self::assertSame(1, $state->uid); self::assertSame(1, $state->cleared);
    self::assertSame($before, $this->proofRecords());
    self::assertSame($invitation, $this->row('famtastic_deep_dive_invitation'));
    self::assertSame($asset, $this->row('famtastic_request_asset'));
    self::assertSame($activityCount, $this->tableCount('famtastic_portal_activity'));
    self::assertSame($change === 'same' ? 'queued' : 'needs_attention', $this->portal->websiteRequestProofHandoff(1)['state']);
    self::assertSame($before, $this->proofRecords());
    self::assertFalse($this->db->inTransaction());
    if ($change !== 'same') $this->reject(fn() => $this->portal->sendWebsiteRequestToSiteStudio(1, $draft['public_id']), $change === 'rights' ? 'asset integrity' : 'differs from current input');
    self::assertSame($before, $this->proofRecords());
  }
  public static function loginManagedChanges(): iterable {
    foreach ([TRUE, FALSE] as $enabled) foreach (['same', 'brief', 'rights', 'withdrawn'] as $change) yield ($enabled ? 'on-' : 'off-') . $change => [$enabled, $change];
  }

  #[DataProvider('loginDenials')]
  public function testRealLoginRetainsAuthenticationGates(string $reason): void {
    $this->enable(); $this->create(); new Settings([]);
    if ($reason === 'unverified') $this->db->update('famtastic_customer')->fields(['verified_at' => NULL])->condition('id', 1)->execute();
    [$controller, $request, $state] = $this->loginContext($reason !== 'credentials', $reason !== 'inactive', $reason !== 'flood');
    $before = $this->proofRecords();
    $response = $controller->login($request);
    self::assertSame(403, $response->getStatusCode());
    self::assertSame(0, $state->finalized); self::assertSame(0, $state->uid); self::assertSame(0, $state->cleared);
    self::assertSame($before, $this->proofRecords());
  }
  public static function loginDenials(): iterable { foreach (['credentials', 'inactive', 'unverified', 'flood'] as $reason) yield $reason => [$reason]; }

  #[DataProvider('flags')]
  public function testLoginDoesNotNeedProofAdmissionService(bool $enabled): void {
    $this->enable(); $this->create(); $this->install(FALSE, FALSE, FALSE);
    new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    [$controller, $request, $state] = $this->loginContext();
    $before = $this->proofRecords();
    self::assertSame(200, $controller->login($request)->getStatusCode());
    self::assertSame(1, $state->finalized); self::assertSame($before, $this->proofRecords());
    self::assertSame('queued', $this->portal->websiteRequestProofHandoff(1)['state']);
  }

  public function testLoginDoesNotSwallowForeignRequestOwnership(): void {
    $this->enable(); $this->create(); new Settings([]);
    [$controller, $request, $state] = $this->loginContext();
    $this->db->update('famtastic_project_request')->fields(['customer_id' => 99])->condition('id', 1)->execute();
    $before = $this->proofRecords();
    $this->reject(fn() => $controller->login($request), 'different customer request');
    self::assertSame(0, $state->finalized); self::assertSame($before, $this->proofRecords());
    self::assertFalse($this->db->inTransaction());
  }

  public function testLoginDoesNotSwallowDatabaseFailure(): void {
    $this->enable(); $this->create(); new Settings([]);
    [$controller, $request, $state] = $this->loginContext();
    $this->db->schema()->dropTable('famtastic_event'); // In-memory SQLite only.
    $error = NULL;
    try { $controller->login($request); }
    catch (\Drupal\Core\Database\DatabaseExceptionWrapper $caught) { $error = $caught; }
    self::assertNotNull($error);
    self::assertSame(0, $state->finalized); self::assertSame(0, $state->cleared);
    self::assertFalse($this->db->inTransaction());
  }
}
