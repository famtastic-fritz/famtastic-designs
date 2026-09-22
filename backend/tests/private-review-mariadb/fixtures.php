<?php
declare(strict_types=1);

use Drupal\Core\Database\Query\Select;
use Drupal\Core\Entity\{EntityStorageInterface, EntityTypeManagerInterface};
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Uuid\Php;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\famtastic_pipeline\Controller\WebsiteRequestProofController;
use Drupal\famtastic_pipeline\Service\{CustomerPortalService, FullSiteReviewService, OperationalLedger};

/** Same validated PDO; only observe AFTER real SQL, never replace query/results. */
final class ReviewConnection extends Drupal\mysql\Driver\Database\mysql\Connection {
  public ?Closure $afterRead = NULL;
  public function select($table, $alias = NULL, array $options = []) {
    $query = new ReviewSelect($this, $table, $alias, $options);
    $query->sourceTable = is_string($table) ? $table : '';
    return $query;
  }
}
final class ReviewSelect extends Select {
  public string $sourceTable = '';
  private bool $locking = FALSE;
  public function forUpdate($set = TRUE) { $this->locking = (bool) $set; return parent::forUpdate($set); }
  public function execute() {
    $result = parent::execute();
    if ($hook = $this->connection->afterRead) $hook($this->sourceTable, $this->locking);
    return $result;
  }
}

/** Interface doubles ONLY: actual final services + actual MySQL transactions. */
final class ReviewMocks extends PHPUnit\Framework\TestCase {
  public function stub(string $class): object { return $this->createMock($class); }
}
final class ReviewServices {
  public FullSiteReviewService $reviews;
  public CustomerPortalService $portal;
  public WebsiteRequestProofController $controller;
  public OperationalLedger $ledger;
  public function __construct(ReviewConnection $db, ReviewFiles $files) {
    $mocks = new ReviewMocks('unusedFixtureFactory');
    $clock = new ProofClock(); $uuid = new Php();
    $this->ledger = new OperationalLedger($db, $clock);
    $account = $mocks->stub(AccountProxyInterface::class);
    $account->method('id')->willReturn(191);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->with('administer famtastic pipeline')->willReturn(TRUE);
    $filesystem = $mocks->stub(FileSystemInterface::class);
    $filesystem->method('realpath')->with('private://')->willReturn($files->path('private'));
    $local = static function (string $uri) use ($files): string {
      proofNeed(preg_match('~^private://famtastic-request-assets/[a-f0-9-]{36}(?:/[a-f0-9]{20}-reference\.png)?$~', $uri) === 1, 'unexpected_upload_uri');
      return $files->path('private/' . substr($uri, strlen('private://')));
    };
    $filesystem->method('prepareDirectory')->willReturnCallback(static function (string $uri) use ($db, $local): bool {
      proofNeed(!$db->inTransaction(), 'preparation_in_metadata_transaction');
      $path = $local($uri);
      return is_dir($path) || mkdir($path, 0700, TRUE);
    });
    $filesystem->method('saveData')->willReturnCallback(static function ($bytes, $uri) use ($db, $local, $files): string {
      proofNeed(!$db->inTransaction() && $bytes === file_get_contents($files->path('upload.png')), 'unexpected_upload_bytes_or_transaction');
      $path = $local($uri); $files->write(substr($path, strlen($files->root) + 1), $bytes); return $uri;
    });
    $storage = $mocks->stub(EntityStorageInterface::class);
    $storage->method('create')->willReturnCallback(static function (array $values) use ($mocks, $db, $local): object {
      proofNeed($db->inTransaction() && $values['uid'] === 191 && is_file($local($values['uri'])), 'unguarded_file_metadata');
      $id = 0; $permanent = FALSE; $file = $mocks->stub(FileInterface::class);
      $file->method('setPermanent')->willReturnCallback(static function () use (&$permanent): void { $permanent = TRUE; });
      $file->method('id')->willReturnCallback(static function () use (&$id): int { return $id; });
      $file->method('save')->willReturnCallback(static function () use ($db, $values, &$id, &$permanent): int {
        proofNeed($db->inTransaction() && $permanent && $id === 0, 'unguarded_file_save');
        $id = (int) $db->insert('fixture_file_metadata')->fields(['uri' => $values['uri']])->execute(); return 1;
      });
      return $file;
    });
    $entities = $mocks->stub(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static function (string $type) use ($storage): object {
      proofNeed($type === 'file', 'unexpected_entity_storage'); return $storage;
    });
    $usage = $mocks->stub(FileUsageInterface::class);
    $usage->method('add')->willReturnCallback(static function ($file, $module, $type, $id) use ($db): void {
      proofNeed($db->inTransaction() && $module === 'famtastic_pipeline' && $type === 'website_request' && $id > 0 && $file->id() > 0, 'unguarded_file_usage');
      $db->insert('fixture_file_usage')->fields(['id' => (int) $file->id()])->execute();
    });
    $this->portal = (new ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $db, 'time' => $clock, 'ledger' => $this->ledger, 'entities' => $entities] as $property => $value) (new ReflectionProperty($this->portal, $property))->setValue($this->portal, $value);
    $this->controller = (new ReflectionClass(WebsiteRequestProofController::class))->newInstanceWithoutConstructor();
    foreach (['database' => $db, 'portal' => $this->portal, 'account' => $account, 'fileSystem' => $filesystem, 'entities' => $entities, 'fileUsage' => $usage, 'uuid' => $uuid] as $property => $value) (new ReflectionProperty($this->controller, $property))->setValue($this->controller, $value);
    $this->reviews = new FullSiteReviewService($db, $filesystem, $clock, $account, $this->ledger, $uuid);
  }
}

