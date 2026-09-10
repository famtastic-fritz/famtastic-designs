<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\EventSubscriber\ProtectedStagingRequestSubscriber;
use Drupal\famtastic_pipeline\Service\OutreachMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Prevents protected staging from silently regaining real side effects.
 */
final class ProtectedStagingSafetyContractTest extends TestCase {

  public function testPaymentManagerHasExplicitDisabledGateway(): void {
    $root = dirname(__DIR__, 3);
    $manager = file_get_contents($root . '/src/Service/PaymentGatewayManager.php');
    $gateway = file_get_contents($root . '/src/Service/DisabledPaymentGateway.php');
    $services = file_get_contents($root . '/famtastic_pipeline.services.yml');

    self::assertStringContainsString("Settings::get('famtastic_payment_mode') === 'disabled'", $manager);
    self::assertStringContainsString("return 'disabled';", $gateway);
    self::assertStringContainsString('Payment is disabled in this environment.', $gateway);
    self::assertStringContainsString('@famtastic_pipeline.disabled_gateway', $services);
  }

  public function testEveryCheckoutEntryPointFailsBeforeMutation(): void {
    $root = dirname(__DIR__, 3);
    $customer = file_get_contents($root . '/src/Controller/CustomerPortalController.php');
    $pipeline = file_get_contents($root . '/src/Controller/PipelineController.php');

    self::assertMatchesRegularExpression('/function commerceCheckout\([^}]+famtastic_payment_mode/s', $customer);
    self::assertGreaterThanOrEqual(2, substr_count($pipeline, "Settings::get('famtastic_payment_mode') === 'disabled'"));
    self::assertStringContainsString("'payment_disabled', 503", $customer);
    self::assertStringContainsString("'payment_disabled', 503", $pipeline);

    $simulation = file_get_contents($root . '/src/Controller/SimulateController.php');
    $webhook = file_get_contents($root . '/src/Controller/StripeWebhookController.php');
    $subscriber = file_get_contents($root . '/src/EventSubscriber/ProtectedStagingRequestSubscriber.php');
    self::assertStringContainsString("Settings::get('famtastic_payment_mode') === 'disabled'", $simulation);
    self::assertStringContainsString("Settings::get('famtastic_payment_mode') === 'disabled'", $webhook);
    self::assertStringContainsString("'commerce_checkout.checkout'", $subscriber);
    self::assertStringContainsString("'commerce_checkout.form'", $subscriber);
    self::assertStringContainsString("['blockNativeCheckoutPath', 33]", $subscriber);
    self::assertStringContainsString("['blockPaymentRoutes', 31]", $subscriber);
  }

  public function testProtectedStagingGloballyBlackholesDrupalMail(): void {
    $root = dirname(__DIR__, 3);
    $module = file_get_contents($root . '/famtastic_pipeline.module');

    self::assertStringContainsString('function famtastic_pipeline_mail_alter', $module);
    self::assertStringContainsString("Settings::get('famtastic_protected_staging', FALSE)", $module);
    self::assertStringContainsString("\$message['send'] = FALSE", $module);
    self::assertStringContainsString('famtastic_staging_mail_capture', $module);
    self::assertStringContainsString("'body_sha256' => hash('sha256'", $module);
    self::assertStringNotContainsString("'body' => implode", $module);

    $mailer = file_get_contents($root . '/src/Service/OutreachMailer.php');
    self::assertStringContainsString("Settings::get('famtastic_protected_staging', FALSE)", $mailer);
    self::assertStringContainsString('captureProtectedStagingMessage', $mailer);
    self::assertStringContainsString("'transport' => 'blackhole'", $mailer);
    self::assertStringContainsString("'to_sha256' => hash('sha256'", $mailer);
    self::assertStringContainsString('Protected staging is active; hook_cron skipped', $module);
    $plugin = file_get_contents($root . '/src/Plugin/Mail/FamtasticBlackholeMail.php');
    self::assertStringContainsString("id: 'famtastic_blackhole'", $plugin);
    self::assertStringContainsString('public function mail(array $message): bool', $plugin);
    self::assertStringContainsString('return TRUE;', $plugin);
  }

  public function testProtectedStagingBlocksNativeCommerceBeforeControllerExecution(): void {
    new Settings(['famtastic_payment_mode' => 'disabled']);
    try {
      $request = Request::create('/checkout/12/order_information');
      $request->attributes->set('_route', 'commerce_checkout.form');
      $event = new RequestEvent(
        $this->createMock(HttpKernelInterface::class),
        $request,
        HttpKernelInterface::MAIN_REQUEST,
      );
      (new ProtectedStagingRequestSubscriber())->blockNativeCheckoutPath($event);

      self::assertTrue($event->hasResponse());
      self::assertSame(503, $event->getResponse()->getStatusCode());
      self::assertStringContainsString('payment_disabled', (string) $event->getResponse()->getContent());
    }
    finally {
      new Settings([]);
    }
  }

  public function testProtectedStagingDoesNotBlockNonCheckoutPathsBeforeRouting(): void {
    new Settings(['famtastic_payment_mode' => 'disabled']);
    try {
      $request = Request::create('/api/customer/session');
      $event = new RequestEvent(
        $this->createMock(HttpKernelInterface::class),
        $request,
        HttpKernelInterface::MAIN_REQUEST,
      );
      (new ProtectedStagingRequestSubscriber())->blockNativeCheckoutPath($event);
      self::assertFalse($event->hasResponse());
    }
    finally {
      new Settings([]);
    }
  }

  public function testProtectedStagingOutreachUsesDigestOnlyBlackhole(): void {
    $directory = sys_get_temp_dir() . '/famtastic-protected-staging-' . bin2hex(random_bytes(8));
    self::assertTrue(mkdir($directory, 0700));
    $capture = $directory . '/mail.jsonl';
    new Settings([
      'famtastic_protected_staging' => TRUE,
      'famtastic_staging_mail_capture' => $capture,
    ]);
    try {
      $mailer = new OutreachMailer(
        $this->createMock(ConfigFactoryInterface::class),
        $this->createMock(LoggerInterface::class),
      );
      $messageId = $mailer->send(
        'reviewer@example.test',
        'Private staging subject',
        'Private staging body',
      );

      self::assertStringContainsString('@blackhole.invalid>', $messageId);
      $record = (string) file_get_contents($capture);
      self::assertStringContainsString('famtastic.protected-staging-mail.v1', $record);
      self::assertStringContainsString('"transport":"blackhole"', $record);
      self::assertStringNotContainsString('reviewer@example.test', $record);
      self::assertStringNotContainsString('Private staging subject', $record);
      self::assertStringNotContainsString('Private staging body', $record);
    }
    finally {
      new Settings([]);
      if (is_file($capture)) {
        unlink($capture);
      }
      rmdir($directory);
    }
  }

}
