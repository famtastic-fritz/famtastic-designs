<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\famtastic_pipeline\Service\AiSolutionAdvisorService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\famtastic_pipeline\Service\AiSolutionAdvisorService
 * @group famtastic_pipeline
 */
class AiSolutionAdvisorServiceTest extends UnitTestCase {

  private AiSolutionAdvisorService $service;

  protected function setUp(): void {
    parent::setUp();

    $database = $this->createMock(Connection::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    $this->service = new AiSolutionAdvisorService($database, $loggerFactory, null);
  }

  /**
   * @covers ::advise
   */
  public function testDefaultRecommendation(): void {
    $result = $this->service->advise('');
    $this->assertSame('business-website', $result['package_sku']);
    $this->assertSame(499, $result['price_estimate']);
    $this->assertNotEmpty($result['recommended_pages']);
  }

  /**
   * @covers ::advise
   */
  public function testSinglePageRecommendation(): void {
    $result = $this->service->advise('I just need a simple 1 page starter site to show my contact info');
    $this->assertSame('web-basics', $result['package_sku']);
    $this->assertSame(199, $result['price_estimate']);
    $this->assertSame('$199', $result['price_formatted']);
  }

  /**
   * @covers ::advise
   */
  public function testGrowthSystemRecommendation(): void {
    $result = $this->service->advise('We need online booking and CRM lead automation for our plumbing business');
    $this->assertSame('business-growth', $result['package_sku']);
    $this->assertSame(3999, $result['price_estimate']);
  }

  /**
   * @covers ::advise
   */
  public function testPremiumAiSystemRecommendation(): void {
    $result = $this->service->advise('We want a client portal where members log in, with an integrated AI assistant');
    $this->assertSame('premium-ai', $result['package_sku']);
    $this->assertSame(6999, $result['price_estimate']);
  }

  /**
   * @covers ::advise
   */
  public function testCampaignLandingPageRecommendation(): void {
    $result = $this->service->advise('We are running Google Ads and need a high-converting landing page funnel');
    $this->assertSame('campaign-landing-page', $result['package_sku']);
    $this->assertSame(1499, $result['price_estimate']);
  }

  /**
   * @covers ::synthesizeBrief
   */
  public function testSynthesizeBrief(): void {
    $brief = $this->service->synthesizeBrief([
      'project_name' => 'Luigi Italian Kitchen',
      'business_model' => 'Italian restaurant offering dine-in, takeout, and private event catering',
      'required_features' => 'Menu display, table reservation request, catering inquiry form',
    ]);

    $this->assertArrayHasKey('executive_summary', $brief);
    $this->assertArrayHasKey('recommended_package', $brief);
    $this->assertArrayHasKey('target_audience', $brief);
    $this->assertArrayHasKey('site_architecture', $brief);
    $this->assertStringContainsString('diners', mb_strtolower($brief['target_audience']));
  }

  /**
   * @covers ::conversationalTurn
   */
  public function testConversationalTurn(): void {
    // Turn 1: user introduces business
    $turn1 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'Bella Cucina, an authentic Italian restaurant in Dallas'],
    ], []);

    $this->assertFalse($turn1['is_complete']);
    $this->assertNotEmpty($turn1['reply']);
    $this->assertNotEmpty($turn1['quick_chips']);
    $this->assertSame('Bella Cucina, an authentic Italian restaurant in Dallas', $turn1['gathered_data']['business_name']);

    // Turn 2: user provides pages and features
    $turn2 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'Bella Cucina, an authentic Italian restaurant in Dallas'],
      ['role' => 'assistant', 'content' => $turn1['reply']],
      ['role' => 'user', 'content' => 'Brand new site (3–5 pages / $499) with online booking and table reservations'],
    ], $turn1['gathered_data']);

    $this->assertSame('3-5', $turn2['gathered_data']['pages']);
    $this->assertContains('booking', $turn2['gathered_data']['features']);

    // Turn 3: user provides email to complete
    $turn3 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'Contact email is owner@bellacucina.com'],
    ], $turn2['gathered_data']);

    $this->assertTrue($turn3['is_complete']);
    $this->assertSame('owner@bellacucina.com', $turn3['gathered_data']['email']);
    $this->assertNotEmpty($turn3['recommendation']['package_title']);
  }

}

