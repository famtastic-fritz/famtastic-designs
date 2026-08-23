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
      // Keep the operational record and the plain-text alternative readable,
      // while giving every customer and owner notification a consistent,
      // mobile-safe presentation.  Callers deliberately provide plain text so
      // request data can never become executable markup in an email.
      $mailer->isHTML(TRUE);
      $mailer->Body = $this->renderHtmlMessage($subject, $body);
      $mailer->AltBody = $body;
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
   * Turns a trusted plain-text notification into a small transactional email.
   *
   * This is intentionally a presentation boundary rather than a new template
   * system: the outbox and memory transport retain the exact readable text,
   * and only http(s) links become anchors after escaping.
   */
  private function renderHtmlMessage(string $subject, string $body): string {
    $paragraphs = preg_split('/\R{2,}/', trim($body)) ?: [];
    $content = '';
    foreach ($paragraphs as $paragraph) {
      $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $escaped = preg_replace_callback(
        '#(https?://[^\s&lt;]+)#i',
        static function (array $match): string {
          $url = $match[1];
          return '<a href="' . $url . '" style="color:#0f6b47;font-weight:700;word-break:break-word">' . $url . '</a>';
        },
        $escaped,
      ) ?? $escaped;
      $content .= '<p style="margin:0 0 16px;color:#243126;font:16px/1.55 Arial,Helvetica,sans-serif">'
        . nl2br($escaped, FALSE)
        . '</p>';
    }
    if ($content === '') {
      $content = '<p style="margin:0;color:#243126;font:16px/1.55 Arial,Helvetica,sans-serif">A FAMtastic Designs notification is ready for review.</p>';
    }

    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<!doctype html><html lang="en"><body style="margin:0;padding:0;background:#edf1eb">'
      . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf1eb"><tr><td style="padding:28px 14px">'
      . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d8e0d8;border-radius:14px;overflow:hidden">'
      . '<tr><td style="padding:22px 28px;background:#102a1c;color:#ffffff;font:800 15px/1 Arial,Helvetica,sans-serif;letter-spacing:.08em;text-transform:uppercase">FAMtastic Designs</td></tr>'
      . '<tr><td style="padding:28px"><h1 style="margin:0 0 20px;color:#102a1c;font:800 27px/1.15 Arial,Helvetica,sans-serif">' . $safeSubject . '</h1>'
      . $content
      . '<p style="margin:24px 0 0;padding-top:16px;border-top:1px solid #d8e0d8;color:#66736a;font:13px/1.45 Arial,Helvetica,sans-serif">This is an operational message from FAMtastic Designs. You can reply to this email if you need to add context.</p>'
      . '</td></tr></table></td></tr></table></body></html>';
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
