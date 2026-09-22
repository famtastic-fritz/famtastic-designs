<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\famtastic_pipeline\Service\{AutomatedProofPolicy, AutomatedProofRelease, CustomerPortalService, ManagedProofArtifactPackage, ManagedProofPackageFiles, ManagedProofReader, OperationalLedger};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ManagedProofImportFixture;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Fixtures/ManagedProofImportFixture.php';

/** Actual import/package/QA context. Principal and producer provenance are fixtures. */
final class ManagedProofQaContextTest extends ManagedProofImportFixture {
  private CustomerPortalService $portal;
  private AutomatedProofRelease $release;
  private object $principal;
  private string $reviewer = 'automation:synthetic-independent';
  private array $receipt;
  private array $research = ['overview' => 'Synthetic sourced context, not customer research.',
    'direction_rationale' => ['a' => 'Synthetic first.', 'b' => 'Synthetic second.', 'c' => 'Synthetic third.'],
    'sources' => ['https://example.test/source'], 'researched_at' => '2026-09-22'];

  protected function setUp(): void {
    parent::setUp();
    $this->db->query('CREATE TABLE users_field_data (uid INTEGER, status INTEGER, default_langcode INTEGER)');
    $this->db->insert('users_field_data')->fields(['uid' => 1, 'status' => 1, 'default_langcode' => 1])->execute();
    $this->receipt = $this->import();
    $this->principal = new \stdClass();
    $logo = realpath(getenv('FAMTASTIC_TEST_CANONICAL_LOGO') ?: dirname(__DIR__, 8) . '/frontend/public/brand/famtastic-designs-logo-v1.png');
    $reader = new ManagedProofReader($this->db, $this->clock,
      fn(\Closure $resolver) => new ManagedProofArtifactPackage($this->store,
        new ManagedProofPackageFiles($this->temporary . '/packages', $this->temporary . '/web'), $logo, $resolver),
      fn(object $principal) => $principal === $this->principal ? $this->reviewer : NULL);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'time' => $this->clock] as $name => $value) (new \ReflectionProperty($this->portal, $name))->setValue($this->portal, $value);
    $this->release = new AutomatedProofRelease($this->db, $this->entities, $this->clock,
      new OperationalLedger($this->db, $this->clock), $this->portal, $reader);
    $container = new ContainerBuilder();
    $container->set('famtastic_pipeline.automated_proof_release', $this->release);
    \Drupal::setContainer($container);
  }

  public function testImportedPrivateBytesReachExistingPortalQaContextWithoutLegacyFiles(): void {
    $before = $this->snapshot();
    $context = $this->portal->websiteRequestAutomatedProofQaContext(1, $this->research, $this->principal);
    self::assertSame(1, $context['request_id']);
    self::assertSame(1, $context['customer_id']);
    self::assertSame(1, $context['campaign_id']);
    self::assertSame(AutomatedProofPolicy::VERSION, $context['policy_version']);
    self::assertSame($this->reviewer, $context['reviewer']);
    self::assertSame(['receipt_id' => $this->receipt['receipt_id'], 'receipt_sha256' => $this->receipt['receipt_sha256'],
      'package_manifest_sha256' => $this->receipt['receipt']['package_manifest_sha256'],
      'producer_ids' => ['automation:synthetic-mac']], $context['managed_import']);
    foreach (['a', 'b', 'c'] as $d) self::assertSame($this->receipt['receipt']['variants'][$d]['html_sha256'], $context['artifact_hashes'][$d]);
    self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $context['research_sha256']);
    self::assertDirectoryDoesNotExist($this->temporary . '/web/proofs');
    self::assertSame($before, $this->snapshot());
  }

  #[DataProvider('unsafeContexts')]
  public function testActualConsumerRejectsUnsafeContextWithoutDatabaseEffects(string $case, string $error): void {
    $principal = $this->principal;
    switch ($case) {
      case 'missing-principal': $principal = NULL; break;
      case 'foreign-principal': $principal = new \stdClass(); break;
      case 'producer': $this->reviewer = 'automation:synthetic-mac'; break;
      case 'withdrawn': $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute(); break;
      case 'blocked-account': $this->db->update('users_field_data')->fields(['status' => 0])->execute(); break;
      case 'brief': $this->db->update('famtastic_project_request')->fields(['intake_data' => '{"changed":true}'])->execute(); break;
      case 'package':
        $path = $this->temporary . '/packages/' . $this->preparedPackage['package_id'] . '/a/index.html';
        chmod($path, 0600); file_put_contents($path, 'Synthetic changed bytes'); chmod($path, 0400); break;
    }
    $before = $this->snapshot();
    $this->reject(fn() => $this->portal->websiteRequestAutomatedProofQaContext(1, $this->research, $principal), $error);
    self::assertSame($before, $this->snapshot());
  }

  public static function unsafeContexts(): iterable {
    yield ['missing-principal', 'unconfigured'];
    yield ['foreign-principal', 'unauthenticated'];
    yield ['producer', 'producer'];
    yield ['withdrawn', 'asset authority'];
    yield ['blocked-account', 'account authority'];
    yield ['brief', 'current input'];
    yield ['package', 'Package'];
  }

  public function testReadContextNeverEnablesLegacyReleaseOrApprovesFixtureContent(): void {
    $context = $this->portal->websiteRequestAutomatedProofQaContext(1, $this->research, $this->principal);
    $before = $this->snapshot();
    $this->reject(fn() => $this->portal->releaseWebsiteRequestProofAfterQa(1, $this->research, $context,
      $this->reviewer, ['subject' => 'Never send']), 'receipt-bound transaction adapter');
    self::assertFalse($this->db->inTransaction());
    self::assertSame($before, $this->snapshot());
    self::assertSame('owner_review', $this->rows('famtastic_project_request')[0]['proof_review_status']);
  }
}
