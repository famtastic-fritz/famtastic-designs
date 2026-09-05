<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\famtastic_pipeline\Service\OutreachMailer;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/** @group famtastic_pipeline */
final class OutreachMailerOneClickUnsubscribeTest extends UnitTestCase {

  private string $capturePath;

  /** @var array<string, string|false> */
  private array $previousEnvironment = [];

  protected function setUp(): void {
    parent::setUp();
    $path = tempnam(sys_get_temp_dir(), 'famtastic-one-click-');
    if ($path === FALSE) {
      $this->fail('Could not allocate a temporary email capture path.');
    }
    $this->capturePath = $path;
    $this->setEnvironment('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT', 'memory');
    $this->setEnvironment('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE', $this->capturePath);
  }

  protected function tearDown(): void {
    foreach ($this->previousEnvironment as $name => $value) {
      $value === FALSE ? putenv($name) : putenv($name . '=' . $value);
    }
    if (isset($this->capturePath)) {
      @unlink($this->capturePath);
    }
    parent::tearDown();
  }

  public function testColdOneClickHeadersAreCapturedWithTheExactPublicWebUrl(): void {
    $key = str_repeat('a', 48);
    $url = 'https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/confirm/' . $key;
    $mailer = new OutreachMailer(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    $mailer->send('recipient@example.test', 'Proof invitation', 'Review your concepts.', $url);

    $line = trim((string) file_get_contents($this->capturePath));
    $record = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame([
      'List-Unsubscribe' => '<' . $url . '>',
      'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
    ], $record['headers'] ?? NULL);
  }

  public function testOneClickHeadersRejectAnythingOutsideThePublicDrupalRoute(): void {
    $mailer = new OutreachMailer(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerInterface::class),
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('notification_one_click_unsubscribe_invalid');
    $mailer->send(
      'recipient@example.test',
      'Proof invitation',
      'Review your concepts.',
      'https://famtasticdesigns.com/api/pipeline/email/unsubscribe/confirm/' . str_repeat('a', 48),
    );
  }

  public function testCustomerProofReadyTemplateIsBrandedAndKeepsEscapedPlainText(): void {
    $mailer = new OutreachMailer(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerInterface::class),
    );
    $reviewUrl = 'https://famtasticdesigns.com/portal/?section=projects&request=11111111-1111-1111-1111-111111111111';

    $mailer->send(
      'customer@example.test',
      'Your FAMtastic Studio Review is ready',
      "Hi Customer,\n\nYour three directions are ready. <script>never-run()</script>\n\nOpen your private Studio Review:\n{$reviewUrl}",
      NULL,
      OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY,
    );

    $line = trim((string) file_get_contents($this->capturePath));
    $record = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);
    $html = (string) ($record['html_body'] ?? '');
    $this->assertStringContainsString('FAMtastic Concierge', $html);
    $this->assertStringContainsString('Open your Studio Review', $html);
    $this->assertStringContainsString(str_replace('&', '&amp;', $reviewUrl), $html);
    $this->assertStringContainsString('&lt;script&gt;never-run()&lt;/script&gt;', $html);
    $this->assertStringNotContainsString('<script>never-run()</script>', $html);
    $this->assertSame('Your FAMtastic Studio Review is ready', $record['subject']);
    $this->assertStringContainsString('Your three directions are ready.', (string) $record['body']);
  }

  private function setEnvironment(string $name, string $value): void {
    if (!array_key_exists($name, $this->previousEnvironment)) {
      $this->previousEnvironment[$name] = getenv($name);
    }
    putenv($name . '=' . $value);
  }

}
