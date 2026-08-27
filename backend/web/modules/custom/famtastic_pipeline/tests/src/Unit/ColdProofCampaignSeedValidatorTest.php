<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\famtastic_pipeline\Service\ColdProofCampaignSeedValidator;
use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class ColdProofCampaignSeedValidatorTest extends UnitTestCase {

  private function seed(): array {
    return [
      'schema_version' => ColdProofCampaignSeedValidator::SCHEMA_VERSION,
      'cohort' => [
        'cohort_key' => 'test-cold-2026',
        'campaign_key' => 'test-cold-2026',
        'source_name' => 'Public directory',
      ],
      'leads' => [[
        'source_record_id' => 'record-1',
        'business_name' => 'Test Studio',
        'business_category' => 'Salon',
        'email' => 'test@example.test',
        'website_observation' => [
          'status' => 'confirmed_absent',
          'fact' => 'The public directory record dated 2026-08-26 has no website field.',
        ],
        'public_source' => [
          'url' => 'https://directory.example.test/test-studio',
          'provenance' => 'public business directory',
          'timeframe' => 'checked 2026-08-26',
        ],
        'corroborated_fact' => 'The public listing identifies Test Studio as a salon in Port Saint Lucie.',
        'proof_teaser' => 'A review-only proof can use only the facts corroborated by this public source.',
      ]],
    ];
  }

  public function testRequiresSourceFactTeaserAndExplicitWebsiteObservation(): void {
    $validated = (new ColdProofCampaignSeedValidator())->validate($this->seed());
    $this->assertSame('', $validated['cohort']['package_profile']);
    $this->assertSame('confirmed_absent', $validated['leads'][0]['website_observation']['status']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $validated['leads'][0]['evidence_hash']);
  }

  public function testUnknownWebsiteObservationCannotBecomeAColdProofClaim(): void {
    $seed = $this->seed();
    $seed['leads'][0]['website_observation']['status'] = 'unknown';
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('unknown cannot qualify');
    (new ColdProofCampaignSeedValidator())->validate($seed);
  }

  public function testVerifiedPresentProducesAnExploratoryEligibleSeedWithoutAWebsiteDiagnosis(): void {
    $seed = $this->seed();
    $seed['leads'][0]['website_observation'] = [
      'status' => 'verified_present',
      'fact' => 'The public business profile links to the current appointment site dated 2026-08-26.',
    ];
    $seed['leads'][0]['website_url'] = 'https://test-studio.example.test/book';

    $validated = (new ColdProofCampaignSeedValidator())->validate($seed);

    $this->assertSame('verified_present', $validated['leads'][0]['website_observation']['status']);
    $this->assertSame('https://test-studio.example.test/book', $validated['leads'][0]['website_url']);
  }

  public function testObservedOutdatedRequiresTheCorroboratedWebsiteUrl(): void {
    $seed = $this->seed();
    $seed['leads'][0]['website_observation'] = [
      'status' => 'observed_outdated',
      'fact' => 'The dated public site still shows the prior location.',
    ];

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('website_url is required');
    (new ColdProofCampaignSeedValidator())->validate($seed);
  }

  public function testDeploymentCanNarrowTheExplicitEligibilityVocabulary(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('cold.website_observation_statuses')->willReturn(['verified_present']);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('famtastic_pipeline.proof_cohorts')->willReturn($config);
    $seed = $this->seed();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('enabled explicit observation');
    (new ColdProofCampaignSeedValidator($factory))->validate($seed);
  }

  public function testSeedCannotSmuggleSendAuthority(): void {
    $seed = $this->seed();
    $seed['cohort']['approved'] = TRUE;
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot set delivery authority');
    (new ColdProofCampaignSeedValidator())->validate($seed);
  }

}
