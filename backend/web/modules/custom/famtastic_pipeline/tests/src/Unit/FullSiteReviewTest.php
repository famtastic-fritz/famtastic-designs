<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Controller\FullSiteReviewController;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\famtastic_pipeline\Service\FullSiteReviewPackage;
use Drupal\famtastic_pipeline\Service\FullSiteReviewRenderer;
use Drupal\famtastic_pipeline\Service\FullSiteReviewService;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
// Use this worktree's module when the reusable Drupal test runtime lives elsewhere.
spl_autoload_register(static function (string $class): void {
  $prefix = 'Drupal\\famtastic_pipeline\\';
  if (!str_starts_with($class, $prefix)) return;
  $file = dirname(__DIR__, 3) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
  if (is_file($file)) require_once $file;
}, TRUE, TRUE);

/** Real SQLite writes and real private file reads; no transports or accounts. */
final class FullSiteReviewTest extends UnitTestCase {
  private Connection $db;
  private FullSiteReviewService $reviews;
  private string $directory;
  private array $manifest;
  private const PUBLIC_ID = '11111111-2222-4333-8444-555555555555';

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema() + _famtastic_pipeline_lifecycle_schema();
    foreach (['famtastic_customer', 'famtastic_organization', 'famtastic_membership', 'famtastic_project_request', 'famtastic_build_run', 'famtastic_event', 'famtastic_portal_activity', 'famtastic_notification_outbox', 'famtastic_job', 'famtastic_private_offer', 'famtastic_request_asset'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    $this->db->insert('famtastic_customer')->fields(['id' => 91, 'uid' => 191, 'public_id' => 'fixture-customer', 'email' => 'fixture@example.invalid', 'display_name' => 'Fixture', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_organization')->fields(['id' => 92, 'public_id' => 'fixture-organization', 'name' => 'Fixture organization', 'status' => 'active', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['organization_id' => 92, 'customer_id' => 91, 'role' => 'owner', 'status' => 'active', 'created' => 1])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 93, 'public_id' => self::PUBLIC_ID, 'customer_id' => 91, 'organization_id' => 92, 'project_name' => 'Fixture', 'status' => 'draft', 'proof_review_status' => 'not_started', 'intake_data' => '{"notes":"Original customer words"}', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_build_run')->fields(['build_key' => 'build-dna:fixture-full-site', 'status' => 'completed', 'source_sha' => str_repeat('a', 40), 'artifact_checksum' => str_repeat('b', 64), 'created' => 1, 'changed' => 1])->execute();
    $this->directory = sys_get_temp_dir() . '/full-site-review-' . bin2hex(random_bytes(8));
    mkdir($this->directory . '/source/assets/fonts', 0700, TRUE);
    mkdir($this->directory . '/source/services', 0700, TRUE);
    mkdir($this->directory . '/source/review-documents', 0700, TRUE);
    mkdir($this->directory . '/private', 0700);
    $bytes = [
      'index.html' => '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/site.css"><script defer src="assets/site.js"></script></head><body><a href="services/">Services</a><a href="services/?plan=starter&amp;service=legal-courier#request">Starter plan</a><img src="/assets/photo.png"><output id="planner">Not initialized</output></body></html>',
      'services/index.html' => '<!doctype html><html><head><link rel="stylesheet" href="../assets/site.css"></head><body><h1>Services</h1><a href="../">Home</a><a href="?plan=growth%20plan">Query only</a><a href="https://example.invalid/" target="_blank">External</a></body></html>',
      'assets/site.css' => '@font-face {font-family: Fixture;src:url("fonts/example.woff2")} .photo {background:url(photo.png)}',
      'assets/site.js' => 'const draft = true && false; const subject = "Plan inquiry — Miami"; document.body.dataset.ready = "yes";',
      'assets/photo.png' => "\x89PNG\r\n\x1a\nfixture",
      'assets/fonts/example.woff2' => 'wOF2fixture',
      'review-documents/design.txt' => 'The customer design guide.',
    ];
    $types = ['html' => 'text/html', 'css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'woff2' => 'font/woff2', 'txt' => 'text/plain'];
    $files = [];
    foreach ($bytes as $path => $content) {
      file_put_contents($this->directory . '/source/' . $path, $content);
      $extension = pathinfo($path, PATHINFO_EXTENSION);
      $files[] = ['path' => $path, 'role' => $extension === 'html' ? 'page' : ($extension === 'txt' ? 'document' : 'asset'), 'media_type' => $types[$extension], 'sha256' => hash('sha256', $content), 'bytes' => strlen($content)];
    }
    $this->manifest = ['schema' => FullSiteReviewPackage::SCHEMA, 'review_id' => 'fixture-version-one', 'title' => 'Fixture full website', 'entry_path' => 'index.html', 'source_commit' => str_repeat('a', 40), 'build_id' => 'fixture-full-site', 'pages' => [['label' => 'Home', 'path' => 'index.html'], ['label' => 'Services', 'path' => 'services/index.html']], 'documents' => [['label' => 'Design guide', 'path' => 'review-documents/design.txt']], 'files' => $files, 'research' => ['overview' => 'Source-based fixture research', 'researched_at' => '2026-09-21', 'sources' => [['title' => 'Example source', 'url' => 'https://example.invalid/source']]]];
    $clock = $this->createMock(TimeInterface::class); $clock->method('getRequestTime')->willReturn(1790000000);
    $filesystem = $this->createMock(FileSystemInterface::class); $filesystem->method('realpath')->with('private://')->willReturn($this->directory . '/private');
    $account = $this->createMock(AccountProxyInterface::class); $account->method('hasPermission')->with('administer famtastic pipeline')->willReturn(TRUE); $account->method('id')->willReturn(1);
    $this->reviews = new FullSiteReviewService($this->db, $filesystem, $clock, $account, new OperationalLedger($this->db, $clock), new Php());
  }

  protected function tearDown(): void {
    if (isset($this->directory) && is_dir($this->directory)) {
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($iterator as $file) { if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
      rmdir($this->directory);
    }
    parent::tearDown();
  }

  private function attach(?array $manifest = NULL): array {
    return $this->reviews->attach(self::PUBLIC_ID, 91, 92, $this->directory . '/source', $manifest ?? $this->manifest, 'codex:fixture', 'Synthetic test authorization');
  }

  public function testQuietImmutableAttachmentPreservesDraftAndIntake(): void {
    $first = $this->attach(); $second = $this->attach();
    self::assertTrue($first['newly_attached']); self::assertFalse($second['newly_attached']);
    self::assertSame($first['manifest_sha256'], $second['manifest_sha256']);
    $row = $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 93)->execute()->fetchAssoc();
    self::assertSame('draft', $row['status']); self::assertSame('not_started', $row['proof_review_status']);
    self::assertNull($row['project_id']); self::assertNull($row['commerce_order_id']); self::assertSame('', $row['selected_proof_direction']);
    $intake = json_decode($row['intake_data'], TRUE); self::assertSame('Original customer words', $intake['notes']);
    self::assertFalse($intake['staff_assisted_brief']['customer_supplied']);
    self::assertSame(1, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
    foreach (['famtastic_notification_outbox', 'famtastic_job'] as $table) self::assertSame(0, (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField());
    self::assertSame('The customer design guide.', $this->reviews->read(91, self::PUBLIC_ID, 'review-documents/design.txt')['bytes']);
    self::assertSame(2, $first['review']['page_count']); self::assertArrayNotHasKey('files', $first['review']);
    self::assertArrayNotHasKey('customer_id', $first['review']);
  }

  public function testConflictingVersionIsRejectedWithoutChangingCurrentReview(): void {
    $this->attach(); $manifest = $this->manifest; $manifest['title'] = 'Changed immutable title';
    $this->expectExceptionMessage('immutable review id'); $this->attach($manifest);
  }

  public function testSafeProjectionAndNoCustomerMutationOrProofDispatchAfterFullSiteAttachment(): void {
    $attached = $this->attach();
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    $clock = $this->createMock(TimeInterface::class); $clock->method('getRequestTime')->willReturn(1790000001);
    foreach (['database' => $this->db, 'time' => $clock, 'configFactory' => $this->getConfigFactoryStub(['famtastic_pipeline.settings' => ['frontend_base_url' => 'https://example.invalid']])] as $property => $value) (new \ReflectionProperty($portal, $property))->setValue($portal, $value);
    $row = $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 93)->execute()->fetchAssoc();
    $result = (new \ReflectionMethod($portal, 'serializeWebsiteRequest'))->invoke($portal, $row);
    self::assertSame('draft', $result['status']);
    self::assertSame($attached['review'], $result['full_site_review']);
    self::assertArrayNotHasKey('intake_data', $result);
    self::assertArrayNotHasKey('full_site_review', $result['intake']['staff_assisted_brief']);
    self::assertStringNotContainsString('manifest_sha256', json_encode($result, JSON_THROW_ON_ERROR));
    foreach (['save', 'submit'] as $action) {
      try { $portal->updateWebsiteRequest(91, self::PUBLIC_ID, ['project_name' => 'Fixture', 'primary_goal' => 'Updated customer goal', 'products_services' => 'Delivery services', 'action' => $action, 'staff_assisted_brief' => ['full_site_review' => NULL]]); self::fail('Customer changed a full-site review into a proof request.'); }
      catch (\InvalidArgumentException $error) { self::assertStringContainsString('Use Messages', $error->getMessage()); }
    }
    try { (new \ReflectionMethod($portal, 'submitClaimedDeepDiveRequest'))->invoke($portal, 91, 93); self::fail('Deep dive resumed a full-site review.'); }
    catch (\InvalidArgumentException $error) { self::assertStringContainsString('Use Messages', $error->getMessage()); }
    self::assertSame($row, $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 93)->execute()->fetchAssoc());
    // Even a preexisting submitted state cannot enter the staff dispatch path,
    // worker context or central queue helper with a stale intake snapshot.
    $this->db->update('famtastic_project_request')->fields(['status' => 'submitted'])->condition('id', 93)->execute();
    foreach ([fn() => $portal->sendWebsiteRequestToSiteStudio(91, self::PUBLIC_ID), fn() => $portal->websiteRequestProofContext(93), fn() => (new \ReflectionMethod($portal, 'queueWebsiteRequestProofJob'))->invoke($portal, 93, 0, self::PUBLIC_ID, [])] as $dispatch) {
      try { $dispatch(); self::fail('Full-site review entered proof workflow.'); }
      catch (\InvalidArgumentException $error) { self::assertStringContainsString('Use Messages', $error->getMessage()); }
    }
    $stored = json_decode($this->db->select('famtastic_project_request', 'r')->fields('r', ['intake_data'])->condition('id', 93)->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertSame('Original customer words', $stored['notes']);
    self::assertSame($attached['manifest_sha256'], $stored['staff_assisted_brief']['full_site_review']['manifest_sha256']);
    self::assertSame('The customer design guide.', $this->reviews->read(91, self::PUBLIC_ID, 'review-documents/design.txt')['bytes']);
    foreach (['famtastic_notification_outbox', 'famtastic_job'] as $table) self::assertSame(0, (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField());
  }

  public function testStaffDraftAndAttachmentAreAtomicIdempotentAndNotCustomerAuthored(): void {
    $args = [91, 92, 'Staff full website', 'owner-full-site-20260921', $this->directory . '/source', $this->manifest, 'codex:fixture', 'Synthetic owner instruction'];
    $first = $this->reviews->createAndAttach(...$args); $again = $this->reviews->createAndAttach(...$args);
    self::assertTrue($first['newly_created']); self::assertTrue($first['newly_attached']);
    self::assertFalse($again['newly_created']); self::assertFalse($again['newly_attached']);
    self::assertSame($first['request_public_id'], $again['request_public_id']);
    $row = $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', $first['request_id'])->execute()->fetchAssoc();
    self::assertSame('draft', $row['status']); self::assertNull($row['submitted_at']); self::assertNull($row['prospect_id']);
    self::assertNull($row['commerce_order_id']); self::assertNull($row['project_id']);
    $intake = json_decode($row['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertFalse($intake['staff_assisted_brief']['customer_supplied']);
    self::assertSame('codex:fixture', $intake['staff_assisted_brief']['actor']);
    self::assertArrayNotHasKey('authored_content', $intake); self::assertArrayNotHasKey('request_submission', $intake);
    self::assertSame('The customer design guide.', $this->reviews->read(91, $first['request_public_id'], 'review-documents/design.txt')['bytes']);
    self::assertSame(2, (int) $this->db->select('famtastic_project_request', 'r')->countQuery()->execute()->fetchField());
    self::assertSame(2, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
    foreach (['famtastic_notification_outbox', 'famtastic_job'] as $table) self::assertSame(0, (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField());
    $args[2] = 'Changed binding';
    try { $this->reviews->createAndAttach(...$args); self::fail('Staff draft key changed identity.'); }
    catch (\RuntimeException $error) { self::assertStringContainsString('different binding', $error->getMessage()); }
    $args[2] = 'Staff full website'; $args[3] = 'duplicate-draft-key';
    try { $this->reviews->createAndAttach(...$args); self::fail('Duplicate project created.'); }
    catch (\RuntimeException $error) { self::assertStringContainsString('matching request exists', $error->getMessage()); }
  }

  public function testFailedStaffPackageLeavesNoDraftOrAuditEvent(): void {
    $bad = $this->manifest; $bad['files'][0]['sha256'] = str_repeat('0', 64);
    try { $this->reviews->createAndAttach(91, 92, 'Failed draft', 'failed-fixture', $this->directory . '/source', $bad, 'codex:fixture', 'Synthetic owner instruction'); self::fail('Invalid package was attached.'); }
    catch (\RuntimeException $error) { self::assertStringContainsString('integrity failed', $error->getMessage()); }
    self::assertSame(1, (int) $this->db->select('famtastic_project_request', 'r')->countQuery()->execute()->fetchField());
    self::assertSame(0, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
  }

  public function testExistingProofJobPreventsAttachment(): void {
    $clock = $this->createMock(TimeInterface::class); $clock->method('getRequestTime')->willReturn(1790000000);
    (new OperationalLedger($this->db, $clock))->enqueue('website_proof.generate.v1:request:93:brief:fixture', 'proof.generate', []);
    $this->expectExceptionMessage('existing proof routine'); $this->attach();
  }

  public function testOwnershipAndMembershipAreRecheckedForEveryFile(): void {
    $this->attach();
    try { $this->reviews->read(999, self::PUBLIC_ID, 'assets/site.css'); self::fail('Other customer read private bytes.'); } catch (\RuntimeException $error) { self::assertSame('Review not found.', $error->getMessage()); }
    $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->condition('customer_id', 91)->execute();
    $this->expectExceptionMessage('Review not found.'); $this->reviews->read(91, self::PUBLIC_ID, 'index.html');
  }

  public function testUndeclaredTraversalAndTamperedFilesAreDenied(): void {
    $result = $this->attach();
    foreach (['../index.html', 'assets/%2e%2e/index.html', 'assets\\site.js', '/index.html'] as $path) {
      try { $this->reviews->read(91, self::PUBLIC_ID, $path); self::fail('Unsafe path accepted.'); } catch (\InvalidArgumentException) { self::assertTrue(TRUE); }
    }
    try { $this->reviews->read(91, self::PUBLIC_ID, 'review-documents/private.txt'); self::fail('Undeclared file accepted.'); } catch (\RuntimeException) { self::assertTrue(TRUE); }
    $path = $this->directory . '/private/famtastic-full-site-reviews/' . self::PUBLIC_ID . '/' . $result['manifest_sha256'] . '/assets/site.js';
    file_put_contents($path, str_repeat('x', filesize($path)));
    $this->expectExceptionMessage('integrity failed'); $this->reviews->read(91, self::PUBLIC_ID, 'assets/site.js');
  }

  public function testSymlinkCannotBeImported(): void {
    unlink($this->directory . '/source/assets/site.js');
    file_put_contents($this->directory . '/outside.js', 'outside');
    symlink($this->directory . '/outside.js', $this->directory . '/source/assets/site.js');
    $this->expectExceptionMessage('symlinks'); $this->attach();
  }

  public function testSessionControllerIsolatesScriptsAndRewritesEveryPageAndAsset(): void {
    $this->attach();
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $this->db);
    $owner = $this->createMock(AccountProxyInterface::class); $owner->method('isAuthenticated')->willReturn(TRUE); $owner->method('id')->willReturn(191);
    $controller = new FullSiteReviewController($owner, $portal, $this->reviews);
    $response = $controller->file(self::PUBLIC_ID, 'index.html');
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('/full-site/services/index.html', $response->getContent());
    self::assertStringContainsString('data:font/woff2;base64,', $response->getContent());
    self::assertStringContainsString('data:image/png;base64,', $response->getContent());
    self::assertStringContainsString('const draft = true && false;', $response->getContent());
    self::assertStringContainsString('const subject = "Plan inquiry — Miami";', $response->getContent());
    self::assertStringNotContainsString('&mdash;', $response->getContent());
    self::assertStringNotContainsString('src="assets/site.js"', $response->getContent());
    self::assertLessThan(strpos($response->getContent(), 'const draft'), strpos($response->getContent(), 'id="planner"'));
    self::assertStringContainsString('/full-site/services/index.html?plan=starter&amp;service=legal-courier#request', $response->getContent());
    $policy = $response->headers->get('Content-Security-Policy');
    self::assertStringContainsString('sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox', $policy);
    self::assertStringNotContainsString('allow-same-origin', $policy);
    self::assertStringContainsString("connect-src 'none'", $policy);
    self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $interior = $controller->file(self::PUBLIC_ID, 'services', 'index.html');
    self::assertSame(200, $interior->getStatusCode());
    self::assertStringContainsString('/full-site/index.html', $interior->getContent());
    self::assertStringContainsString('/full-site/services/index.html?plan=growth%20plan', $interior->getContent());
    self::assertStringContainsString('rel="noopener noreferrer"', $interior->getContent());
    self::assertSame('wOF2fixture', $controller->file(self::PUBLIC_ID, 'assets', 'fonts', 'example.woff2')->getContent());
    $anonymous = $this->createMock(AccountProxyInterface::class); $anonymous->method('isAuthenticated')->willReturn(FALSE);
    self::assertSame(404, (new FullSiteReviewController($anonymous, $portal, $this->reviews))->file(self::PUBLIC_ID, 'index.html')->getStatusCode());
    $other = $this->createMock(AccountProxyInterface::class); $other->method('isAuthenticated')->willReturn(TRUE); $other->method('id')->willReturn(999);
    self::assertSame(404, (new FullSiteReviewController($other, $portal, $this->reviews))->file(self::PUBLIC_ID, 'index.html')->getStatusCode());
    self::assertSame(404, (new FullSiteReviewController($other, $portal, $this->reviews))->file(self::PUBLIC_ID, 'assets', 'fonts', 'example.woff2')->getStatusCode());
    self::assertSame(404, $controller->adminFile(93, 'index.html')->getStatusCode());
  }

  public function testExplicitStaffReviewDoesNotBecomeACustomerSessionOrMutateState(): void {
    $this->attach();
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $this->db);
    $staff = $this->createMock(AccountProxyInterface::class);
    $staff->method('isAuthenticated')->willReturn(TRUE); $staff->method('id')->willReturn(1);
    $staff->method('hasPermission')->with('administer famtastic pipeline')->willReturn(TRUE);
    $controller = new FullSiteReviewController($staff, $portal, $this->reviews);
    $response = $controller->adminFile(93, 'index.html');
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('/web/admin/famtastic/website-request/93/full-site/services/index.html?plan=starter', $response->getContent());
    self::assertStringNotContainsString('/api/customer/', $response->getContent());
    self::assertSame(200, $controller->adminFile(93, 'services', 'index.html')->getStatusCode());
    self::assertSame('wOF2fixture', $controller->adminFile(93, 'assets', 'fonts', 'example.woff2')->getContent());
    self::assertSame(404, $controller->adminFile(999, 'index.html')->getStatusCode());
    self::assertSame(404, $controller->file(self::PUBLIC_ID, 'index.html')->getStatusCode());
    self::assertSame('draft', $this->db->select('famtastic_project_request', 'r')->fields('r', ['status'])->condition('id', 93)->execute()->fetchField());
    self::assertSame(1, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
    foreach (['famtastic_notification_outbox', 'famtastic_job'] as $table) self::assertSame(0, (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField());
  }

  public function testAResponseCannotMixManifestVersionsWhenAttachmentChangesMidRead(): void {
    $this->attach();
    $first = $this->db->select('famtastic_project_request', 'r')->fields('r', ['intake_data'])->condition('id', 93)->execute()->fetchField();
    $new = $this->manifest; $new['review_id'] = 'fixture-version-two'; $new['title'] = 'New immutable version'; $this->attach($new);
    $second = $this->db->select('famtastic_project_request', 'r')->fields('r', ['intake_data'])->condition('id', 93)->execute()->fetchField();
    $this->db->update('famtastic_project_request')->fields(['intake_data' => $first])->condition('id', 93)->execute();
    $clock = $this->createMock(TimeInterface::class);
    $staff = $this->createMock(AccountProxyInterface::class); $staff->method('hasPermission')->willReturn(TRUE);
    $filesystem = $this->createMock(FileSystemInterface::class);
    $changed = FALSE;
    $filesystem->method('realpath')->with('private://')->willReturnCallback(function () use (&$changed, $second): string {
      if (!$changed) { $changed = TRUE; $this->db->update('famtastic_project_request')->fields(['intake_data' => $second])->condition('id', 93)->execute(); }
      return $this->directory . '/private';
    });
    $reviews = new FullSiteReviewService($this->db, $filesystem, $clock, $staff, new OperationalLedger($this->db, $clock), new Php());
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $this->db);
    $owner = $this->createMock(AccountProxyInterface::class); $owner->method('isAuthenticated')->willReturn(TRUE); $owner->method('id')->willReturn(191);
    $response = (new FullSiteReviewController($owner, $portal, $reviews))->file(self::PUBLIC_ID, 'index.html');
    self::assertTrue($changed); self::assertSame(404, $response->getStatusCode());
    self::assertSame('Review not found.', $response->getContent());
  }

  public function testManifestRejectsExecutableAndInternalDocuments(): void {
    foreach ([['path' => 'assets/payload.php', 'media_type' => 'text/html', 'role' => 'asset'], ['path' => 'AGENTS.md', 'media_type' => 'text/plain', 'role' => 'document']] as $bad) {
      $manifest = $this->manifest; $manifest['files'][] = $bad + ['bytes' => 1, 'sha256' => str_repeat('a', 64)];
      try { FullSiteReviewPackage::normalize($manifest); self::fail('Unsafe file accepted.'); } catch (\InvalidArgumentException) { self::assertTrue(TRUE); }
    }
  }

  public function testNonStaffCannotAttachAndSelectedWorkCannotBeReplaced(): void {
    $clock = $this->createMock(TimeInterface::class);
    $filesystem = $this->createMock(FileSystemInterface::class);
    $account = $this->createMock(AccountProxyInterface::class); $account->method('hasPermission')->willReturn(FALSE);
    $denied = new FullSiteReviewService($this->db, $filesystem, $clock, $account, new OperationalLedger($this->db, $clock), new Php());
    try { $denied->attach(self::PUBLIC_ID, 91, 92, $this->directory . '/source', $this->manifest, 'codex:fixture', 'Fixture authority'); self::fail('Non-staff attached a review.'); }
    catch (\RuntimeException $error) { self::assertSame('Staff authorization is required.', $error->getMessage()); }
    try { $denied->readForStaff(93, 'index.html'); self::fail('Non-staff read through the staff path.'); }
    catch (\RuntimeException $error) { self::assertSame('Staff authorization is required.', $error->getMessage()); }
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'selected'])->condition('id', 93)->execute();
    try { $this->attach(); self::fail('Selected lifecycle was replaced.'); }
    catch (\RuntimeException $error) { self::assertStringContainsString('cannot replace', $error->getMessage()); }
    self::assertSame(0, (int) $this->db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField());
  }
}
