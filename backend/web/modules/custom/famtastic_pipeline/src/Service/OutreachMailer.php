<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Outreach / transactional email boundary.
 */
class OutreachMailer {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Returns the exact configured envelope From address without its password.
   */
  public function fromAddress(): string {
    $smtp = $this->configFactory->get('smtp.settings');
    return trim((string) (
      $smtp->get('smtp_from')
      ?: $smtp->get('smtp_username')
      ?: $this->configFactory->get('famtastic_pipeline.settings')->get('support_from_email')
    ));
  }

  /**
   * Sends a transactional message through the configured cPanel SMTP account.
   *
   * The campaign boundary uses PHPMailer directly so a provider message id is
   * returned and an SMTP rejection cannot be mistaken for successful delivery.
   */
  public function send(string $to, string $subject, string $body): string {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('notification_recipient_invalid');
    }

    $transport = (string) (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') ?: Settings::get('famtastic_transactional_email_transport', 'smtp'));
    if ($transport === 'memory') {
      return $this->captureMemoryMessage($to, $subject, $body);
    }
    if ($transport !== 'smtp') {
      throw new RuntimeException('notification_transport_invalid');
    }

    $smtp = $this->configFactory->get('smtp.settings');
    $host = trim((string) $smtp->get('smtp_host'));
    $port = (int) $smtp->get('smtp_port');
    $username = trim((string) $smtp->get('smtp_username'));
    $password = (string) $smtp->get('smtp_password');
    $from = $this->fromAddress();
    $fromName = trim((string) ($smtp->get('smtp_fromname') ?: 'FAMtastic Designs'));

    if (!$smtp->get('smtp_on') || $host === '' || $port < 1 || $port > 65535) {
      throw new RuntimeException('notification_transport_not_configured');
    }
    if ($username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('notification_transport_credentials_invalid');
    }

    $mailer = new PHPMailer(TRUE);
    try {
      $mailer->isSMTP();
      $mailer->Host = $host;
      $mailer->Port = $port;
      $mailer->SMTPAuth = TRUE;
      $mailer->Username = $username;
      $mailer->Password = $password;
      $mailer->SMTPAutoTLS = (bool) $smtp->get('smtp_autotls');
      $mailer->Timeout = max(5, min(120, (int) ($smtp->get('smtp_timeout') ?: 30)));
      $mailer->CharSet = PHPMailer::CHARSET_UTF8;
      $mailer->SMTPSecure = match ((string) $smtp->get('smtp_protocol')) {
        'ssl' => PHPMailer::ENCRYPTION_SMTPS,
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        default => '',
      };
      $mailer->setFrom($from, $fromName);
      $replyTo = trim((string) $this->configFactory->get('famtastic_pipeline.settings')->get('support_from_email'));
      if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $mailer->addReplyTo($replyTo, 'FAMtastic Designs');
      }
      $mailer->addAddress($to);
      $mailer->Subject = $subject;
      $mailer->Body = $body;
      $mailer->isHTML(FALSE);
      $mailer->send();
      $providerMessageId = trim($mailer->getLastMessageID());
      if ($providerMessageId === '') {
        $providerMessageId = sprintf('<famtastic-%s@%s>', bin2hex(random_bytes(16)), substr(strrchr($from, '@') ?: '@famtasticdesigns.com', 1));
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('OUTREACH EMAIL failed from @from to @to: @subject', [
        '@from' => $from,
        '@to' => $to,
        '@subject' => $subject,
      ]);
      throw new RuntimeException('notification_delivery_failed', 0, $e);
    }

    $this->logger->info('OUTREACH EMAIL accepted by SMTP from @from to @to: @subject [@message_id]', [
      '@from' => $from,
      '@to' => $to,
      '@subject' => $subject,
      '@message_id' => $providerMessageId,
    ]);
    return $providerMessageId;
  }

  /**
   * Captures deterministic test messages without contacting an SMTP server.
   */
  private function captureMemoryMessage(string $to, string $subject, string $body): string {
    $path = trim((string) (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE') ?: Settings::get('famtastic_transactional_email_capture', '')));
    if ($path === '' || !is_dir(dirname($path)) || !is_writable(dirname($path))) {
      throw new RuntimeException('notification_capture_path_invalid');
    }
    $messageId = sprintf('<famtastic-test-%s@memory.invalid>', bin2hex(random_bytes(16)));
    $record = json_encode([
      'message_id' => $messageId,
      'to' => mb_strtolower($to),
      'subject' => $subject,
      'body' => $body,
      'captured_at' => gmdate(DATE_ATOM),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    if (file_put_contents($path, $record, FILE_APPEND | LOCK_EX) === FALSE) {
      throw new RuntimeException('notification_capture_failed');
    }
    $this->logger->info('TRANSACTIONAL TEST EMAIL captured for @to: @subject [@message_id]', [
      '@to' => $to,
      '@subject' => $subject,
      '@message_id' => $messageId,
    ]);
    return $messageId;
  }

}
