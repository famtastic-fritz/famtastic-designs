<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\famtastic_pipeline\Service\{ManagedProofArtifactPackage, ManagedProofImportContract as Contract, ManagedProofImportReceipt as Receipt,
  ManagedProofPackageFiles, ManagedProofReader, SelectedCreatorCreditProjection as Credit};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\{ManagedProofImportFixture, ProofArtifactInputs};
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Fixtures/ManagedProofImportFixture.php';

/**
 * Test-only setup reusable by a ManagedProofImportFixture QA-context subclass.
 * Require this file and use this trait; explicitly install the synthetic user.
 * Neither helper installs a reviewer/release grant or registers any service.
 */
trait ManagedProofReaderTestSetup {
  protected function installSyntheticReaderUser(): void {
    // The reader uses actual Drupal column names and the default language row.
    $this->db->query('CREATE TABLE users_field_data (uid INTEGER NOT NULL, langcode TEXT NOT NULL, default_langcode INTEGER NOT NULL, status INTEGER NOT NULL, PRIMARY KEY (uid, langcode))');
    $this->db->insert('users_field_data')->fields(['uid' => 1, 'langcode' => 'en', 'default_langcode' => 1, 'status' => 1])->execute();
  }

  protected function managedReaderPackageFactory(): \Closure {
    $logo = realpath(getenv('FAMTASTIC_TEST_CANONICAL_LOGO') ?: dirname(__DIR__, 8) . '/frontend/public/brand/famtastic-designs-logo-v1.png');
    self::assertNotFalse($logo);
    return function (\Closure $resolveReceipt) use ($logo): ManagedProofArtifactPackage {
      self::assertFalse($this->db->inTransaction());
      return new ManagedProofArtifactPackage($this->store,
        new ManagedProofPackageFiles($this->temporary . '/packages', $this->temporary . '/web'), $logo, $resolveReceipt);
    };
  }

  protected function makeManagedReader(?\Closure $reviewer = NULL, ?\Closure $release = NULL, ?\Closure $packageFactory = NULL): ManagedProofReader {
    return new ManagedProofReader($this->db, $this->clock, $packageFactory ?? $this->managedReaderPackageFactory(), $reviewer, $release);
  }
}

/**
 * Real importer/receipts, SQLite and package bytes. Identity/provenance/release
 * authority are explicit SYNTHETIC doubles, not HMAC, QA or activation evidence.
 * No network, provider, mail, release writer, service registration or selection.
 */
final class ManagedProofReaderTest extends ManagedProofImportFixture {
  use ManagedProofReaderTestSetup;

  private object $reviewerPrincipal;
  private mixed $reviewerIdentity = 'automation:synthetic-reviewer';
  private bool $reviewerAllowed = TRUE;
  private int $reviewerCalls = 0;

  protected function setUp(): void {
    parent::setUp();
    $this->installSyntheticReaderUser();
    $this->reviewerPrincipal = new \stdClass();
  }

  private function reviewerAuthority(): \Closure {
    return function (object $principal, array $receipt, array $request): mixed {
      self::assertFalse($this->db->inTransaction()); ++$this->reviewerCalls;
      // Exact object identity stands in for upstream HMAC/registry authentication
      // ONLY in this fixture. No property, body identity or claimed boolean used.
      if ($principal !== $this->reviewerPrincipal || !$this->reviewerAllowed
        || $receipt['request_id'] !== 1 || (int) $request['id'] !== 1) return NULL;
      return $this->reviewerIdentity;
    };
  }

