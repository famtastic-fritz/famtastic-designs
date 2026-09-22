<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\FreshProofBinding;
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ManagedProofImportFixture;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/Fixtures/ManagedProofImportFixture.php';

/** Status only: real import/SQLite/private files, synthetic producer authority. */
final class ManagedProofImportHandoffTest extends ManagedProofImportFixture {
  private function handoff(): array {
    return FreshProofBinding::handoff($this->db, $this->rows('famtastic_project_request')[0], $this->now);
  }

  public function testCommittedImportProjectsIndependentPendingReviewWithoutWrites(): void {
    $this->import();
    $before = $this->snapshot();
    $result = $this->handoff();
    self::assertSame('owner_review', $result['state']);
    self::assertSame('Proof files saved; independent review pending', $result['label']);
    self::assertSame('completed', $result['job_status']);
    self::assertStringNotContainsString('queued', $result['detail']);
    self::assertStringNotContainsString('owner approval', $result['detail']);
    self::assertStringContainsString('not been released', $result['detail']);
    self::assertSame($before, $this->snapshot());
    self::assertArrayNotHasKey('receipt', $result);
    self::assertArrayNotHasKey('package_id', $result);
  }

  public function testReviewFlagWithoutImportReceiptRemainsUnconfirmed(): void {
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'owner_review'])->condition('id', 1)->execute();
    $before = $this->snapshot();
    self::assertSame('needs_attention', $this->handoff()['state']);
    self::assertSame($before, $this->snapshot());
  }

  public function testFreshAdmissionValidationRemainsStrictAfterImport(): void {
    $this->import();
    $before = $this->snapshot();
    $this->reject(fn() => FreshProofBinding::read($this->db, FreshProofBinding::event($this->db, 1),
      $this->rows('famtastic_project_request')[0]), 'differs from current input');
    self::assertSame($before, $this->snapshot());
  }

  #[DataProvider('changedFacts')]
  public function testChangedFactsCannotProjectCurrentImportedProofs(string $table, string $field, mixed $value): void {
    $this->import();
    $this->db->update($table)->fields([$field => $value])->execute();
    $before = $this->snapshot();
    self::assertSame('needs_attention', $this->handoff()['state']);
    self::assertSame($before, $this->snapshot());
  }

  public static function changedFacts(): iterable {
    yield 'authored name' => ['famtastic_project_request', 'project_name', 'Changed after import'];
    yield 'authored intake' => ['famtastic_project_request', 'intake_data', '{"primary_goal":"Changed"}'];
    yield 'selected direction' => ['famtastic_project_request', 'selected_proof_direction', 'b'];
    yield 'project binding' => ['famtastic_project_request', 'project_id', 99];
    yield 'commercial binding' => ['famtastic_project_request', 'commerce_order_id', 99];
    yield 'withdrawn asset' => ['famtastic_request_asset', 'status', 'withdrawn'];
    yield 'changed consent' => ['famtastic_request_asset', 'ai_use_consent', 0];
    yield 'changed proof metadata' => ['proof_variant', 'design_dna__value', '{}'];
    yield 'changed build' => ['famtastic_build_run', 'artifact_checksum', str_repeat('0', 64)];
    yield 'changed claim receipt' => ['famtastic_worker_claim', 'result_sha256', str_repeat('0', 64)];
    yield 'changed job receipt' => ['famtastic_job', 'result', '{}'];
    // Receipt-aware release/selection projection is not installed yet. Never
    // infer managed customer visibility solely from a legacy review flag.
    yield 'unproved QA state' => ['famtastic_project_request', 'proof_review_status', 'customer_ready'];
    yield 'unproved notification state' => ['famtastic_project_request', 'proof_review_status', 'notified'];
    yield 'unproved selected state' => ['famtastic_project_request', 'proof_review_status', 'selected'];
  }
}
