<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\{EntityStorageInterface, EntityTypeManagerInterface};
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\{ProofCampaignController, WebsiteRequestProofController};
use Drupal\famtastic_pipeline\Entity\{ProofCampaign, ProofVariant, Prospect};
use Drupal\famtastic_pipeline\Service\{CreatorCredit, CustomerPortalService, FreshProofBinding, PipelineRepository, ProofCampaignService};
use Drupal\sqlite\Driver\Database\sqlite\{Connection, Select};
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\{Request, Response};

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Actual controllers/portal/classifier, real SQLite and tiny confined files.
 *
 * Only account, entity storage and prospect/service transport are doubles.
 * No installed authentication, HTTP routing or SQL concurrency is claimed.
 */
final class ManagedProofLegacyReaderFenceTest extends UnitTestCase {
  private const REQUEST = '00000000-0000-0000-0000-000000000001';
  private ReaderFenceConnection $db;
  private WebsiteRequestProofController $controller;
  private CustomerPortalService $portal;
  private string $temporary;
  private string $html;
  private string $image;
  private array $oldSettings;
  private int $storageReads = 0;
  private int $variantReads = 0;

  protected function setUp(): void {
    parent::setUp();
    try { Settings::getInstance(); $this->oldSettings = Settings::getAll(); }
    catch (\BadMethodCallException) { $this->oldSettings = []; }
    new Settings(['hash_salt' => 'synthetic-reader-fence-only', 'famtastic_fresh_proof_admission_enabled' => FALSE]);
    $o = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new ReaderFenceConnection(Connection::open($o), $o);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema() + _famtastic_pipeline_automation_schema();
    $schemas['famtastic_website_proof_research_snapshot'] = _famtastic_pipeline_website_proof_research_snapshot_schema();
    foreach (['famtastic_project_request', 'famtastic_membership', 'famtastic_customer', 'famtastic_request_asset', 'famtastic_event', 'famtastic_website_proof_research_snapshot', 'famtastic_portal_activity', 'famtastic_private_offer'] as $table) {
      $this->db->schema()->createTable($table, $schemas[$table]);
    }
    $this->db->insert('famtastic_customer')->fields(['id' => 1, 'public_id' => self::REQUEST, 'uid' => 1, 'display_name' => 'Synthetic', 'email' => 'fixture@example.test', 'created' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 1, 'organization_id' => 2, 'status' => 'active', 'created' => 1])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 1, 'public_id' => self::REQUEST, 'customer_id' => 1, 'organization_id' => 2,
      'project_name' => 'Synthetic private proof', 'business_name' => 'Synthetic only', 'intake_data' => '{}', 'created' => 1, 'changed' => 1,
      'proof_campaign_id' => 53, 'proof_review_status' => 'customer_ready', 'proof_share_enabled' => 1, 'proof_share_version' => 1])->execute();
    $this->temporary = realpath(sys_get_temp_dir()) . '/managed-reader-fence-' . bin2hex(random_bytes(12));
    foreach (['', '/web', '/web/proofs', '/web/proofs/pc-synthetic', '/web/proofs/pc-synthetic/a', '/web/proofs/pc-synthetic/a/assets'] as $suffix) mkdir($this->temporary . $suffix, 0700);
    $this->html = '<html><body><p>Synthetic private artifact</p><img src="assets/hero.png"></body></html>';
    $this->image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII=');
    file_put_contents($this->temporary . '/web/proofs/pc-synthetic/a/index.html', $this->html);
    file_put_contents($this->temporary . '/web/proofs/pc-synthetic/a/assets/hero.png', $this->image);
    $container = new ContainerBuilder(); $container->setParameter('app.root', $this->temporary . '/web'); \Drupal::setContainer($container);

    $variants = [];
    foreach (['a', 'b', 'c'] as $d) {
      $variant = $this->createMock(ProofVariant::class);
      $variant->method('get')->willReturnCallback(function (string $field) use ($d): object {
        $this->variantReads++;
        return (object) ['value' => [
          'direction_id' => $d, 'direction_name' => strtoupper($d),
          'artifact_path' => 'web/proofs/pc-synthetic/a/index.html',
          'design_dna' => json_encode(['asset_manifest' => [[
            'asset_id' => 'hero', 'relative_path' => 'hero.png', 'media_type' => 'image/png',
            'sha256' => hash('sha256', $this->image), 'size_bytes' => strlen($this->image),
            'artifact_path' => 'web/proofs/pc-synthetic/a/assets/hero.png',
          ]]]),
        ][$field] ?? NULL];
      });
      $variants[] = $variant;
    }
    $query = $this->createMock(QueryInterface::class);
    foreach (['accessCheck', 'condition', 'range', 'sort'] as $method) $query->method($method)->willReturnSelf();
    $query->method('execute')->willReturn([1, 2, 3]);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query); $storage->method('load')->willReturn($variants[0]);
    $storage->method('loadMultiple')->willReturn($variants);
    $campaign = $this->createMock(ProofCampaign::class);
    $campaign->method('id')->willReturn(53);
    $campaign->method('get')->willReturnCallback(static fn(string $field): object => (object) ['value' => [
      'generation_status' => 'ready', 'campaign_id' => 'pc-synthetic', 'selected_variant' => '',
    ][$field] ?? NULL]);
    $campaigns = $this->createMock(EntityStorageInterface::class); $campaigns->method('load')->willReturn($campaign);
    $campaignQuery = $this->createMock(QueryInterface::class);
    foreach (['accessCheck', 'condition', 'range', 'sort'] as $method) $campaignQuery->method($method)->willReturnSelf();
    $campaignQuery->method('execute')->willReturn([53]); $campaigns->method('getQuery')->willReturn($campaignQuery);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(function (string $type) use ($storage, $campaigns) {
      $this->storageReads++;
      return match ($type) { 'proof_variant' => $storage, 'proof_campaign' => $campaigns, default => throw new \LogicException('Unexpected storage access.') };
    });
    $clock = $this->createMock(TimeInterface::class); $clock->method('getRequestTime')->willReturn(1700000000); $clock->method('getCurrentTime')->willReturn(1700000000);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'entities' => $entities, 'time' => $clock,
      'configFactory' => $this->getConfigFactoryStub(['famtastic_pipeline.settings' => ['frontend_base_url' => 'https://fixture.example.test']])] as $p => $v) {
      (new \ReflectionProperty($this->portal, $p))->setValue($this->portal, $v);
    }
    $account = $this->createMock(AccountProxyInterface::class); $account->method('isAuthenticated')->willReturn(TRUE); $account->method('id')->willReturn(1);
    $this->controller = (new \ReflectionClass(WebsiteRequestProofController::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'portal' => $this->portal, 'account' => $account, 'entities' => $entities, 'managedReader' => NULL] as $p => $v) (new \ReflectionProperty($this->controller, $p))->setValue($this->controller, $v);
  }

  protected function tearDown(): void {
    if (isset($this->temporary)) {
      // Only two known files and their exact test-owned directories; no glob.
      foreach (['/web/proofs/pc-synthetic/a/index.html', '/web/proofs/pc-synthetic/a/assets/hero.png'] as $suffix) unlink($this->temporary . $suffix);
      foreach (['/web/proofs/pc-synthetic/a/assets', '/web/proofs/pc-synthetic/a', '/web/proofs/pc-synthetic', '/web/proofs', '/web', ''] as $suffix) rmdir($this->temporary . $suffix);
    }
    if (isset($this->oldSettings)) new Settings($this->oldSettings);
    parent::tearDown();
  }

  private function marker(string $mode): void {
    if ($mode === 'none') return;
    [$key, $type, $campaign, $payload] = match ($mode) {
      'request' => [FreshProofBinding::key(1), FreshProofBinding::EVENT, 53, '{}'],
      'malformed' => [FreshProofBinding::key(1), 'broken', 999, '{not-json'],
      'campaign-type' => ['other-key', FreshProofBinding::EVENT, 53, 'null'],
      'campaign-prefix' => [FreshProofBinding::key(999), 'broken', 53, 'false'],
      'unrelated' => [FreshProofBinding::key(999), FreshProofBinding::EVENT, 999, '{}'],
      'lookalike' => ['proof-admission:requestX1', 'ordinary', 53, '{}'],
    };
    $this->db->insert('famtastic_event')->fields(['event_key' => $key, 'event_type' => $type, 'campaign_id' => $campaign, 'payload' => $payload, 'occurred_at' => 1, 'recorded_at' => 1])->execute();
  }

  private function snapshot(): array {
    $out = [];
    foreach (['famtastic_project_request', 'famtastic_membership', 'famtastic_customer', 'famtastic_request_asset', 'famtastic_event', 'famtastic_portal_activity'] as $t) {
      $out[$t] = $this->db->select($t, 't')->fields('t')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
    return $out;
  }
  private function websiteCall(string $method): Response {
    $r = Request::create('/synthetic', 'GET');
    $sig = hash_hmac('sha256', 'website-proof-share-v1|' . self::REQUEST . '|1', Settings::getHashSalt());
    return match ($method) {
      'customerPreview' => $this->controller->customerPreview($r, self::REQUEST, 'a'),
      'customerAsset' => $this->controller->customerAsset($r, self::REQUEST, 'a', 'hero.png'),
      'adminPreview' => $this->controller->adminPreview($r, 1, 'a'),
      'adminAsset' => $this->controller->adminAsset($r, 1, 'a', 'hero.png'),
      'publicShare' => $this->controller->publicShare($r, self::REQUEST, $sig),
      'publicPreview' => $this->controller->publicPreview($r, self::REQUEST, $sig, 'a'),
      'publicAsset' => $this->controller->publicAsset($r, self::REQUEST, $sig, 'a', 'hero.png'),
    };
  }
  private function denied(Response $r): void {
    self::assertSame(404, $r->getStatusCode());
    self::assertTrue($r->headers->hasCacheControlDirective('private'));
    self::assertTrue($r->headers->hasCacheControlDirective('no-store'));
    self::assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    self::assertStringNotContainsString('Synthetic', $r->getContent());
    self::assertStringNotContainsString($this->temporary, $r->getContent());
    self::assertStringNotContainsString(self::REQUEST, $r->getContent());
  }

  #[DataProvider('websiteManagedCases')]
  public function testManagedWebsiteRoutesNeverReachPlantedLegacyFilesOrDna(string $method, string $mode): void {
    $this->marker($mode); $before = $this->snapshot();
    $this->denied($this->websiteCall($method));
    self::assertSame(0, $this->storageReads, 'Deny before legacy storage/path lookup, not because a file is missing.');
    self::assertSame(0, $this->variantReads);
    self::assertSame($before, $this->snapshot()); self::assertSame(0, $this->db->lockingReads);
    self::assertSame($this->html, file_get_contents($this->temporary . '/web/proofs/pc-synthetic/a/index.html'));
    self::assertSame($this->image, file_get_contents($this->temporary . '/web/proofs/pc-synthetic/a/assets/hero.png'));
  }
  public static function websiteManagedCases(): iterable {
    foreach (['customerPreview', 'customerAsset', 'adminPreview', 'adminAsset', 'publicShare', 'publicPreview', 'publicAsset'] as $method) {
      foreach (['request', 'malformed', 'campaign-type', 'campaign-prefix'] as $mode) yield $method . '-' . $mode => [$method, $mode];
    }
  }

  #[DataProvider('legacyWebsiteCases')]
  public function testUnmanagedRoutesStillReadTheSameLegacyArtifact(string $method, string $mode): void {
    $this->marker($mode); $before = $this->snapshot(); $r = $this->websiteCall($method);
    self::assertSame(200, $r->getStatusCode()); self::assertGreaterThan(0, $this->storageReads);
    if ($method === 'publicShare') {
      $body = json_decode($r->getContent(), TRUE); self::assertTrue($body['ok']); self::assertSame(3, $body['proof_share']['proof_count']);
    }
    elseif (str_ends_with($method, 'Asset')) self::assertSame($this->image, $r->getContent());
    else {
      $sig = hash_hmac('sha256', 'website-proof-share-v1|' . self::REQUEST . '|1', Settings::getHashSalt());
      $url = match ($method) {
        'adminPreview' => '/web/admin/famtastic/website-request/1/proof/a/assets/hero.png',
        'customerPreview' => '/web/api/customer/website-requests/' . self::REQUEST . '/proofs/a/assets/hero.png',
        'publicPreview' => '/web/api/proof-shares/' . self::REQUEST . '/' . $sig . '/proofs/a/assets/hero.png',
      };
      self::assertSame(CreatorCredit::present(str_replace('assets/hero.png', $url, $this->html)), $r->getContent());
    }
    self::assertSame($before, $this->snapshot()); self::assertSame(0, $this->db->lockingReads);
  }
  public static function legacyWebsiteCases(): iterable {
    foreach (['customerPreview', 'customerAsset', 'adminPreview', 'adminAsset', 'publicShare', 'publicPreview', 'publicAsset'] as $method) {
      foreach (['none', 'unrelated', 'lookalike'] as $mode) yield $method . '-' . $mode => [$method, $mode];
    }
  }

  public function testReadClassifierDoesNotChangeTheExistingLockingClassifier(): void {
    $this->marker('malformed'); $row = ['id' => 1, 'proof_campaign_id' => 53];
    self::assertTrue(FreshProofBinding::isManagedReadOnly($this->db, $row));
    self::assertTrue(FreshProofBinding::isManagedCampaignReadOnly($this->db, 53));
    self::assertSame(0, $this->db->lockingReads);
    self::assertTrue(FreshProofBinding::isManaged($this->db, $row));
    self::assertSame(1, $this->db->lockingReads); self::assertFalse($this->db->inTransaction());
    // Cleared campaign does not erase immutable request identity.
    self::assertTrue(FreshProofBinding::isManagedReadOnly($this->db, ['id' => 1, 'proof_campaign_id' => NULL]));
  }

  public function testManagedPortalMetadataIsHiddenWithoutReadingVariants(): void {
    $this->marker('malformed');
    $this->db->update('famtastic_project_request')->fields(['project_id' => 7])->condition('id', 1)->execute();
    $before = $this->snapshot();
    $sig = hash_hmac('sha256', 'website-proof-share-v1|' . self::REQUEST . '|1', Settings::getHashSalt());
    self::assertNull($this->portal->sharedWebsiteRequest(self::REQUEST, $sig));
    self::assertNull($this->portal->publicWebsiteProofShare(self::REQUEST, $sig));
    self::assertSame(['enabled' => FALSE, 'url' => '', 'changed_at' => NULL], $this->portal->websiteProofShareStatus(1));
    $row = $this->db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 1)->execute()->fetchAssoc();
    self::assertNull((new \ReflectionMethod($this->portal, 'serializeRequestProof'))->invoke($this->portal, $row));
    $project = new class {
      public function id(): int { return 7; }
      public function get(string $field): object { return (object) ['target_id' => 9]; }
    };
    self::assertNull((new \ReflectionMethod($this->portal, 'projectProofs'))->invoke($this->portal, $project));
    self::assertSame(0, $this->storageReads); self::assertSame(0, $this->variantReads);
    self::assertSame($before, $this->snapshot()); self::assertSame(0, $this->db->lockingReads);
  }

  #[DataProvider('shareActions')]
  public function testManagedShareCannotEnableOrRotateButCanRevokeWithoutLegacyEvidence(string $action): void {
    $this->marker('malformed');
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'not_started'])->condition('id', 1)->execute();
    $before = $this->snapshot();
    if ($action !== 'disable') {
      $error = NULL;
      try { $this->portal->updateWebsiteProofShare(1, self::REQUEST, $action, 1); }
      catch (\RuntimeException $e) { $error = $e; }
      self::assertNotNull($error); self::assertSame('Website proofs are not available for sharing.', $error->getMessage());
      self::assertSame($before, $this->snapshot());
    }
    else {
      $result = $this->portal->updateWebsiteProofShare(1, self::REQUEST, 'disable', 1);
      self::assertFalse($result['proof_share']['enabled']); self::assertSame('', $result['proof_share']['url']); self::assertNull($result['proofs']);
      $after = $this->snapshot(); $row = $after['famtastic_project_request'][0];
      self::assertSame(0, (int) $row['proof_share_enabled']); self::assertSame(2, (int) $row['proof_share_version']);
      self::assertSame(1, (int) $row['proof_share_changed_by_uid']);
      self::assertCount(1, $after['famtastic_portal_activity']);
      self::assertSame('website_request.proof_share_disabled', $after['famtastic_portal_activity'][0]['event_type']);
      self::assertSame($before['famtastic_event'], $after['famtastic_event']);
    }
    self::assertSame(0, $this->storageReads); self::assertSame(0, $this->variantReads); self::assertSame(0, $this->db->lockingReads);
  }
  public static function shareActions(): iterable { foreach (['enable', 'rotate', 'disable'] as $action) yield [$action]; }

  #[DataProvider('shareActions')]
  public function testUnmanagedShareActionsKeepExistingVersionAndAuditBehavior(string $action): void {
    $result = $this->portal->updateWebsiteProofShare(1, self::REQUEST, $action, 1);
    self::assertSame($action !== 'disable', $result['proof_share']['enabled']);
    self::assertSame($action === 'disable', $result['proof_share']['url'] === '');
    self::assertCount(3, $result['proofs']['variants']);
    $after = $this->snapshot(); $row = $after['famtastic_project_request'][0];
    self::assertSame($action === 'enable' ? 1 : 2, (int) $row['proof_share_version']);
    self::assertSame('customer_ready', $row['proof_review_status']); self::assertSame(53, (int) $row['proof_campaign_id']);
    self::assertCount(1, $after['famtastic_portal_activity']); self::assertSame([], $after['famtastic_event']);
    self::assertGreaterThan(0, $this->variantReads); self::assertSame(0, $this->db->lockingReads);
  }

  public function testUnattachedProjectCannotFallBackToAManagedCampaignButLegacyRemainsVisible(): void {
    $project = new class {
      public function id(): int { return 7; }
      public function get(string $field): object { return (object) ['target_id' => 9]; }
    };
    $projection = new \ReflectionMethod($this->portal, 'projectProofs');
    self::assertCount(3, $projection->invoke($this->portal, $project)['variants']);
    $this->storageReads = $this->variantReads = 0; $this->marker('campaign-type'); $before = $this->snapshot();
    self::assertNull($projection->invoke($this->portal, $project));
    self::assertSame(1, $this->storageReads, 'Campaign query only, before loading variants.'); self::assertSame(0, $this->variantReads);
    self::assertSame($before, $this->snapshot()); self::assertSame(0, $this->db->lockingReads);
  }

  public function testManagedRevocationStillRequiresOwnedRequestAndActiveMembership(): void {
    $this->marker('request');
    foreach (['foreign', 'inactive'] as $case) {
      if ($case === 'inactive') $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->execute();
      $before = $this->snapshot(); $error = NULL;
      try { $this->portal->updateWebsiteProofShare($case === 'foreign' ? 99 : 1, self::REQUEST, 'disable', 1); }
      catch (\RuntimeException $e) { $error = $e; }
      self::assertNotNull($error); self::assertSame('Website proofs are not available.', $error->getMessage());
      self::assertSame($before, $this->snapshot());
    }
    self::assertSame(0, $this->storageReads);
  }

  #[DataProvider('campaignManagedCases')]
  public function testProspectTokenCannotReadMutateExpirySelectOrRegenerateManagedCampaign(string $method, string $mode): void {
    $this->marker($mode); $before = $this->snapshot();
    $campaign = $this->createMock(ProofCampaign::class); $campaign->method('id')->willReturn(53);
    // These expectations fail if denial moves after refresh/serialization.
    $campaign->expects(self::never())->method('get'); $campaign->expects(self::never())->method('isExpired');
    $campaign->expects(self::never())->method('save'); $campaign->expects(self::never())->method('set');
    $variant = $this->createMock(ProofVariant::class); $variant->expects(self::never())->method('get');
    $service = $this->createMock(ProofCampaignService::class);
    $service->method('getForProspect')->willReturn(['campaign' => $campaign, 'variants' => [$variant]]);
    $service->expects(self::never())->method('select'); $service->expects(self::never())->method('createForProspect');
    $repository = $this->createMock(PipelineRepository::class);
    $repository->method('loadProspectByToken')->with('synthetic-valid-token')->willReturn($this->createMock(Prospect::class));
    $controller = new ProofCampaignController($repository, $service, $this->db);
    $request = Request::create('/synthetic', $method === 'show' ? 'GET' : 'POST');
    $request->headers->set('X-Prospect-Token', 'synthetic-valid-token');
    $this->denied($controller->{$method}($request));
    self::assertSame($before, $this->snapshot()); self::assertSame(0, $this->db->lockingReads);
  }
  public static function campaignManagedCases(): iterable {
    foreach (['show', 'createCampaign', 'selectAction'] as $method) {
      foreach (['request', 'malformed', 'campaign-type', 'campaign-prefix'] as $mode) yield $method . '-' . $mode => [$method, $mode];
    }
  }

  #[DataProvider('legacyCampaignCases')]
  public function testUnmanagedTokenReadCreateAndSelectionRemainAvailable(string $method): void {
    $this->marker('unrelated');
    $campaign = $this->createMock(ProofCampaign::class); $campaign->method('id')->willReturn(53);
    $campaign->method('get')->willReturnCallback(static fn(string $field): object => (object) ['value' => [
      'status' => 'active', 'campaign_id' => 'pc-synthetic', 'business_name' => 'Synthetic', 'generation_status' => 'ready', 'expires_at' => 1000, 'created' => 1,
    ][$field] ?? NULL]);
    $campaign->method('isExpired')->willReturn(FALSE);
    $service = $this->createMock(ProofCampaignService::class);
    $service->method('getForProspect')->willReturn($method === 'createCampaign' ? NULL : ['campaign' => $campaign, 'variants' => []]);
    $service->expects($method === 'selectAction' ? self::once() : self::never())->method('select')->willReturn($campaign);
    $service->expects($method === 'createCampaign' ? self::once() : self::never())->method('createForProspect')->willReturn(['campaign' => $campaign, 'variants' => []]);
    $repository = $this->createMock(PipelineRepository::class);
    $repository->method('loadProspectByToken')->willReturn($this->createMock(Prospect::class));
    $controller = new ProofCampaignController($repository, $service, $this->db);
    $request = Request::create('/synthetic', $method === 'show' ? 'GET' : 'POST', content: '{"variant_id":"a","package":"essential_199"}');
    $request->headers->set('X-Prospect-Token', 'synthetic-valid-token');
    $r = $controller->{$method}($request);
    self::assertSame($method === 'createCampaign' ? 201 : 200, $r->getStatusCode()); self::assertTrue(json_decode($r->getContent(), TRUE)['ok']);
    self::assertSame(0, $this->db->lockingReads);
  }
  public static function legacyCampaignCases(): iterable {
    foreach (['show', 'createCampaign', 'selectAction'] as $method) yield [$method];
  }

  public function testUnmanagedExistingCampaignExpiryBehaviorIsNotRemoved(): void {
    $campaign = $this->createMock(ProofCampaign::class); $campaign->method('id')->willReturn(53); $status = 'active';
    $campaign->method('get')->willReturnCallback(static function (string $field) use (&$status): object {
      return (object) ['value' => $field === 'status' ? $status : NULL];
    });
    $campaign->method('isExpired')->willReturn(TRUE);
    $campaign->expects(self::once())->method('set')->with('status', 'expired')->willReturnCallback(function () use ($campaign, &$status) { $status = 'expired'; return $campaign; });
    $campaign->expects(self::once())->method('save');
    $service = $this->createMock(ProofCampaignService::class);
    $service->method('getForProspect')->willReturn(['campaign' => $campaign, 'variants' => []]);
    $repository = $this->createMock(PipelineRepository::class); $repository->method('loadProspectByToken')->willReturn($this->createMock(Prospect::class));
    $container = new ContainerBuilder(); $container->set('database', $this->db);
    $container->set('famtastic_pipeline.repository', $repository); $container->set('famtastic_pipeline.proof_campaign_service', $service);
    $controller = ProofCampaignController::create($container);
    self::assertSame(200, $controller->show(Request::create('/synthetic'))->getStatusCode()); self::assertSame('expired', $status);
  }

  public function testInvalidProspectTokenIsStillDeniedBeforeCampaignLookup(): void {
    $repository = $this->createMock(PipelineRepository::class); $repository->method('loadProspectByToken')->willReturn(NULL);
    $service = $this->createMock(ProofCampaignService::class); $service->expects(self::never())->method('getForProspect');
    $controller = new ProofCampaignController($repository, $service, $this->db);
    foreach (['show', 'createCampaign', 'selectAction'] as $method) {
      $r = $controller->{$method}(Request::create('/synthetic'));
      self::assertSame(404, $r->getStatusCode()); self::assertSame('invalid_or_expired_token', json_decode($r->getContent(), TRUE)['error']);
    }
  }
}

/** Counts requested locks; still executes the actual Drupal SQLite queries. */
final class ReaderFenceConnection extends Connection {
  public int $lockingReads = 0;
  public function select($table, $alias = NULL, array $options = []) {
    return new ReaderFenceSelect($this, $table, $alias, $options);
  }
}
final class ReaderFenceSelect extends Select {
  public function forUpdate($set = TRUE) {
    if ($set) $this->connection->lockingReads++;
    return parent::forUpdate($set);
  }
}
