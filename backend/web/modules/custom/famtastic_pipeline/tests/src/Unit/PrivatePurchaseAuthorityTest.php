<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\ApprovedPrivatePurchaseAuthority;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseAuthorityInterface;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/src/Service/PrivatePurchaseAuthorityInterface.php';
require_once dirname(__DIR__, 3) . '/src/Service/ApprovedPrivatePurchaseAuthority.php';
require_once dirname(__DIR__, 3) . '/src/Service/PrivatePurchaseService.php';

/** Pure authority tests; no database, settings, secrets or providers. */
final class PrivatePurchaseAuthorityTest extends UnitTestCase {
  private function synthetic(): array {
    return [
      'request_id' => 901, 'public_id' => 'c0942382-2f3e-4604-978a-7c0bff008fac',
      'customer_id' => 701, 'organization_id' => 801, 'prospect_id' => 901,
      'email' => 'buyer@example.test', 'sku' => 'SYNTHETIC-PRIVATE-199',
      'scope_hash' => hash('sha256', json_encode(['synthetic' => TRUE])),
      'policy' => 'synthetic-only-v1', 'event_key' => 'synthetic-scope:901',
      'authority' => 'Synthetic test authority; not owner approval',
    ];
  }

  private function authority(array $definition): PrivatePurchaseAuthorityInterface {
    return new class($definition) implements PrivatePurchaseAuthorityInterface {
      public function __construct(public array $definition) {}
      public function reunion(): array { return $this->definition; }
    };
  }

  public function testDefaultAuthorityPreservesExactClosedProductionIdentity(): void {
    self::assertSame([
      'request_id' => 16, 'public_id' => PrivatePurchaseService::REUNION,
      'customer_id' => 14, 'organization_id' => 14, 'prospect_id' => 298,
      'email' => 'mbshclassof2000@gmail.com', 'sku' => 'PRIVATE-REUNION16-199',
      'scope_hash' => PrivatePurchaseService::REUNION_HASH,
      'policy' => PrivatePurchaseService::REUNION_POLICY,
      'event_key' => 'private-scope:request:16:class-of-2000-v1',
      'authority' => 'Fritz Medine explicit approval',
    ], (new ApprovedPrivatePurchaseAuthority())->reunion());
    $service = new PrivatePurchaseService();
    self::assertSame(16, $service->reunionRequestId());
    self::assertSame(PrivatePurchaseService::REUNION, $service->reunionPublicId());
    self::assertTrue((new \ReflectionClass(ApprovedPrivatePurchaseAuthority::class))->isFinal());
  }

  public function testInjectedAuthorityIsFrozenWithoutChangingDefault(): void {
    $authority = $this->authority($this->synthetic());
    $service = new PrivatePurchaseService($authority);
    $authority->definition = (new ApprovedPrivatePurchaseAuthority())->reunion();
    self::assertSame(901, $service->reunionRequestId());
    self::assertSame($this->synthetic()['public_id'], $service->reunionPublicId());
    self::assertSame(16, (new PrivatePurchaseService())->reunionRequestId());
  }

  public function testMalformedDefinitionsFailClosed(): void {
    foreach ([['request_id' => '901'], ['request_id' => 0], ['request_id' => 17],
      ['customer_id' => -1], ['organization_id' => NULL], ['prospect_id' => FALSE],
      ['public_id' => PrivatePurchaseService::STOCK], ['public_id' => 'invalid'],
      ['email' => 'invalid'], ['email' => "buyer@example.test\nother"], ['scope_hash' => 'bad'],
      ['policy' => ''], ['event_key' => []], ['authority' => str_repeat('x', 255)], ['unexpected' => TRUE]] as $bad) {
      try { new PrivatePurchaseService($this->authority(array_replace($this->synthetic(), $bad))); self::fail('Malformed authority accepted'); }
      catch (\InvalidArgumentException $error) { self::assertSame('private_authority_invalid', $error->getMessage()); }
    }
    $missing = $this->synthetic(); unset($missing['authority']);
    $this->expectException(\InvalidArgumentException::class);
    new PrivatePurchaseService($this->authority($missing));
  }

  public function testSyntheticScopeRetainsEveryIdentityAndFinancialGuard(): void {
    $a = $this->synthetic();
    $service = new PrivatePurchaseService($this->authority($a));
    $request = ['id' => 901, 'public_id' => $a['public_id'], 'customer_id' => 701, 'organization_id' => 801];
    $offer = ['public_id' => 'synthetic-offer', 'website_request_id' => 901, 'customer_id' => 701, 'organization_id' => 801,
      'sku' => $a['sku'], 'offered_amount_minor' => 19900, 'currency' => 'usd', 'status' => 'active'];
    $event = ['authority' => $a['authority'], 'scope_hash' => $a['scope_hash'], 'request_id' => 901,
      'customer_id' => 701, 'organization_id' => 801, 'offer_public_id' => 'synthetic-offer', 'scope' => ['synthetic' => TRUE]];
    $service->assertScope($request, $offer, $event);
    self::assertNotSame($a['customer_id'], $a['organization_id']);
    try { PrivatePurchaseService::assertReunionScope($request, $offer, $event); self::fail('Production static wrapper accepted synthetic scope'); }
    catch (\RuntimeException $error) { self::assertSame('private_scope_changed', $error->getMessage()); }
    foreach ([['request', 'customer_id', 702], ['request', 'organization_id', 701], ['request', 'id', 902],
      ['offer', 'website_request_id', 902], ['offer', 'customer_id', 702], ['offer', 'organization_id', 701],
      ['offer', 'sku', 'OTHER'], ['offer', 'offered_amount_minor', 1], ['offer', 'currency', 'eur'],
      ['offer', 'status', 'revoked'], ['offer', 'expires_at', time() - 1],
      ['event', 'authority', 'Fritz Medine explicit approval'], ['event', 'request_id', 902],
      ['event', 'customer_id', 702], ['event', 'organization_id', 701], ['event', 'offer_public_id', 'other'],
      ['event', 'scope_hash', str_repeat('a', 64)], ['event', 'scope', ['synthetic' => FALSE]]] as [$target, $key, $value]) {
      $copies = compact('request', 'offer', 'event'); $copies[$target][$key] = $value;
      try { $service->assertScope(...array_values($copies)); self::fail('Altered scope accepted'); }
      catch (\RuntimeException $error) { self::assertSame('private_scope_changed', $error->getMessage()); }
    }
  }

  public function testProductionWiringHasNoRuntimeAuthoritySelector(): void {
    $root = dirname(__DIR__, 3);
    $services = \Symfony\Component\Yaml\Yaml::parseFile($root . '/famtastic_pipeline.services.yml')['services'];
    self::assertSame(ApprovedPrivatePurchaseAuthority::class, $services['famtastic_pipeline.private_purchase_authority']['class']);
    self::assertSame(['@famtastic_pipeline.private_purchase_authority'], $services['famtastic_pipeline.private_purchase']['arguments']);
    $source = file_get_contents($root . '/src/Service/ApprovedPrivatePurchaseAuthority.php');
    foreach (['getenv(', 'Settings::', '$_GET', '$_POST', '\Drupal::config'] as $forbidden) self::assertStringNotContainsString($forbidden, $source);
  }
}
