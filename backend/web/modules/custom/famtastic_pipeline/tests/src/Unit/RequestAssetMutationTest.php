<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\{EntityTypeManagerInterface, EntityStorageInterface};
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Controller\WebsiteRequestProofController;
use Drupal\famtastic_pipeline\Service\{CustomerPortalService, OperationalLedger};
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Actual final portal/controller and SQLite transactions, no Drupal site boot. */
final class RequestAssetMutationTest extends UnitTestCase {
  private Connection $db;
  private CustomerPortalService $portal;
  private WebsiteRequestProofController $controller;
  private string $uploadPath;
  private int $prepared = 0;
  private int $uses = 0;
  private ?\Closure $duringPreparation = NULL;
  private bool $invalidReconciliation = FALSE;
  private const REQUEST = '00000000-0000-0000-0000-000000000001';
  private const ASSET = '00000000-0000-0000-0000-000000000002';

  protected function setUp(): void {
    parent::setUp();
    $vendor = getenv('FAMTASTIC_BACKEND_VENDOR') ?: dirname(__DIR__, 7) . '/vendor';
    $loader = require $vendor . '/autoload.php';
    $loader->addPsr4('Drupal\\file\\', dirname($vendor) . '/web/core/modules/file/src', TRUE);
    $loader->addPsr4('Drupal\\user\\', dirname($vendor) . '/web/core/modules/user/src', TRUE);
    $o = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new class(Connection::open($o), $o) extends Connection {
      public ?\Closure $beforeRequestLock = NULL;
      public function select($table, $alias = NULL, array $options = []) {
        if ($table === 'famtastic_project_request' && $this->inTransaction() && $this->beforeRequestLock) {
          $callback = $this->beforeRequestLock; $this->beforeRequestLock = NULL; $callback();
        }
        return parent::select($table, $alias, $options);
      }
    };
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema() + _famtastic_pipeline_automation_schema();
    foreach (['famtastic_project_request', 'famtastic_membership', 'famtastic_customer', 'famtastic_request_asset', 'famtastic_event'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    // Storage doubles below persist in the SAME transaction, not just counters.
    $this->db->query('CREATE TABLE test_file_metadata (id INTEGER PRIMARY KEY)');
    $this->db->query('CREATE TABLE test_file_usage (id INTEGER PRIMARY KEY)');
    $this->db->insert('famtastic_customer')->fields(['id' => 1, 'public_id' => self::REQUEST, 'uid' => 1, 'display_name' => 'Synthetic', 'email' => 'fixture@example.test', 'created' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 1, 'organization_id' => 2, 'status' => 'active', 'created' => 1])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 1, 'public_id' => self::REQUEST, 'customer_id' => 1, 'organization_id' => 2,
      'project_name' => 'Synthetic reference test', 'business_name' => 'Synthetic only', 'intake_data' => '{}', 'created' => 1, 'changed' => 1])->execute();
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getCurrentTime')->willReturn(1790010000);
    $clock->method('getRequestTime')->willReturn(1790010000);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $file = $this->createMock(FileInterface::class); $file->method('id')->willReturn(7);
    $file->method('save')->willReturnCallback(function () {
      self::assertTrue($this->db->inTransaction(), 'File metadata must be guarded.');
      $this->db->insert('test_file_metadata')->fields(['id' => 7])->execute(); return 1;
    });
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('create')->willReturnCallback(function (array $values) use ($file) {
      self::assertTrue($this->db->inTransaction()); self::assertSame(1, $values['uid']);
      self::assertStringStartsWith('private://famtastic-request-assets/' . self::REQUEST . '/', $values['uri']); return $file;
    });
    $entities->method('getStorage')->willReturnCallback(function (string $type) use ($storage) {
      if ($type === 'file') return $storage;
      if ($this->invalidReconciliation) throw new \InvalidArgumentException('Synthetic reconciliation invalid input.');
      throw new \RuntimeException('Synthetic selected reconciliation failure.');
    });
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'time' => $clock, 'ledger' => new OperationalLedger($this->db, $clock), 'entities' => $entities] as $property => $value) {
      (new \ReflectionProperty($this->portal, $property))->setValue($this->portal, $value);
    }
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE); $account->method('id')->willReturn(1);
    $fs = $this->createMock(FileSystemInterface::class); $fs->method('prepareDirectory')->willReturn(TRUE);
    $fs->method('saveData')->willReturnCallback(function ($bytes, $destination) {
      self::assertFalse($this->db->inTransaction(), 'Private file preparation is outside the metadata transaction.');
      $this->prepared++;
      if ($this->duringPreparation) ($this->duringPreparation)();
      return $destination;
    });
    $usage = $this->createMock(FileUsageInterface::class);
    $usage->method('add')->willReturnCallback(function () {
      self::assertTrue($this->db->inTransaction()); $this->uses++;
      $this->db->insert('test_file_usage')->fields(['id' => 7])->execute();
    });
    $uuid = $this->createMock(UuidInterface::class); $uuid->method('generate')->willReturn(self::ASSET);
    $this->controller = (new \ReflectionClass(WebsiteRequestProofController::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'portal' => $this->portal, 'account' => $account, 'fileSystem' => $fs,
      'entities' => $entities, 'fileUsage' => $usage, 'uuid' => $uuid] as $property => $value) {
      (new \ReflectionProperty($this->controller, $property))->setValue($this->controller, $value);
    }
    $this->uploadPath = tempnam(sys_get_temp_dir(), 'famtastic-asset-test-');
    file_put_contents($this->uploadPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));
  }

  protected function tearDown(): void {
    if (isset($this->uploadPath) && is_file($this->uploadPath)) unlink($this->uploadPath);
    parent::tearDown();
  }
  private function input(): Request {
    return Request::create('https://example.test/reference', 'POST', ['ownership_confirmed' => '1'], files: [
      'asset' => new UploadedFile($this->uploadPath, 'reference.png', 'image/png', UPLOAD_ERR_OK, TRUE),
    ]);
  }
  private function asset(string $status = 'active'): void {
    $this->db->insert('famtastic_request_asset')->fields(['id' => 1, 'public_id' => self::ASSET, 'website_request_id' => 1, 'customer_id' => 1, 'file_id' => 7,
      'original_name' => 'reference.png', 'mime_type' => 'image/png', 'size_bytes' => filesize($this->uploadPath), 'sha256' => hash_file('sha256', $this->uploadPath),
      'ownership_confirmed' => 1, 'status' => $status, 'created' => 1, 'changed' => 1])->execute();
  }
  private function row(): array|false { return $this->db->select('famtastic_request_asset', 'a')->fields('a')->condition('id', 1)->execute()->fetchAssoc(); }
  private function tableCount(string $table): int { return (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField(); }
  private function selected(): void { $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'proof_campaign_id' => 1])->condition('id', 1)->execute(); }
  private function rejects(callable $call, string $message): void {
    try { $call(); } catch (\Throwable $e) { self::assertStringContainsString($message, $e->getMessage()); return; }
    self::fail('Expected rejection: ' . $message);
  }

  public function testUploadAndDuplicatePreserveOriginalConsentsAndOneAsset(): void {
    $first = $this->controller->uploadAsset($this->input(), self::REQUEST);
    self::assertSame(201, $first->getStatusCode()); self::assertSame(1, $this->prepared); self::assertSame(1, $this->uses);
    $before = $this->row();
    $request = $this->input(); $request->request->set('ai_transformation_consent', '1');
    $again = $this->controller->uploadAsset($request, self::REQUEST);
    self::assertSame(200, $again->getStatusCode()); self::assertTrue(json_decode($again->getContent(), TRUE)['duplicate']);
    self::assertSame($before, $this->row()); self::assertSame(1, $this->tableCount('famtastic_request_asset'));
    self::assertSame(1, $this->prepared); self::assertSame(1, $this->uses); self::assertFalse($this->db->inTransaction());
  }

  #[DataProvider('preparationChanges')]
  public function testUploadRevalidatesAfterPrivatePreparation(string $change, int $status): void {
    $this->duringPreparation = function () use ($change): void {
      if ($change === 'member') $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->execute();
      elseif ($change === 'owner') $this->db->update('famtastic_project_request')->fields(['customer_id' => 99])->execute();
      else $this->asset($change);
    };
    $result = $this->controller->uploadAsset($this->input(), self::REQUEST);
    self::assertSame($status, $result->getStatusCode()); self::assertSame(1, $this->prepared); self::assertSame(0, $this->uses);
    self::assertSame(0, $this->tableCount('test_file_metadata')); self::assertSame(0, $this->tableCount('test_file_usage'));
    self::assertSame(in_array($change, ['active', 'withdrawn'], TRUE) ? 1 : 0, $this->tableCount('famtastic_request_asset'));
    if ($change === 'withdrawn') self::assertSame('withdrawn', $this->row()['status']);
    self::assertFalse($this->db->inTransaction());
  }
  public static function preparationChanges(): iterable {
    yield 'membership removed' => ['member', 404]; yield 'owner changed' => ['owner', 404];
    yield 'concurrent same bytes' => ['active', 200]; yield 'withdrawn same bytes' => ['withdrawn', 409];
  }

  public function testWithdrawnDuplicateDoesNotPrepareFileOrRestorePermission(): void {
    $this->asset('withdrawn'); $before = $this->row();
    self::assertSame(409, $this->controller->uploadAsset($this->input(), self::REQUEST)->getStatusCode());
    self::assertSame($before, $this->row()); self::assertSame(0, $this->prepared); self::assertSame(0, $this->uses);
  }

  #[DataProvider('advisoryDuplicateChanges')]
  public function testAdvisoryDuplicateIsNotAuthority(string $change, int $status): void {
    $this->asset();
    $this->db->beforeRequestLock = function () use ($change): void {
      if ($change === 'member') $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->execute();
      else $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute();
    };
    self::assertSame($status, $this->controller->uploadAsset($this->input(), self::REQUEST)->getStatusCode());
    self::assertSame(0, $this->prepared); self::assertSame(0, $this->tableCount('test_file_metadata'));
    if ($change !== 'member') self::assertSame('withdrawn', $this->row()['status']);
    self::assertFalse($this->db->inTransaction());
  }
  public static function advisoryDuplicateChanges(): iterable { yield ['member', 404]; yield ['withdrawn', 409]; }

  public function testWithdrawalAndAuditAreExactlyOnce(): void {
    $this->asset(); $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET);
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event'));
    $before = $this->row(); $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET);
    self::assertSame($before, $this->row()); self::assertSame(1, $this->tableCount('famtastic_event')); self::assertFalse($this->db->inTransaction());
  }

  public function testWithdrawalRemainsCommittedAfterReconciliationFailure(): void {
    $this->asset(); $this->selected();
    $this->rejects(fn() => $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET), 'Synthetic selected reconciliation failure');
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event')); self::assertFalse($this->db->inTransaction());
    $this->rejects(fn() => $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET), 'Synthetic selected reconciliation failure');
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event'));
  }

  public function testUploadMetadataRemainsCommittedAfterReconciliationFailure(): void {
    $this->selected();
    $this->rejects(fn() => $this->controller->uploadAsset($this->input(), self::REQUEST), 'Synthetic selected reconciliation failure');
    self::assertSame('active', $this->row()['status']); self::assertSame(1, $this->uses); self::assertFalse($this->db->inTransaction());
    self::assertSame(1, $this->tableCount('test_file_metadata')); self::assertSame(1, $this->tableCount('test_file_usage'));
  }

  public function testUploadUsageFailureRollsBackAllManagedMetadata(): void {
    $this->db->insert('test_file_usage')->fields(['id' => 7])->execute();
    $this->rejects(fn() => $this->controller->uploadAsset($this->input(), self::REQUEST), 'test_file_usage');
    self::assertSame(1, $this->prepared); self::assertSame(0, $this->tableCount('famtastic_request_asset'));
    self::assertSame(0, $this->tableCount('test_file_metadata')); self::assertSame(1, $this->tableCount('test_file_usage'));
    self::assertFalse($this->db->inTransaction());
  }

  public function testWithdrawalControllerSuccessAndPostCommitInvalidInput(): void {
    $this->asset();
    self::assertSame(200, $this->controller->withdrawAsset($this->input(), self::REQUEST, self::ASSET)->getStatusCode());
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event'));
    $this->selected(); $this->invalidReconciliation = TRUE;
    self::assertSame(404, $this->controller->withdrawAsset($this->input(), self::REQUEST, self::ASSET)->getStatusCode());
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event'));
    self::assertFalse($this->db->inTransaction());
  }

  public function testWithdrawalControllerRuntimeErrorCannotUndoRevocation(): void {
    $this->asset(); $this->selected();
    $this->rejects(fn() => $this->controller->withdrawAsset($this->input(), self::REQUEST, self::ASSET), 'Synthetic selected reconciliation failure');
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event')); self::assertFalse($this->db->inTransaction());
  }

  public function testFreshWithdrawalSurvivesController404FromReconciliation(): void {
    $this->asset(); $this->selected(); $this->invalidReconciliation = TRUE;
    self::assertSame(404, $this->controller->withdrawAsset($this->input(), self::REQUEST, self::ASSET)->getStatusCode());
    self::assertSame('withdrawn', $this->row()['status']); self::assertSame(1, $this->tableCount('famtastic_event'));
    self::assertFalse($this->db->inTransaction());
  }

  #[DataProvider('ownershipChanges')]
  public function testWithdrawalCannotCrossCurrentOwnership(string $change): void {
    $this->asset(); $before = $this->row();
    if ($change === 'member') $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->execute();
    elseif ($change === 'request') $this->db->update('famtastic_project_request')->fields(['customer_id' => 99])->execute();
    else $this->db->update('famtastic_request_asset')->fields(['customer_id' => 99])->execute();
    $before = $this->row();
    $this->rejects(fn() => $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET), 'not found');
    self::assertSame($before, $this->row()); self::assertSame(0, $this->tableCount('famtastic_event')); self::assertFalse($this->db->inTransaction());
  }
  public static function ownershipChanges(): iterable { foreach (['member', 'request', 'asset'] as $change) yield $change => [$change]; }

  public function testAuditFailureRollsBackWithdrawalAndNestedWritersRejectBeforeEffects(): void {
    $this->asset(); $before = $this->row();
    $outer = $this->db->startTransaction();
    $this->rejects(fn() => $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET), 'root transaction');
    $this->rejects(fn() => $this->controller->uploadAsset($this->input(), self::REQUEST), 'root transaction');
    self::assertSame($before, $this->row()); self::assertSame(0, $this->prepared); $outer->rollBack();
    $this->db->schema()->dropTable('famtastic_event');
    $this->rejects(fn() => $this->portal->withdrawWebsiteRequestAsset(1, self::REQUEST, self::ASSET), 'famtastic_event');
    self::assertSame($before, $this->row()); self::assertFalse($this->db->inTransaction());
  }

  public function testLockedOwnerReadRequiresTransaction(): void {
    $this->rejects(fn() => $this->portal->ownedWebsiteRequest(1, self::REQUEST, TRUE), 'requires a transaction');
    $tx = $this->db->startTransaction();
    self::assertSame(1, (int) $this->portal->ownedWebsiteRequest(1, self::REQUEST, TRUE)['id']);
    self::assertNull($this->portal->ownedWebsiteRequest(99, self::REQUEST, TRUE)); $tx->rollBack();
  }
}
