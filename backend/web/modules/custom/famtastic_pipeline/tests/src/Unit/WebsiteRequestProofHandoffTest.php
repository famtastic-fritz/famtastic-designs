<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Form\WebsiteRequestProofReviewForm;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

/** Exercises the actual query against isolated in-memory SQLite rows. */
final class WebsiteRequestProofHandoffTest extends UnitTestCase {

  private Connection $database;
  private CustomerPortalService $portal;
  private EntityTypeManagerInterface $entities;
  private EntityStorageInterface $campaigns;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->database = new Connection(Connection::open($options), $options);
    $this->database->query('CREATE TABLE famtastic_project_request (id INTEGER PRIMARY KEY, customer_id INTEGER, project_name TEXT, status TEXT, proof_review_status TEXT, proof_campaign_id INTEGER)');
    $this->database->query('CREATE TABLE famtastic_customer (id INTEGER PRIMARY KEY, display_name TEXT, email TEXT)');
    $this->database->query('CREATE TABLE famtastic_job (id INTEGER PRIMARY KEY, job_key TEXT, job_type TEXT, status TEXT, attempts INTEGER, max_attempts INTEGER)');
    $this->database->insert('famtastic_project_request')->fields([
      'id' => 14, 'customer_id' => 12, 'project_name' => 'Customer website', 'status' => 'submitted', 'proof_review_status' => 'not_started', 'proof_campaign_id' => NULL,
    ])->execute();
    $this->database->insert('famtastic_customer')->fields(['id' => 12, 'display_name' => 'Customer', 'email' => 'customer@example.test'])->execute();

    $this->campaigns = $this->createMock(EntityStorageInterface::class);
    $variants = $this->createMock(EntityStorageInterface::class);
    $variants->method('loadMultiple')->willReturn([]);
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $variants->method('getQuery')->willReturn($query);
    $this->entities = $this->createMock(EntityTypeManagerInterface::class);
    $this->entities->method('getStorage')->willReturnCallback(fn(string $type) => $type === 'proof_campaign' ? $this->campaigns : $variants);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($this->portal, 'database'))->setValue($this->portal, $this->database);
    (new \ReflectionProperty($this->portal, 'entities'))->setValue($this->portal, $this->entities);
  }

  private function job(int $id, string $key, string $status = 'queued', string $type = 'proof.generate'): void {
    $this->database->insert('famtastic_job')->fields([
      'id' => $id, 'job_key' => $key, 'job_type' => $type, 'status' => $status, 'attempts' => 0, 'max_attempts' => 5,
    ])->execute();
  }

  private function localHandoff(): void {
    $this->database->update('famtastic_project_request')->fields(['proof_campaign_id' => 53])->condition('id', 14)->execute();
    $campaign = $this->createMock(ProofCampaign::class);
    $campaign->method('get')->willReturnCallback(static fn(string $field) => (object) ['value' => [
      'generation_status' => 'waiting_callback', 'studio_job_id' => 'local-1234567890abcdef1234567890abcdef',
    ][$field] ?? NULL]);
    $this->campaigns->method('load')->with(53)->willReturn($campaign);
  }

  public function testRequestFourteenNeverReadsRequestOneHundredFortyJob(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:first');
    $this->job(2, 'website_proof.generate.v1:request:140:brief:other', 'failed');
    $status = $this->portal->websiteRequestProofHandoff(14);
    $this->assertSame(1, $status['job_id']);
    $this->assertSame('queued', $status['state']);
  }

  public function testForeignRequestCannotMakeAnUnqueuedRequestLookQueued(): void {
    $this->job(2, 'website_proof.generate.v1:request:140:brief:other');
    $status = $this->portal->websiteRequestProofHandoff(14);
    $this->assertNull($status['job_id']);
    $this->assertSame('needs_attention', $status['state']);
  }

  public function testLegacyExactKeyIsPreservedAndNewestBriefWins(): void {
    $this->job(1, 'website_proof.generate.v1:request:14');
    $this->assertSame(1, $this->portal->websiteRequestProofHandoff(14)['job_id']);
    $this->job(2, 'website_proof.generate.v1:request:14:brief:new', 'running');
    $status = $this->portal->websiteRequestProofHandoff(14);
    $this->assertSame(2, $status['job_id']);
    $this->assertSame('preparing', $status['state']);
  }

  public function testAnotherJobTypeCannotSupplyTheProofStatus(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:valid');
    $this->job(2, 'website_proof.generate.v1:request:14:brief:other', 'failed', 'outreach.send');
    $this->assertSame(1, $this->portal->websiteRequestProofHandoff(14)['job_id']);
  }

  public function testUnderscoreInRoutineNameIsLiteral(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:valid');
    $this->job(2, 'websiteXproof.generate.v1:request:14:brief:wrong-routine', 'failed');
    $this->assertSame(1, $this->portal->websiteRequestProofHandoff(14)['job_id']);
  }

  public function testCompletedLocalHandoffRequiresAttentionWithoutProofClaims(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:valid', 'completed');
    $this->localHandoff();
    $status = $this->portal->websiteRequestProofHandoff(14);
    $this->assertSame('waiting_for_provider', $status['state']);
    $this->assertStringContainsString('needs FAMtastic attention', $status['label']);
    $this->assertStringContainsString('working concepts have not been returned', $status['detail']);
    $this->assertSame(1, (int) $this->database->select('famtastic_job')->countQuery()->execute()->fetchField());
  }

  public function testCompletedJobWithNoArtifactsOrHandoffDoesNotLookQueued(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:valid', 'completed');
    $status = $this->portal->websiteRequestProofHandoff(14);
    $this->assertSame('needs_attention', $status['state']);
    $this->assertStringContainsString('ended without a complete proof set', $status['detail']);
  }

  public function testFailedJobAndMissingRequestStayExplicit(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:valid', 'failed');
    $this->assertSame('needs_attention', $this->portal->websiteRequestProofHandoff(14)['state']);
    $this->assertNull($this->portal->websiteRequestProofHandoff(999));
  }

  public function testStaffReviewShowsTheActualLocalProviderBlock(): void {
    $this->job(1, 'website_proof.generate.v1:request:14:brief:valid', 'completed');
    $this->localHandoff();
    $form = new WebsiteRequestProofReviewForm($this->database, $this->entities, $this->portal, $this->createMock(AccountProxyInterface::class));
    $form->setStringTranslation($this->getStringTranslationStub());
    $built = $form->buildForm([], new FormState(), 14);
    $this->assertStringContainsString('Proof generation needs FAMtastic attention', (string) $built['summary']['#title']);
    $this->assertContains('messages--warning', $built['handoff']['#attributes']['class']);
    $this->assertStringContainsString('working concepts have not been returned', $built['handoff']['status']['#markup']);
    $this->assertArrayNotHasKey('actions', $built);
  }

}
