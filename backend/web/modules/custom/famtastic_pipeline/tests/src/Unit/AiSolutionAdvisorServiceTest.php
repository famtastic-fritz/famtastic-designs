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
    $this->assertSame('web-basics', $result['package_sku']);
    $this->assertSame(199, $result['price_estimate']);
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
  public function testScoutConversationalFlow(): void {
    // Step 1 -> Step 2: User provides business and city
    $turn1 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'Barbershop, Port St. Lucie'],
    ], []);

    $this->assertSame(2, $turn1['step_number']);
    $this->assertNotEmpty($turn1['market_scan']);
    $this->assertStringContainsString('Port St. Lucie', $turn1['reply']);
    $this->assertStringContainsString('$199', $turn1['reply']);
    $this->assertNotEmpty($turn1['quick_chips']);

    // Step 2 -> Step 3: User picks $199 package
    $turn2 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'Barbershop, Port St. Lucie'],
      ['role' => 'assistant', 'content' => $turn1['reply']],
      ['role' => 'user', 'content' => 'Yes, build my $199 scope'],
    ], $turn1['gathered_data']);

    $this->assertSame(3, $turn2['step_number']);
    $this->assertStringContainsString('logo and domain', mb_strtolower($turn2['reply']));

    // Step 3 -> Step 4: User confirms scope, sees on-screen artifact before email
    $turn3 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'I have logo & domain ready'],
    ], $turn2['gathered_data']);

    $this->assertSame(4, $turn3['step_number']);
    $this->assertTrue($turn3['is_artifact_ready']);
    $this->assertSame('web-basics', $turn3['recommendation']['package_sku']);
    $this->assertSame('$199', $turn3['recommendation']['price_formatted']);

    // Step 4 Completion: User enters email
    $turn4 = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'owner@pslbarbers.com'],
    ], $turn3['gathered_data']);

    $this->assertTrue($turn4['is_complete']);
    $this->assertSame('owner@pslbarbers.com', $turn4['gathered_data']['email']);
  }

  /**
   * @covers ::conversationalTurn
   */
  public function testHumanEscalationLeadScoring(): void {
    $turn = $this->service->conversationalTurn([
      ['role' => 'user', 'content' => 'I want to talk to a real human'],
    ], []);

    $this->assertTrue($turn['gathered_data']['wants_human']);
    $this->assertTrue($turn['gathered_data']['lead_score_hot']);
    $this->assertStringContainsString('personal follow-up', $turn['reply']);
  }

}