  public function testRealImportedPendingReviewReadsAllDirectionsAndExactAssetAdapterWithoutWritesOrLocks(): void {
    $committed = $this->import(); $state = $this->fullSnapshot(); $files = $this->fileSnapshot();
    $factory = $this->managedReaderPackageFactory(); $calls = 0;
    $reader = $this->makeManagedReader($this->reviewerAuthority(), NULL, static function (\Closure $resolver) use ($factory, &$calls) {
      ++$calls; return $factory($resolver);
    });
    $this->db->observed = [];
    $context = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    self::assertSame([], get_object_vars($context)); self::assertSame(0, $calls);
    $facts = $reader->facts($context);
    self::assertSame('famtastic.managed-proof-read-context.v1', $facts['schema']);
    self::assertSame(1, $facts['request_id']); self::assertSame(1, $facts['customer_id']); self::assertSame(2, $facts['organization_id']);
    self::assertSame(1, $facts['campaign_id']); self::assertSame(1, $facts['job_id']);
    self::assertSame($committed['receipt_id'], $facts['receipt_id']); self::assertSame($committed['receipt_sha256'], $facts['receipt_sha256']);
    self::assertSame($this->preparedPackage['package_id'], $facts['package_id']);
    self::assertSame($this->preparedPackage['package_manifest_sha256'], $facts['package_manifest_sha256']);
    self::assertSame(['automation:synthetic-mac'], $facts['producer_ids']);
    self::assertSame('automation:synthetic-reviewer', $facts['actor']); self::assertSame('owner_review', $facts['proof_review_status']);
    self::assertSame('reviewer', $facts['purpose']); self::assertNull($facts['release_sha256']); self::assertNull($facts['evidence_sha256']);
    self::assertSame(ProofArtifactInputs::DIRECTIONS, $facts['direction_names']);
    foreach (ProofArtifactInputs::variants() as $variant) {
      $d = $variant['direction_id']; $html = $reader->readRole($context, $d, 'html');
      self::assertSame(Credit::derive($variant['html']), $html['bytes']);
      self::assertSame('text/html; charset=UTF-8', $html['media_type']); self::assertSame(strlen($html['bytes']), $html['size_bytes']);
      self::assertSame($facts['artifact_hashes'][$d], $html['sha256']);
      self::assertSame(hash('sha256', $variant['html']), $facts['original_artifact_hashes'][$d]);
      $asset = $reader->readRole($context, $d, 'asset', 'hero');
      self::assertSame(base64_decode($variant['assets'][0]['base64']), $asset['bytes']);
      self::assertSame($asset, $reader->readRelativeAsset($context, $d, 'assets/hero.png'));
      $thumbnail = $reader->readRole($context, $d, 'thumbnail');
      self::assertSame(base64_decode($variant['thumbnail_base64']), $thumbnail['bytes']); self::assertSame('image/jpeg', $thumbnail['media_type']);
      $logo = $reader->readRelativeAsset($context, $d, Credit::ASSET_PATH);
      self::assertSame(Credit::policy()['system_asset']['sha256'], $logo['sha256']); self::assertSame(2020725, $logo['size_bytes']);
      self::assertSame($logo, $reader->readRole($context, $d, 'system_logo'));
    }
    self::assertSame(19, $calls, 'A fresh receipt-resolved package per facts/read invocation.');
    self::assertGreaterThan($calls, $this->reviewerCalls);
    self::assertFalse($this->db->inTransaction()); self::assertNotEmpty($this->db->observed);
    foreach ($this->db->observed as $query) self::assertFalse($query['locking'], $query['sql']);
    self::assertSame($state, $this->fullSnapshot()); self::assertSame($files, $this->fileSnapshot());
    $wire = json_encode($facts, JSON_THROW_ON_ERROR);
    foreach (['manifest.json', $this->temporary, 'worker_description', $this->claim['lease_token'], 'token_hash'] as $private) self::assertStringNotContainsString($private, $wire);
    self::assertArrayNotHasKey('manifest', $facts); self::assertArrayNotHasKey('receipt', $facts);
  }

