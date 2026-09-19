<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/OfflinePrepaymentService.php';

final class OfflinePrepaymentServiceTest extends TestCase {
  public function testOnlyExactAuthorizedPrepaymentCanBridgeLegacyStagingGuard(): void {
    $r = ['id' => 17, 'public_id' => '4940a4fd-91af-40c4-b8a5-2b4dad1a3b95', 'customer_id' => 15, 'organization_id' => 15, 'status' => 'submitted', 'commerce_order_id' => NULL];
    $o = ['website_request_id' => 17, 'customer_id' => 15, 'organization_id' => 15, 'status' => 'prepaid_held', 'sku' => 'PRIVATE-STOCKANDSHIP98-200', 'offered_amount_minor' => 20000, 'currency' => 'usd', 'commerce_order_id' => 21];
    $scope = OfflinePrepaymentService::stockandshipScope();
    $d = ['request_id' => 17, 'request_public_id' => $r['public_id'], 'customer_id' => 15, 'organization_id' => 15, 'scope' => $scope, 'scope_hash' => hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 'hold' => 'awaiting_customer_terms_domain_and_final_acceptance', 'client_acceptance' => NULL, 'evidence_source' => 'Fritz Medine explicit confirmation'];
    $p = ['order_id' => 21, 'payment_state' => 'completed', 'received' => '200.00', 'outstanding' => '0.00', 'currency' => 'USD', 'order_state' => 'draft', 'launch_authorized' => FALSE];
    self::assertTrue(OfflinePrepaymentService::matchesSelectedStagingEvidence($r, $o, $d, $p));
    self::assertTrue(OfflinePrepaymentService::matchesSelectedStagingEvidence(array_replace($r, ['commerce_order_id' => 21]), $o, $d, $p));
    self::assertTrue(OfflinePrepaymentService::matchesSelectedStagingEvidence($r, $o, array_replace($d, ['hold' => 'awaiting_exact_staging_acceptance_and_launch_readiness']), $p));
    foreach ([['id' => 16], ['customer_id' => 14], ['organization_id' => 14], ['commerce_order_id' => 999], ['status' => 'converted']] as $bad) self::assertFalse(OfflinePrepaymentService::matchesSelectedStagingEvidence(array_replace($r, $bad), $o, $d, $p));
    foreach ([['payment_state' => 'pending'], ['received' => '199.00'], ['outstanding' => '1.00'], ['launch_authorized' => TRUE], ['order_id' => 999]] as $bad) self::assertFalse(OfflinePrepaymentService::matchesSelectedStagingEvidence($r, $o, $d, array_replace($p, $bad)));
    self::assertFalse(OfflinePrepaymentService::matchesSelectedStagingEvidence($r, array_replace($o, ['status' => 'revoked']), $d, $p));
    self::assertFalse(OfflinePrepaymentService::matchesSelectedStagingEvidence($r, $o, array_replace($d, ['evidence_source' => 'customer_assertion']), $p));
    foreach ([['scope_hash' => 'changed'], ['hold' => 'ready'], ['client_acceptance' => TRUE], ['scope' => array_replace($scope, ['amount' => '199.00'])]] as $bad) self::assertFalse(OfflinePrepaymentService::matchesSelectedStagingEvidence($r, $o, array_replace($d, $bad), $p));
  }
  public function testImmutableOfferKeyIsStableAndRequestSpecific(): void {
    $id = OfflinePrepaymentService::offerId('request-one');
    self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id);
    self::assertSame($id, OfflinePrepaymentService::offerId('request-one'));
    self::assertNotSame($id, OfflinePrepaymentService::offerId('request-two'));
    self::assertSame('200.00', OfflinePrepaymentService::stockandshipScope()['amount']);
  }

  public function testVerifiedExactCustomerIsRequired(): void {
    OfflinePrepaymentService::assertIdentity(['customer_id' => 15], ['id' => 15, 'verified_at' => 123, 'email' => 'client@example.test'], 15, 'client@example.test');
    self::assertTrue(TRUE);
    foreach ([['customer_id' => 14], ['customer_id' => 0]] as $request) {
      try { OfflinePrepaymentService::assertIdentity($request, ['id' => 15, 'verified_at' => 123, 'email' => 'client@example.test'], 15, 'client@example.test'); self::fail('Cross-account accepted'); }
      catch (\RuntimeException $error) { self::assertSame('prepayment_account_mismatch', $error->getMessage()); }
    }
    foreach ([['id' => 15, 'verified_at' => NULL, 'email' => 'client@example.test'], ['id' => 15, 'verified_at' => 123, 'email' => 'different@example.test']] as $customer) {
      try { OfflinePrepaymentService::assertIdentity(['customer_id' => 15], $customer, 15, 'client@example.test'); self::fail('Unverified/mismatched account accepted'); }
      catch (\RuntimeException $error) { self::assertSame('prepayment_account_mismatch', $error->getMessage()); }
    }
  }

  public function testCompletionRejectsWrongExpiredUsedCodeTermsAndUnsafeDomain(): void {
    $code = str_repeat('a', 48);
    $data = ['scope' => ['version' => 'v1'], 'scope_hash' => 'exact-scope', 'completion' => ['state' => 'issued', 'hash' => hash('sha256', $code), 'expires_at' => 1001]];
    $input = ['accept_terms' => TRUE, 'terms_version' => 'v1', 'scope_hash' => 'exact-scope', 'domain_choice' => 'new_domain', 'domain' => 'example.com'];
    OfflinePrepaymentService::assertCompletion($data, $code, $input, 1000);
    self::assertTrue(TRUE);
    $invalid = [
      [$data, str_repeat('b', 48), $input, 1000],
      [$data, $code, $input, 1001],
      [array_replace_recursive($data, ['completion' => ['state' => 'consumed']]), $code, $input, 1000],
      [$data, $code, array_replace($input, ['accept_terms' => 'yes']), 1000],
      [$data, $code, array_replace($input, ['terms_version' => 'old']), 1000],
      [$data, $code, array_replace($input, ['scope_hash' => 'changed']), 1000],
      [$data, $code, array_replace($input, ['domain_choice' => 'purchase_now']), 1000],
      [$data, $code, array_replace($input, ['domain' => 'https://evil.test/path']), 1000],
    ];
    foreach ($invalid as $args) {
      try { OfflinePrepaymentService::assertCompletion(...$args); self::fail('Unsafe completion accepted'); }
      catch (\RuntimeException $error) { self::assertStringStartsWith('completion_', $error->getMessage()); }
    }
  }
}
