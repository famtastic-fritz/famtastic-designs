<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService;
use Drupal\famtastic_pipeline\Service\StagingReceiptService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class StagingReceiptPacketIdentityTest extends UnitTestCase {

  private const SOURCE_DIGEST = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
  private const OUTPUT_DIGEST = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

  /** Exact source selection identity accepts a distinct generated output. */
  public function testExactRegisteredPacketIdentityAcceptsDistinctOutputDigest(): void {
    $packet = $this->packet();
    $receipt = $this->receipt($packet);
    StagingReceiptService::assertReceiptMatchesRegisteredPacket($receipt, $packet);
    $this->assertNotSame($receipt['artifact_sha256'], $receipt['selected_artifact_sha256']);
  }

  /** @dataProvider receiptMismatchProvider */
  public function testReceiptMismatchIsRejected(string $field, string $value, string $message): void {
    $packet = $this->packet();
    $receipt = $this->receipt($packet);
    $receipt[$field] = $value;
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    StagingReceiptService::assertReceiptMatchesRegisteredPacket($receipt, $packet);
  }

  public static function receiptMismatchProvider(): array {
    return [
      'packet' => ['packet_id', 'packet-other', 'packet_id'],
      'idempotency' => ['idempotency_key', 'build-other', 'idempotency_key'],
      'request' => ['request_id', 'request-other', 'request_id'],
      'project' => ['project_id', '999', 'project_id'],
      'manifest' => ['artifact_manifest_sha256', str_repeat('c', 64), 'artifact_manifest_sha256'],
      'direction' => ['selected_direction_id', 'direction-b', 'selected direction'],
      'selected source bytes' => ['selected_artifact_sha256', str_repeat('c', 64), 'selected artifact'],
    ];
  }

  public function testCheckoutRequiresDurableAccountReviewAcceptance(): void {
    $packet = $this->packet();
    $receipt = $this->receipt($packet);
    $row = [
      'staging_status' => 'deployed',
      'staging_review_status' => 'accepted',
      'staging_receipt_hash' => str_repeat('d', 64),
      'staging_receipt_json' => json_encode($receipt, JSON_THROW_ON_ERROR),
    ];
    $this->assertTrue(StagingReceiptService::checkoutGateSatisfied($row));
    $row['staging_review_status'] = 'pending';
    $this->assertFalse(StagingReceiptService::checkoutGateSatisfied($row));
  }

  /** @return array<string, mixed> */
  private function packet(): array {
    $artifacts = [
      ['role' => 'selected_preview', 'path' => 'packet-files/reference-previews/direction-a/index.html', 'sha256' => self::SOURCE_DIGEST, 'bytes' => 128],
      ['role' => 'source_material', 'path' => 'packet-files/brief.json', 'sha256' => str_repeat('e', 64), 'bytes' => 64],
    ];
    return [
      'packet_id' => 'packet-123',
      'idempotency_key' => 'build-request-123',
      'request_id' => 'request-123',
      'project_id' => '42',
      'selected_direction_ids' => ['direction-a'],
      'artifacts' => $artifacts,
      'artifact_manifest_sha256' => SiteStudioBuildPacketService::artifactManifestDigest($artifacts),
      'selected_artifacts' => [[
        'direction_id' => 'direction-a',
        'source_artifact_path' => 'packet-files/reference-previews/direction-a/index.html',
        'source_artifact_sha256' => self::SOURCE_DIGEST,
        'source_artifact_bytes' => 128,
      ]],
    ];
  }

  /** @param array<string, mixed> $packet
   * @return array<string, mixed>
   */
  private function receipt(array $packet): array {
    return [
      'schema' => 'famtastic.site-studio.staging-receipt.v1',
      'status' => 'deployed',
      'packet_id' => $packet['packet_id'],
      'idempotency_key' => $packet['idempotency_key'],
      'request_id' => $packet['request_id'],
      'project_id' => $packet['project_id'],
      'selected_direction_id' => 'direction-a',
      'selected_artifact_sha256' => self::SOURCE_DIGEST,
      'artifact_manifest_sha256' => $packet['artifact_manifest_sha256'],
      // The generated staging output is purposefully different from source.
      'artifact_sha256' => self::OUTPUT_DIGEST,
    ];
  }

}