  public function testUnconfiguredDependenciesAndReadyLookingUnimportedRowsRemainClosed(): void {
    $reader = $this->makeManagedReader($this->reviewerAuthority());
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'owner_review'])->execute();
    $this->denied(fn() => $reader->context(1, $this->reviewerPrincipal, 'reviewer'), 'receipt is missing');
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'not_started'])->execute();
    $this->import();
    $this->denied(fn() => (new ManagedProofReader($this->db, $this->clock))->context(1, $this->reviewerPrincipal, 'reviewer'), 'factory is unconfigured');
    $this->denied(fn() => $this->makeManagedReader()->context(1, $this->reviewerPrincipal, 'reviewer'), 'authentication is unconfigured');
    $this->denied(fn() => $reader->context(1, (object) ['authenticated' => TRUE, 'identity' => $this->reviewerIdentity], 'reviewer'), 'unauthenticated');
    $this->denied(fn() => $reader->context(0, $this->reviewerPrincipal, 'reviewer'), 'Invalid managed read');
    $this->denied(fn() => $reader->context(1, $this->reviewerPrincipal, 'admin'), 'Invalid managed read');
    $this->denied(fn() => $reader->context(99, $this->reviewerPrincipal, 'reviewer'), 'not eligible');
  }

  public function testEveryActualProducerAndNoncanonicalReviewerIdentityIsRejected(): void {
    // Trusted synthetic provenance includes all producers, not just claim owner.
    $this->provenance['producer_ids'] = ['automation:additional-producer', 'automation:synthetic-mac'];
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    foreach ([...$this->provenance['producer_ids'], 'synthetic-reviewer', 'automation:Synthetic-reviewer', 'automation:synthetic-reviewer ', TRUE, NULL] as $identity) {
      $this->reviewerIdentity = $identity;
      $this->denied(fn() => $reader->context(1, $this->reviewerPrincipal, 'reviewer'), 'unauthenticated or a producer');
    }
    // A receipt recorder is not silently promoted to producer (or authenticated).
    $this->reviewerIdentity = 'automation:synthetic-recovery';
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    self::assertSame('automation:synthetic-recovery', $reader->facts($handle)['actor']);
  }

  public function testOpaqueContextsCannotBeClonedDeserializedInventedOrTransferred(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $context = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    foreach ([new \stdClass(), clone $context, unserialize(serialize($context)), (object) ['request_id' => 1, 'purpose' => 'reviewer', 'receipt_id' => Contract::key(1)]] as $forged) {
      $this->denied(fn() => $reader->facts($forged), 'Unknown managed read context');
      $this->denied(fn() => $reader->readRole($forged, 'a', 'html'), 'Unknown managed read context');
    }
    $other = $this->makeManagedReader($this->reviewerAuthority());
    $this->denied(fn() => $other->readRelativeAsset($context, 'a', 'assets/hero.png'), 'Unknown managed read context');
    $this->denied(fn() => $reader->facts(['request_id' => 1]), 'must be of type object');
    // Arbitrary properties on the original token cannot overwrite private state.
    $context->request_id = 999; $context->purpose = 'customer'; $context->manifest = ['forged' => TRUE];
    self::assertSame(1, $reader->facts($context)['request_id']); self::assertSame('reviewer', $reader->facts($context)['purpose']);
  }

  #[DataProvider('liveMutations')]
  public function testEveryReadRechecksCurrentRowsAndFrozenInputs(string $table, array $fields, string $error): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->db->update($table)->fields($fields)->execute(); $state = $this->fullSnapshot();
    $this->denied(fn() => $reader->facts($handle), $error);
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), $error);
    $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', 'assets/hero.png'), $error);
    self::assertSame($state, $this->fullSnapshot());
  }

  public static function liveMutations(): iterable {
    yield 'verified customer' => ['famtastic_customer', ['verified_at' => NULL], 'account authority'];
    yield 'active Drupal user' => ['users_field_data', ['status' => 0], 'account authority'];
    yield 'default language user' => ['users_field_data', ['default_langcode' => 0], 'account authority'];
    yield 'active organization' => ['famtastic_organization', ['status' => 'inactive'], 'account authority'];
    yield 'active member' => ['famtastic_membership', ['status' => 'inactive'], 'account authority'];
    yield 'prospect ownership' => ['famtastic_customer_resource', ['organization_id' => 9], 'account authority'];
    yield 'archived request' => ['famtastic_project_request', ['customer_archived_at' => 1], 'not eligible'];
    yield 'request state' => ['famtastic_project_request', ['status' => 'draft'], 'not eligible'];
    yield 'selected direction' => ['famtastic_project_request', ['selected_proof_direction' => 'b'], 'not eligible'];
    yield 'selected timestamp' => ['famtastic_project_request', ['selected_proof_at' => 1], 'not eligible'];
    yield 'review state' => ['famtastic_project_request', ['proof_review_status' => 'customer_ready'], 'not pending'];
    yield 'human approver' => ['famtastic_project_request', ['proof_approved_by_uid' => 1], 'not pending'];
    yield 'approval timestamp' => ['famtastic_project_request', ['proof_approved_at' => 1], 'not pending'];
    yield 'notification timestamp' => ['famtastic_project_request', ['proof_notified_at' => 1], 'not pending'];
    yield 'brief' => ['famtastic_project_request', ['intake_data' => '{"primary_goal":"changed"}'], 'current input'];
    yield 'business' => ['famtastic_project_request', ['business_name' => 'Different'], 'current input'];
    yield 'project attachment' => ['famtastic_project_request', ['project_id' => 4], 'current input'];
    yield 'commerce attachment' => ['famtastic_project_request', ['commerce_order_id' => 4], 'current input'];
    yield 'withdrawal' => ['famtastic_request_asset', ['status' => 'withdrawn'], 'asset authority'];
    yield 'AI consent' => ['famtastic_request_asset', ['ai_use_consent' => 0], 'asset authority'];
    yield 'subject permission' => ['famtastic_request_asset', ['subject_permission_confirmed' => 0], 'asset authority'];
    yield 'transformation consent' => ['famtastic_request_asset', ['ai_transformation_consent' => 0], 'asset authority'];
    yield 'file integrity' => ['famtastic_request_asset', ['sha256' => str_repeat('e', 64)], 'asset authority'];
    yield 'ownership consent' => ['famtastic_request_asset', ['ownership_confirmed' => 0], 'integrity or rights'];
    yield 'foreign asset owner' => ['famtastic_request_asset', ['customer_id' => 9], 'ownership or status'];
    yield 'expired campaign' => ['proof_campaign', ['expires_at' => 1], 'campaign is unavailable'];
    yield 'closed campaign' => ['proof_campaign', ['status' => 'closed'], 'campaign is unavailable'];
    yield 'selected campaign' => ['proof_campaign', ['selected_variant' => 'b'], 'campaign is unavailable'];
    yield 'variant DNA' => ['proof_variant', ['design_dna__value' => '{}'], 'variant bytes differ'];
    yield 'variant format' => ['proof_variant', ['design_dna__format' => 'full_html'], 'variant bytes differ'];
    yield 'Build DNA' => ['famtastic_build_run', ['provider' => 'forged'], 'Build DNA differs'];
    yield 'journal' => ['famtastic_proof_operation', ['receipt_wire' => '{}'], 'journal bytes differ'];
    yield 'claim completion' => ['famtastic_worker_claim', ['result_sha256' => str_repeat('e', 64)], 'not committed'];
    yield 'job completion' => ['famtastic_job', ['result' => '{}'], 'not committed'];
    yield 'changed account metadata' => ['famtastic_customer', ['email' => 'changed@example.test'], 'context changed'];
  }

  public function testMissingProspectAndMissingAssetFailEvenWithHistoricalReceipt(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->db->delete('famtastic_prospect')->condition('id', 3)->execute();
    self::assertSame(Contract::key(1), Receipt::committed($this->db, 1)['receipt_id']);
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'account authority');
    $this->db->insert('famtastic_prospect')->fields(['id' => 3])->execute();
    $this->db->delete('famtastic_request_asset')->condition('id', 1)->execute();
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'asset authority');
  }

  public function testReceiptTamperingAndReviewerRevocationInvalidateHandles(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->reviewerAllowed = FALSE;
    $this->denied(fn() => $reader->facts($handle), 'unauthenticated');
    $this->reviewerAllowed = TRUE; $this->reviewerIdentity = 'automation:another-reviewer';
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'context changed');
    $this->reviewerIdentity = 'automation:synthetic-mac';
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'producer');
    $this->reviewerIdentity = 'automation:synthetic-reviewer';
    $this->db->update('famtastic_event')->fields(['payload' => '{"ok":true}'])->condition('event_key', Contract::key(1))->execute();
    $this->denied(fn() => $reader->facts($handle), 'closed schema');
  }

  public function testElapsedCampaignDeadlineIsRecheckedAtRead(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->now = (int) $this->rows('proof_campaign')[0]['expires_at'];
    $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', Credit::ASSET_PATH), 'campaign is unavailable');
  }

  public function testExactRelativePathAndRoleAllowlistNeverAcceptPathsOrManifestFromCaller(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    foreach (['hero.png', '/assets/hero.png', ' assets/hero.png', 'assets/hero.png ', 'assets//hero.png', 'assets/../index.html',
      'assets/%68ero.png', 'assets/hero.png?x=1', 'assets/hero.png#x', 'assets/hero\\.png', "assets/hero.png\0",
      'assets/Hero.png', 'assets/brand/FAMtastic-designs-logo-v1.png', 'assets/brand/famtastic-designs-logo-v1.png/extra',
      'assets/thumbnail.jpg', 'assets/manifest.json', 'a/assets/hero.png', $this->temporary . '/packages/manifest.json'] as $path) {
      $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', $path));
    }
    foreach ([['d', 'html', NULL], ['A', 'html', NULL], ['a', 'manifest', NULL], ['a', 'html', 'hero'],
      ['a', 'asset', NULL], ['a', 'asset', '../hero'], ['a', 'asset', 'Hero'], ['a', 'asset', 'hero.png'], ['a', 'system_logo', 'hero']] as [$d, $role, $id]) {
      $this->denied(fn() => $reader->readRole($handle, $d, $role, $id), 'Invalid managed read role');
    }
    $this->denied(fn() => $reader->readRole($handle, 'a', 'asset', 'system_logo'), 'role is absent');
    $this->denied(fn() => $reader->readRole($handle, 'a', 'asset', 'undeclared'), 'role is absent');
  }

  #[DataProvider('tamperedFiles')]
  public function testFactsAndByteReadsBothVerifyActualFiles(string $relative): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $path = $this->temporary . '/packages/' . $this->preparedPackage['package_id'] . '/' . $relative;
    self::assertTrue(chmod($path, 0600));
    self::assertSame(strlen('tampered synthetic file'), file_put_contents($path, 'tampered synthetic file'));
    self::assertTrue(chmod($path, 0400));
    $this->denied(fn() => $reader->facts($handle));
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'));
    $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', 'assets/hero.png'));
  }
  public static function tamperedFiles(): iterable {
    foreach (['manifest.json', 'a/index.html', 'b/assets/hero.png', 'c/' . Credit::ASSET_PATH] as $path) yield $path => [$path];
  }

  public function testReauthorizesAfterPackageVerificationAndAfterBytesBeforeReturning(): void {
    $this->import(); $baseFactory = $this->managedReaderPackageFactory();
    $factory = function (\Closure $resolver) use ($baseFactory) {
      return new ManagedReaderObservedPackage($baseFactory($resolver), function () {
        $this->db->update('famtastic_membership')->fields(['status' => 'inactive'])->execute();
      });
    };
    $reader = $this->makeManagedReader($this->reviewerAuthority(), NULL, $factory);
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->denied(fn() => $reader->facts($handle), 'account authority');
    $this->db->update('famtastic_membership')->fields(['status' => 'active'])->execute();
    $factory = function (\Closure $resolver) use ($baseFactory) {
      return new ManagedReaderObservedPackage($baseFactory($resolver), NULL, function (array $bytes) {
        $this->reviewerAllowed = FALSE; return $bytes;
      });
    };
    $reader = $this->makeManagedReader($this->reviewerAuthority(), NULL, $factory);
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'unauthenticated');
  }

  public function testFactoryMustReturnConcretePackageAndBytesMustMatchVerifiedManifest(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority(), NULL, static fn(\Closure $resolver) => new \stdClass());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->denied(fn() => $reader->facts($handle), 'invalid reader');
    $factory = $this->managedReaderPackageFactory();
    $reader = $this->makeManagedReader($this->reviewerAuthority(), NULL, static function (\Closure $resolver) use ($factory) {
      return new ManagedReaderObservedPackage($factory($resolver), NULL, static function (array $bytes) {
        $bytes['bytes'] .= 'tamper'; return $bytes;
      });
    });
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'differs from verified content');
  }

  public function testRealPackageReceiptResolverReauthorizesAfterServicePrecheck(): void {
    $this->import(); $baseFactory = $this->managedReaderPackageFactory();
    $reader = $this->makeManagedReader($this->reviewerAuthority(), NULL, function (\Closure $resolver) use ($baseFactory) {
      return new ManagedReaderObservedPackage($baseFactory($resolver), NULL, NULL, function () {
        $this->db->update('famtastic_request_asset')->fields(['ai_use_consent' => 0])->execute();
      });
    });
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer');
    $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', 'assets/hero.png'), 'asset authority');
  }

  public function testAllReaderEntryPointsRejectActiveTransactionsBeforeCallbacksOrFileReads(): void {
    $this->import(); $reader = $this->makeManagedReader($this->reviewerAuthority());
    $handle = $reader->context(1, $this->reviewerPrincipal, 'reviewer'); $calls = $this->reviewerCalls;
    $tx = $this->db->startTransaction();
    try {
      $this->denied(fn() => $reader->context(1, $this->reviewerPrincipal, 'reviewer'), 'outside transactions');
      $this->denied(fn() => $reader->facts($handle), 'outside transactions');
      $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'outside transactions');
      $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', Credit::ASSET_PATH), 'outside transactions');
      self::assertSame($calls, $this->reviewerCalls);
    }
    finally { $tx->rollBack(); unset($tx); }
  }

  public function testCustomerCannotUseImportOrManuallyReadyStateAsRelease(): void {
    $committed = $this->import(); $customer = $this->customer(); $reader = $this->makeManagedReader();
    $this->denied(fn() => $reader->context(1, $customer, 'customer'), 'release is absent');
    $this->setSyntheticReleasedState();
    $this->denied(fn() => $reader->context(1, $customer, 'customer'), 'verifier is unconfigured');
    $reader = $this->makeManagedReader(NULL, static fn() => TRUE);
    $this->denied(fn() => $reader->context(1, $customer, 'customer'), 'attestation is invalid');
    $grant = $this->syntheticReleaseAttestation($committed);
    $reader = $this->makeManagedReader(NULL, static fn() => $grant);
    foreach ([$this->customer(0, FALSE), $this->customer(9), (object) ['uid' => 1, 'authenticated' => TRUE]] as $foreign) {
      $this->denied(fn() => $reader->context(1, $foreign, 'customer'), 'principal does not own');
    }
    $this->db->update('famtastic_project_request')->fields(['proof_approved_by_uid' => 1])->execute();
    $this->denied(fn() => $reader->context(1, $customer, 'customer'), 'release is absent');
  }

  public function testSyntheticExactReleaseAuthorityCanReadAndIsRevalidatedOnEveryUse(): void {
    $committed = $this->import(); $this->setSyntheticReleasedState();
    $grant = $this->syntheticReleaseAttestation($committed); $calls = 0; $allowed = TRUE;
    $customer = $this->customer();
    // Frozen test-only decision, not a production verifier or echo of its input.
    $release = function (array $current, array $request, AccountInterface $principal) use ($committed, $grant, $customer, &$calls, &$allowed): array {
      self::assertFalse($this->db->inTransaction()); ++$calls;
      if (!$allowed || $current !== $committed || $principal !== $customer || (int) $request['proof_approved_at'] !== $grant['proof_approved_at']) throw new \RuntimeException('Synthetic release revoked.');
      return $grant;
    };
    $reader = $this->makeManagedReader(NULL, $release); $before = $this->fullSnapshot();
    $handle = $reader->context(1, $customer, 'customer'); $facts = $reader->facts($handle);
    self::assertSame('customer:1:uid:1', $facts['actor']); self::assertSame('customer', $facts['purpose']);
    self::assertSame($grant['release_sha256'], $facts['release_sha256']); self::assertSame($grant['evidence_sha256'], $facts['evidence_sha256']);
    self::assertSame($facts['artifact_hashes']['a'], $reader->readRole($handle, 'a', 'html')['sha256']);
    self::assertSame(24, $reader->readRelativeAsset($handle, 'a', 'assets/hero.png')['size_bytes']);
    self::assertGreaterThan(5, $calls); self::assertSame($before, $this->fullSnapshot());
    $allowed = FALSE;
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'Synthetic release revoked');
  }

  #[DataProvider('invalidReleaseAttestations')]
  public function testReleaseVerifierResultMustBeExactAndClosed(string $field, mixed $value, string $error): void {
    $committed = $this->import(); $this->setSyntheticReleasedState();
    $grant = $this->syntheticReleaseAttestation($committed); $grant[$field] = $value;
    $reader = $this->makeManagedReader(NULL, static fn() => $grant);
    $this->denied(fn() => $reader->context(1, $this->customer(), 'customer'), $error);
  }
  public static function invalidReleaseAttestations(): iterable {
    foreach (['receipt_id' => 'proof-import:job:2', 'receipt_sha256' => str_repeat('e', 64), 'request_id' => 2,
      'customer_id' => 2, 'campaign_id' => 2, 'package_manifest_sha256' => str_repeat('e', 64), 'producer_ids' => ['automation:other-producer'],
      'proof_review_status' => 'notified', 'proof_approved_at' => 1] as $field => $value) yield $field => [$field, $value, 'differs from current receipt'];
    yield 'untyped request' => ['request_id', '1', 'differs from current receipt'];
    yield 'evidence digest' => ['evidence_sha256', '', 'Invalid operation digest'];
    yield 'decision digest' => ['release_sha256', TRUE, 'Invalid operation digest'];
    yield 'extra authority' => ['approved', TRUE, 'closed schema'];
  }

  public function testMissingReleaseFieldChangedEvidenceAndRevokedCustomerInvalidateRead(): void {
    $committed = $this->import(); $this->setSyntheticReleasedState();
    $grant = $this->syntheticReleaseAttestation($committed); $currentGrant = $grant;
    $reader = $this->makeManagedReader(NULL, function () use (&$currentGrant) { return $currentGrant; });
    $customer = $this->customer(); $handle = $reader->context(1, $customer, 'customer');
    unset($currentGrant['receipt_id']);
    $this->denied(fn() => $reader->facts($handle), 'closed schema');
    foreach (['release_sha256', 'evidence_sha256'] as $hash) {
      $currentGrant = array_replace($grant, [$hash => str_repeat('f', 64)]);
      $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'context changed');
    }
    $currentGrant = $grant;
    $this->db->update('users_field_data')->fields(['status' => 0])->execute();
    $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', Credit::ASSET_PATH), 'account authority');
  }

  public function testNotificationNeedsFreshExactReleaseAttestationAndSelectionStaysClosed(): void {
    $committed = $this->import(); $this->setSyntheticReleasedState();
    $grant = $this->syntheticReleaseAttestation($committed);
    $reader = $this->makeManagedReader(NULL, function () use (&$grant) { return $grant; });
    $customer = $this->customer(); $handle = $reader->context(1, $customer, 'customer');
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'notified', 'proof_notified_at' => $this->now])->execute();
    $this->denied(fn() => $reader->facts($handle), 'differs from current receipt');
    $grant['proof_review_status'] = 'notified';
    $this->denied(fn() => $reader->facts($handle), 'context changed');
    $fresh = $reader->context(1, $customer, 'customer');
    self::assertSame('notified', $reader->facts($fresh)['proof_review_status']);
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'selected_proof_at' => $this->now])->execute();
    $this->denied(fn() => $reader->readRole($fresh, 'a', 'html'), 'not eligible');
  }

  public function testLiveCustomerPrincipalAuthenticationIsNotCachedInHandle(): void {
    $committed = $this->import(); $this->setSyntheticReleasedState();
    $grant = $this->syntheticReleaseAttestation($committed);
    $authenticated = TRUE; $uid = 1;
    $customer = $this->createMock(AccountInterface::class);
    $customer->method('id')->willReturnCallback(function () use (&$uid) { return $uid; });
    $customer->method('isAuthenticated')->willReturnCallback(function () use (&$authenticated) { return $authenticated; });
    $reader = $this->makeManagedReader(NULL, static fn() => $grant);
    $handle = $reader->context(1, $customer, 'customer');
    $authenticated = FALSE;
    $this->denied(fn() => $reader->readRole($handle, 'a', 'html'), 'principal does not own');
    $authenticated = TRUE; $uid = 9;
    $this->denied(fn() => $reader->readRelativeAsset($handle, 'a', 'assets/hero.png'), 'principal does not own');
  }

  private function customer(int $uid = 1, bool $authenticated = TRUE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid); $account->method('isAuthenticated')->willReturn($authenticated);
    return $account;
  }

  /** Intentionally synthetic lifecycle write; DOES NOT implement release/QA. */
  private function setSyntheticReleasedState(): void {
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'customer_ready', 'proof_approved_at' => $this->now])->condition('id', 1)->execute();
  }

  /** Frozen synthetic authority for exercising the exact dependency boundary. */
  private function syntheticReleaseAttestation(array $committed): array {
    $r = $committed['receipt'];
    return ['receipt_id' => $committed['receipt_id'], 'receipt_sha256' => $committed['receipt_sha256'], 'request_id' => $r['request_id'],
      'customer_id' => $r['customer_id'], 'campaign_id' => $r['campaign_entity_id'], 'package_manifest_sha256' => $r['package_manifest_sha256'],
      'producer_ids' => $r['producer_ids'], 'proof_review_status' => 'customer_ready', 'proof_approved_at' => $this->now,
      'evidence_sha256' => hash('sha256', 'SYNTHETIC QA NOT IMPLEMENTED'), 'release_sha256' => hash('sha256', 'SYNTHETIC RELEASE NOT IMPLEMENTED')];
  }

  private function denied(callable $call, string $message = ''): void {
    $failure = NULL;
    try { $call(); } catch (\Throwable $e) { $failure = $e; }
    self::assertNotNull($failure, 'Expected managed reader rejection.');
    self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $failure, 'Do not disguise failed authority assertions as rejection.');
    self::assertTrue($failure instanceof \RuntimeException || $failure instanceof \LogicException || $failure instanceof \TypeError,
      'Unexpected failure type: ' . get_class($failure));
    if ($message !== '') self::assertStringContainsString($message, $failure->getMessage());
  }

  private function fullSnapshot(): array {
    $state = $this->snapshot();
    foreach (['users_field_data', 'famtastic_organization', 'famtastic_customer_resource', 'famtastic_prospect', 'famtastic_worker_mutex'] as $table) $state[$table] = $this->rows($table);
    return $state;
  }

  private function fileSnapshot(): array {
    $snapshot = [];
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS)) as $file) {
      $snapshot[substr($file->getPathname(), strlen($this->temporary))] = [hash_file('sha256', $file->getPathname()), $file->getSize(), $file->getPerms() & 0777];
    }
    ksort($snapshot); return $snapshot;
  }
}

/** Decorates REAL package operations; only the after-I/O race/fault is synthetic. */
final class ManagedReaderObservedPackage extends ManagedProofArtifactPackage {
  public function __construct(private readonly ManagedProofArtifactPackage $inner,
    private readonly ?\Closure $afterVerify = NULL, private readonly ?\Closure $afterRead = NULL,
    private readonly ?\Closure $beforeRead = NULL) {}

  public function verifyPreparedPackage(string $packageId, string $packageHash, string $bundleId, string $preparedHash): array {
    $facts = $this->inner->verifyPreparedPackage($packageId, $packageHash, $bundleId, $preparedHash);
    if ($this->afterVerify) ($this->afterVerify)();
    return $facts;
  }

  public function read(string $receiptId, string $direction, string $role, ?string $assetId = NULL): array {
    if ($this->beforeRead) ($this->beforeRead)();
    $bytes = $this->inner->read($receiptId, $direction, $role, $assetId);
    return $this->afterRead ? ($this->afterRead)($bytes) : $bytes;
  }
}
