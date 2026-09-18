<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/Service/OfflinePrepaymentService.php';

final class OfflinePrepaymentServiceTest extends TestCase {
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