function reviewTables(): array {
  $schema = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema() + _famtastic_pipeline_automation_schema();
  $names = ['famtastic_customer', 'famtastic_organization', 'famtastic_membership', 'famtastic_project_request', 'famtastic_build_run',
    'famtastic_event', 'famtastic_portal_activity', 'famtastic_notification_outbox', 'famtastic_job', 'famtastic_request_asset'];
  return array_intersect_key($schema, array_flip($names)) + [
    'fixture_file_metadata' => ['fields' => ['id' => ['type' => 'serial', 'not null' => TRUE], 'uri' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE]], 'primary key' => ['id']],
    'fixture_file_usage' => ['fields' => ['id' => ['type' => 'int', 'not null' => TRUE]], 'primary key' => ['id']],
    'fixture_owner' => ['fields' => ['id' => ['type' => 'int', 'not null' => TRUE], 'binding' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE]], 'primary key' => ['id']],
  ];
}

/** Only pr_ fixture tables in the existing generated DB; no unprefixed resets. */
function reviewSchema(ReviewConnection $db, array $config): void {
  proofNeed(!$db->inTransaction(), 'schema_inside_transaction');
  $tables = reviewTables();
  $worker = Drupal\famtastic_pipeline\Service\WorkerCoordinatorSchema::tables()
    + array_intersect_key(_famtastic_pipeline_automation_schema(), array_flip(['famtastic_job', 'famtastic_event', 'famtastic_exception']));
  $existing = $db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchCol();
  $prefixed = array_map(static fn(string $t): string => 'pr_' . $t, array_keys($tables));
  proofNeed(!array_diff($existing, [...array_keys($worker), 'semaphore', 'proof_harness_owner', ...$prefixed]), 'unexpected_tables_do_not_adopt');
  $binding = hash('sha256', $config['run'] . ':' . reviewLineage()['source_commit'] . ':' . json_encode($tables, JSON_THROW_ON_ERROR));
  if (array_intersect($existing, $prefixed)) {
    proofNeed(!array_diff($prefixed, $existing), 'partial_review_schema_do_not_adopt');
    $owner = $db->select('fixture_owner', 'o')->fields('o')->execute()->fetchAll(PDO::FETCH_ASSOC);
    proofNeed(count($owner) === 1 && (int) $owner[0]['id'] === 1 && hash_equals($binding, $owner[0]['binding']), 'review_schema_owner_mismatch');
  }
  else {
    foreach ($tables as $name => $schema) $db->schema()->createTable($name, $schema);
    $db->insert('fixture_owner')->fields(['id' => 1, 'binding' => $binding])->execute();
  }
  $engines = $db->query('SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchCol();
  proofNeed($engines === ['InnoDB'], 'nontransactional_fixture_tables');
}
