<?php
declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\SelectedSourceIntent;
use PHPUnit\Framework\TestCase;

final class SelectedRequestBindingTest extends TestCase {
  private function row(array $intake = []): array {
    return ['public_id' => 'synthetic-request', 'customer_id' => 10, 'proof_campaign_id' => 24,
      'project_type' => 'new_website', 'intake_data' => json_encode($intake + ['page_count' => 1, 'page_list' => 'Home'])];
  }

  public function testBindingIgnoresRawFormattingAuditHistoryAndProjectAllocation(): void {
    $row = $this->row();
    $same = $row + ['project_id' => 123, 'changed' => 456];
    $same['intake_data'] = "{\n\"page_list\":\"Home\",\"page_count\":1,\"request_submission\":{\"raw_json\":\"different transport\"},\"authored_content_history\":[{}]}";
    self::assertSame(SelectedSourceIntent::requestBinding($row), SelectedSourceIntent::requestBinding($same));
    self::assertSame('famtastic.selected-request-binding.v1', SelectedSourceIntent::requestBinding($row)['schema']);
  }

  public function testEveryScopeAndTenantChangeInvalidatesProducerBinding(): void {
    $row = $this->row(); $binding = SelectedSourceIntent::requestBinding($row);
    foreach ([['page_count' => 2, 'page_list' => "Home\nAbout"], ['ecommerce_details' => 'Catalog'],
      ['required_features' => 'Bookings'], ['integrations' => 'CRM'], ['custom_needs' => 'Membership'],
      ['authored_content' => ['pages' => [['record_id' => 'new-copy', 'text' => ['heading' => 'Changed']]]]]] as $scope) {
      self::assertNotSame($binding, SelectedSourceIntent::requestBinding($this->row($scope)));
    }
    foreach (['public_id' => 'other-request', 'customer_id' => 20, 'proof_campaign_id' => 25, 'project_type' => 'online_store'] as $field => $value) {
      self::assertNotSame($binding, SelectedSourceIntent::requestBinding(array_replace($row, [$field => $value])));
    }
  }

  public function testAssetAuthorityAndContentWithdrawalInvalidateBinding(): void {
    $row = $this->row(['authored_content' => ['pages' => [['record_id' => 'copy-1', 'text' => ['body' => 'Approved copy']]]]]);
    $assets = [['public_id' => 'asset-1', 'sha256' => str_repeat('a', 64), 'ownership_confirmed' => TRUE, 'ai_use_consent' => TRUE]];
    $binding = SelectedSourceIntent::requestBinding($row, $assets);
    self::assertNotSame($binding, SelectedSourceIntent::requestBinding($row));
    $assets[0]['ai_use_consent'] = FALSE;
    self::assertNotSame($binding, SelectedSourceIntent::requestBinding($row, $assets));
    self::assertNotSame($binding, SelectedSourceIntent::requestBinding($this->row(), $assets));
  }
}
