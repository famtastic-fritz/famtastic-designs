<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Controller\WebsiteRequestProofController;
use Drupal\famtastic_pipeline\Service\{CustomerPortalService, ManagedProofArtifactPackage, ManagedProofPackageFiles, ManagedProofReader, SelectedCreatorCreditProjection};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ManagedProofImportFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Routing\{Route, RouteCollection, RequestContext};
use Symfony\Component\Routing\Matcher\UrlMatcher;

require_once __DIR__ . '/Fixtures/ManagedProofImportFixture.php';

/** Actual import/reader/controller; session and release attestation are synthetic. */
final class ManagedProofHttpReaderTest extends ManagedProofImportFixture {
  private WebsiteRequestProofController $controller;
  private CustomerPortalService $portal;
  private int $uid = 1;
  private bool $authenticated = TRUE;
  private bool $grantRelease = TRUE;
  private array $receipt;
  private string $publicId;
  private string $base;

  protected function setUp(): void {
    parent::setUp();
    $this->db->query('CREATE TABLE users_field_data (uid INTEGER, status INTEGER, default_langcode INTEGER)');
    $this->db->insert('users_field_data')->fields(['uid' => 1, 'status' => 1, 'default_langcode' => 1])->execute();
    $this->receipt = $this->import();
    $this->publicId = $this->rows('famtastic_project_request')[0]['public_id'];
    $this->base = '/web/api/customer/website-requests/' . $this->publicId . '/proofs/a';
    // This is deliberately NOT an actual QA/release test. A fixture attestor
    // isolates the reader/controller contract; production has no such grant.
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'customer_ready', 'proof_approved_at' => $this->now])->execute();
    $logo = realpath(getenv('FAMTASTIC_TEST_CANONICAL_LOGO') ?: dirname(__DIR__, 8) . '/frontend/public/brand/famtastic-designs-logo-v1.png');
    $reader = new ManagedProofReader($this->db, $this->clock,
      fn(\Closure $resolver) => new ManagedProofArtifactPackage($this->store,
        new ManagedProofPackageFiles($this->temporary . '/packages', $this->temporary . '/web'), $logo, $resolver),
      NULL, function(array $committed, array $request): array {
        if (!$this->grantRelease || $committed !== $this->receipt) throw new \RuntimeException('Synthetic release verifier refused.');
        return ['receipt_id' => $committed['receipt_id'], 'receipt_sha256' => $committed['receipt_sha256'],
          'request_id' => 1, 'customer_id' => 1, 'campaign_id' => 1,
          'package_manifest_sha256' => $committed['receipt']['package_manifest_sha256'],
          'producer_ids' => $committed['receipt']['producer_ids'],
          'proof_review_status' => $request['proof_review_status'], 'proof_approved_at' => $this->now,
          'evidence_sha256' => hash('sha256', 'synthetic-QA-evidence-attestation-not-real-review'),
          'release_sha256' => hash('sha256', 'synthetic-release-attestation-not-QA')];
      });
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturnCallback(fn() => $this->uid);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->authenticated);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'time' => $this->clock] as $p => $v) (new \ReflectionProperty($this->portal, $p))->setValue($this->portal, $v);
    $this->controller = (new \ReflectionClass(WebsiteRequestProofController::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'entities' => $this->entities, 'portal' => $this->portal,
      'account' => $account, 'managedReader' => $reader] as $p => $v) (new \ReflectionProperty($this->controller, $p))->setValue($this->controller, $v);
  }

  public function testFileShapedHtmlAndExactLogoServeWithoutRewritingCreditOrBytes(): void {
    $before = $this->snapshot();
    $response = $this->controller->customerPreview(Request::create($this->base . '/index.html'), $this->publicId, 'a');
    self::assertSame(200, $response->getStatusCode());
    self::assertSame($this->receipt['receipt']['variants']['a']['html_sha256'], hash('sha256', $response->getContent()));
    self::assertSame(file_get_contents($this->temporary . '/packages/' . $this->preparedPackage['package_id'] . '/a/index.html'), $response->getContent());
    self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    self::assertStringContainsString("form-action 'none'", $response->headers->get('Content-Security-Policy'));
    $asset = $this->controller->customerAsset(Request::create($this->base . '/assets/hero.png'), $this->publicId, 'a', 'hero.png');
    self::assertSame(200, $asset->getStatusCode());
    self::assertSame(file_get_contents($this->temporary . '/packages/' . $this->preparedPackage['package_id'] . '/a/assets/hero.png'), $asset->getContent());
    self::assertSame('image/png', $asset->headers->get('Content-Type'));
    $logoPath = substr(SelectedCreatorCreditProjection::ASSET_PATH, strlen('assets/'));
    $logo = $this->controller->customerAsset(Request::create($this->base . '/assets/' . $logoPath), $this->publicId, 'a', $logoPath);
    self::assertSame(200, $logo->getStatusCode());
    self::assertSame('ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950', hash('sha256', $logo->getContent()));
    self::assertSame('image/png', $logo->headers->get('Content-Type'));
    self::assertSame($before, $this->snapshot());
  }

  public function testExistingHtmlUrlRedirectsOnlyAfterCurrentReadAuthorization(): void {
    $response = $this->controller->customerPreview(Request::create($this->base), $this->publicId, 'a');
    self::assertSame(302, $response->getStatusCode());
    self::assertSame($this->base . '/index.html', $response->headers->get('Location'));
    self::assertSame('', $response->getContent());
    $this->grantRelease = FALSE;
    $denied = $this->controller->customerPreview(Request::create($this->base), $this->publicId, 'a');
    self::assertSame(404, $denied->getStatusCode());
    self::assertFalse($denied->headers->has('Location'));
  }

  #[DataProvider('deniedCases')]
  public function testForeignOrStaleAuthorityNeverServesHtmlOrAssets(string $case): void {
    switch ($case) {
      case 'anonymous': $this->authenticated = FALSE; break;
      case 'foreign': $this->uid = 999; break;
      case 'member': $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->execute(); break;
      case 'blocked': $this->db->update('users_field_data')->fields(['status' => 0])->execute(); break;
      case 'withdrawn': $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute(); break;
      case 'brief': $this->db->update('famtastic_project_request')->fields(['intake_data' => '{"changed":true}'])->execute(); break;
      case 'release': $this->grantRelease = FALSE; break;
      case 'pending': $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'owner_review'])->execute(); break;
      case 'unconfigured': // Test the readonly dependency through a new controller.
        $old = $this->controller; $this->controller = (new \ReflectionClass(WebsiteRequestProofController::class))->newInstanceWithoutConstructor();
        foreach (['database','entities','portal','account'] as $p) (new \ReflectionProperty($this->controller, $p))->setValue($this->controller, (new \ReflectionProperty($old, $p))->getValue($old));
        (new \ReflectionProperty($this->controller, 'managedReader'))->setValue($this->controller, NULL); break;
    }
    $before = $this->snapshot();
    self::assertSame(404, $this->controller->customerPreview(Request::create($this->base . '/index.html'), $this->publicId, 'a')->getStatusCode());
    self::assertSame(404, $this->controller->customerAsset(Request::create($this->base . '/assets/hero.png'), $this->publicId, 'a', 'hero.png')->getStatusCode());
    self::assertSame($before, $this->snapshot());
  }
  public static function deniedCases(): iterable { foreach (['anonymous','foreign','member','blocked','withdrawn','brief','release','pending','unconfigured'] as $case) yield [$case]; }

  #[DataProvider('unsafePaths')]
  public function testUndeclaredOrAliasedAssetPathsAreGenericSecureDenials(string $path): void {
    $r = $this->controller->customerAsset(Request::create($this->base . '/assets/x'), $this->publicId, 'a', $path);
    self::assertSame(404, $r->getStatusCode());
    self::assertSame('Proof not found.', $r->getContent());
    self::assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    self::assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
  }
  public static function unsafePaths(): iterable { foreach (['../index.html', '%2e%2e/index.html', '/brand/famtastic-designs-logo-v1.png', 'brand/FAMtastic-designs-logo-v1.png', 'missing.png'] as $p) yield [$p]; }

  public function testIndexAliasRetainsExactCustomerControllerAndBoundedParameters(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    $r = $routes['famtastic_pipeline.customer_website_request_proof_index'];
    self::assertSame('/api/customer/website-requests/{website_request}/proofs/{direction}/index.html', $r['path']);
    self::assertSame(WebsiteRequestProofController::class . '::customerPreview', $r['defaults']['_controller']);
    self::assertSame(['GET'], $r['methods']);
    self::assertSame('a|b|c', $r['requirements']['direction']);
  }

  public function testSymfonyMatchesIndexAndItsDirectionRelativeAssetRoute(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml');
    $collection = new RouteCollection();
    foreach (['famtastic_pipeline.customer_website_request_proof_index', 'famtastic_pipeline.customer_website_request_proof_asset'] as $name) {
      $r = $routes[$name];
      $collection->add($name, new Route($r['path'], $r['defaults'], $r['requirements'], [], '', [], $r['methods']));
    }
    // Real Symfony matching of the Drupal path below its /web base. This is
    // not an installed router, browser/session or end-to-end auth assertion.
    $matcher = new UrlMatcher($collection, new RequestContext('/web', 'GET'));
    foreach (['/index.html' => 'customerPreview', '/assets/hero.png' => 'customerAsset',
      '/assets/brand/famtastic-designs-logo-v1.png' => 'customerAsset'] as $suffix => $method) {
      $matched = $matcher->match(substr($this->base, strlen('/web')) . $suffix);
      self::assertSame(WebsiteRequestProofController::class . '::' . $method, $matched['_controller']);
      self::assertSame($this->publicId, $matched['website_request']);
      self::assertSame('a', $matched['direction']);
    }
  }
}
