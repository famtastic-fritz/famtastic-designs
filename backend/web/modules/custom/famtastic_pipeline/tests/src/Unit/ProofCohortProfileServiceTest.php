<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\famtastic_pipeline\Service\ProofCohortProfileService;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class ProofCohortProfileServiceTest extends UnitTestCase {

  private function service(array $profiles, string $default = 'three'): ProofCohortProfileService {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['anonymous.default_profile', $default],
      ['anonymous.profiles', $profiles],
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('famtastic_pipeline.proof_cohorts')->willReturn($config);
    return new ProofCohortProfileService($factory);
  }

  public function testDefaultThreeDirectionProfileIsResolved(): void {
    $service = $this->service(['three' => [
      'audience' => 'anonymous_public', 'direction_count' => 3,
      'directions' => [
        'a' => ['name' => 'Safe', 'intent' => 'Credible and clear.'],
        'b' => ['name' => 'Medium', 'intent' => 'Expressive but usable.'],
        'c' => ['name' => 'Ultra', 'intent' => 'Most memorable viable route.'],
      ],
    ]]);
    $profile = $service->resolveAnonymous();
    $this->assertSame('three', $profile['id']);
    $this->assertSame(3, $profile['direction_count']);
    $this->assertSame(['a', 'b', 'c'], array_keys($profile['directions']));
  }

  public function testConfiguredFiveDirectionProfileIsNotSilentlyReducedToThree(): void {
    $directions = [];
    foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
      $directions[$id] = ['name' => strtoupper($id), 'intent' => 'Intent for direction ' . $id . '.'];
    }
    $profile = $this->service(['five' => [
      'audience' => 'anonymous_public', 'direction_count' => 5, 'directions' => $directions,
    ]], 'five')->resolveAnonymous();
    $this->assertSame(5, $profile['direction_count']);
    $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_keys($profile['directions']));
  }

}
