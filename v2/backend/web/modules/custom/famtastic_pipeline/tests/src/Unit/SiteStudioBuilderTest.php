<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\famtastic_pipeline\Service\SiteStudioRequestBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the pure brief-rendering half of the Site Studio builder.
 *
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\SiteStudioRequestBuilder
 * @group famtastic_pipeline
 */
class SiteStudioBuilderTest extends UnitTestCase {

  protected function builder(): SiteStudioRequestBuilder {
    return new SiteStudioRequestBuilder($this->createMock(ConfigFactoryInterface::class));
  }

  protected function sampleJson(): array {
    return [
      'schema_version' => '1.0',
      'package' => 'basic_199',
      'business' => [
        'name' => 'Joe Plumbing',
        'category' => 'Plumber',
        'description' => 'Since 1998',
        'public_contact' => ['phone' => '555', 'email' => 'j@x.test', 'website' => NULL],
        'hours' => NULL,
        'service_area' => 'Phoenix',
      ],
      'positioning' => ['primary_cta' => 'Call now', 'ideal_customer' => 'Homeowners'],
      'content' => [
        'services' => ['Drain cleaning', 'Water heaters'],
        'about' => 'Family owned',
        'differentiators' => ['24/7'],
        'credentials' => [],
        'testimonials' => [],
        'faqs' => [],
        'required_sections' => ['Home', 'Contact'],
        'avoid' => 'No price claims',
      ],
      'brand' => ['colors' => 'Blue', 'reference_sites' => []],
      'domain' => ['existing_domain' => 'joe.test'],
      'assets' => [['filename' => 'logo.png', 'owner_confirmed' => TRUE]],
    ];
  }

  /** @covers ::buildBrief */
  public function testBriefContainsKeyContent(): void {
    $brief = $this->builder()->buildBrief($this->sampleJson());
    $this->assertStringContainsString('Joe Plumbing', $brief);
    $this->assertStringContainsString('Call now', $brief);
    $this->assertStringContainsString('- Drain cleaning', $brief);
    $this->assertStringContainsString('Do NOT include', $brief);
    $this->assertStringContainsString('No price claims', $brief);
    $this->assertStringContainsString('logo.png (ownership confirmed)', $brief);
  }

  /** @covers ::buildBrief */
  public function testBriefHandlesMissingFieldsGracefully(): void {
    $brief = $this->builder()->buildBrief(['business' => ['name' => 'X']]);
    $this->assertStringContainsString('Site Studio Build Request — X', $brief);
    $this->assertStringContainsString('_none provided_', $brief);
  }

}
