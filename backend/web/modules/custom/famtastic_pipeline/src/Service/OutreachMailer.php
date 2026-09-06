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

  public const TEMPLATE_STANDARD = 'standard';
  public const TEMPLATE_STANDARD_VERSION = 1;
  public const TEMPLATE_CUSTOMER_INTAKE_SUBMITTED = 'customer_intake_submitted';
  public const TEMPLATE_CUSTOMER_INTAKE_SUBMITTED_VERSION = 1;
  public const TEMPLATE_CUSTOMER_PROOF_READY = 'customer_proof_ready';
  public const TEMPLATE_CUSTOMER_PROOF_READY_VERSION = 2;
  public const TEMPLATE_CUSTOMER_PROOF_READY_LEGACY_VERSION = 1;
  public const TEMPLATE_CUSTOMER_REVISION_RECEIVED = 'customer_revision_received';
  public const TEMPLATE_CUSTOMER_REVISION_RECEIVED_VERSION = 1;

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
  public function send(string $to, string $subject, string $body, ?string $oneClickUnsubscribeUrl = NULL, string $template = self::TEMPLATE_STANDARD, int $templateVersion = 0): string {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
      throw new RuntimeException('notification_recipient_invalid');
    }
    $templateVersion = $templateVersion ?: self::currentTemplateVersion($template);
    if (!self::supportsTemplate($template, $templateVersion)) {
      throw new RuntimeException('notification_template_invalid');
    }
    $oneClickHeaders = $this->oneClickUnsubscribeHeaders($oneClickUnsubscribeUrl);
    $htmlBody = $this->renderHtmlMessage($subject, $body, $template);

    $transport = (string) (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') ?: Settings::get('famtastic_transactional_email_transport', 'smtp'));
    if ($transport === 'memory') {
      return $this->captureMemoryMessage($to, $subject, $body, $oneClickHeaders, $htmlBody, $template, $templateVersion);
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
      foreach ($oneClickHeaders as $name => $value) {
        $mailer->addCustomHeader($name, $value);
      }
      $mailer->addCustomHeader('X-FAMtastic-Template', $template . '/v' . $templateVersion);
      // Keep the operational record and the plain-text alternative readable,
      // while giving every customer and owner notification a consistent,
      // mobile-safe presentation.  Callers deliberately provide plain text so
      // request data can never become executable markup in an email.
      $mailer->isHTML(TRUE);
      $mailer->Body = $htmlBody;
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

  /** Returns whether a versioned transactional template is available. */
  public static function supportsTemplate(string $template, int $version): bool {
    return ($template === self::TEMPLATE_STANDARD && $version === self::TEMPLATE_STANDARD_VERSION)
      || ($template === self::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED && $version === self::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED_VERSION)
      || ($template === self::TEMPLATE_CUSTOMER_PROOF_READY && in_array($version, [self::TEMPLATE_CUSTOMER_PROOF_READY_LEGACY_VERSION, self::TEMPLATE_CUSTOMER_PROOF_READY_VERSION], TRUE))
      || ($template === self::TEMPLATE_CUSTOMER_REVISION_RECEIVED && $version === self::TEMPLATE_CUSTOMER_REVISION_RECEIVED_VERSION);
  }

  /** Selects the current template version for direct, versionless callers. */
  private static function currentTemplateVersion(string $template): int {
    return match ($template) {
      self::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED => self::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED_VERSION,
      self::TEMPLATE_CUSTOMER_PROOF_READY => self::TEMPLATE_CUSTOMER_PROOF_READY_VERSION,
      self::TEMPLATE_CUSTOMER_REVISION_RECEIVED => self::TEMPLATE_CUSTOMER_REVISION_RECEIVED_VERSION,
      default => self::TEMPLATE_STANDARD_VERSION,
    };
  }

  /**
   * Builds the RFC 8058 headers for a verified-cold commercial invitation.
   *
   * Callers cannot inject arbitrary headers here: the only accepted URL is
   * the opaque POST-confirmation endpoint exposed under the public Drupal
   * document root.  Ordinary notifications leave this empty.
   *
   * @return array<string, string>
   */
  private function oneClickUnsubscribeHeaders(?string $url): array {
    $url = trim((string) $url);
    if ($url === '') {
      return [];
    }
    if (str_contains($url, "\r") || str_contains($url, "\n") || !filter_var($url, FILTER_VALIDATE_URL)) {
      throw new RuntimeException('notification_one_click_unsubscribe_invalid');
    }
    $parts = parse_url($url);
    if (
      !is_array($parts)
      || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
      || trim((string) ($parts['host'] ?? '')) === ''
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
      || preg_match('#^/web/api/pipeline/email/unsubscribe/confirm/[a-f0-9]{48}$#', (string) ($parts['path'] ?? '')) !== 1
    ) {
      throw new RuntimeException('notification_one_click_unsubscribe_invalid');
    }
    return [
      'List-Unsubscribe' => '<' . $url . '>',
      'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
    ];
  }

  /**
   * Turns a trusted plain-text notification into a small transactional email.
   *
   * This is intentionally a presentation boundary rather than a new template
   * system: the outbox and memory transport retain the exact readable text,
   * and only http(s) links become anchors after escaping.
   */
  private function renderHtmlMessage(string $subject, string $body, string $template = self::TEMPLATE_STANDARD): string {
    if ($template === self::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED) {
      return $this->renderCustomerIntakeSubmittedMessage($subject, $body);
    }
    if ($template === self::TEMPLATE_CUSTOMER_PROOF_READY) {
      return $this->renderCustomerProofReadyMessage($subject, $body);
    }
    if ($template === self::TEMPLATE_CUSTOMER_REVISION_RECEIVED) {
      return $this->renderCustomerRevisionReceivedMessage($subject, $body);
    }
    $paragraphs = preg_split('/\R{2,}/', trim($body)) ?: [];
    $content = '';
    foreach ($paragraphs as $paragraph) {
      $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      // Escape first, then autolink. The character class must only exclude
      // whitespace: the previous pattern excluded the literal entity string
      // "&lt;" - i.e. the letters s,l,t,g,;,& - so every URL truncated at the
      // first "s" and no customer link ever opened (SITE_LEARNINGS 2026-08-24).
      $escaped = preg_replace_callback(
        '#(https?://[^\s]+)#i',
        static function (array $match): string {
          $url = $match[1];
          // Leave trailing sentence punctuation out of the href.
          $trailing = '';
          if (preg_match('#[),.;:!?"\']+$#', $url, $m)) {
            $trailing = $m[0];
            $url = substr($url, 0, strlen($url) - strlen($trailing));
          }
          return '<a href="' . $url . '" style="color:#0f6b47;font-weight:700;word-break:break-word">' . $url . '</a>' . $trailing;
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
   * Renders the account-owned proof delivery without borrowing commercial
   * campaign language or external proof/share links.
   *
   * The source body remains plain text and is stored verbatim in the durable
   * outbox. That keeps a human-readable receipt and prevents customer input
   * from becoming executable markup; this method only adds the trusted visual
   * treatment around it.
   */
  private function renderCustomerProofReadyMessage(string $subject, string $body): string {
    return $this->renderCustomerConciergeMessage(
      $subject,
      $body,
      'Your Studio Review is ready',
      'Private concept review · verified workspace',
      'Review your 3 directions →',
      'Your concepts stay inside your verified FAMtastic account until you choose to share feedback. FAMtastic Concierge is here when you are ready to talk through a direction.',
    );
  }

  /** Renders a human, branded receipt for feedback—not a generic worker note. */
  private function renderCustomerRevisionReceivedMessage(string $subject, string $body): string {
    return $this->renderCustomerConciergeMessage(
      $subject,
      $body,
      'We heard you',
      'Feedback saved · next proof round',
      'Open your project →',
      'Your feedback is saved with this project. FAMtastic will only send a new review when the replacement directions are complete and approved.',
    );
  }

  /** Renders the account-owned receipt after a customer starts proof work. */
  private function renderCustomerIntakeSubmittedMessage(string $subject, string $body): string {
    return $this->renderCustomerConciergeMessage(
      $subject,
      $body,
      'Your design review has started',
      'Intake received · verified workspace',
      'Open your workspace →',
      'Next, FAMtastic reviews the business context you shared and prepares your proof routine. Your concepts are released only after FAMtastic owner review.',
    );
  }

  /** Shared visual system for account-owned Concierge transactional notices. */
  private function renderCustomerConciergeMessage(string $subject, string $body, string $headline, string $badge, string $ctaLabel, string $assurance): string {
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeHeadline = htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeBadge = htmlspecialchars($badge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCtaLabel = htmlspecialchars($ctaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeAssurance = htmlspecialchars($assurance, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    [$body, $reviewUrl] = $this->extractConciergeCta($body);
    $paragraphs = preg_split('/\R{2,}/', trim($body)) ?: [];
    $content = '';
    foreach ($paragraphs as $paragraph) {
      $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $content .= '<p style="margin:0 0 15px;color:#26372c;font:16px/1.6 Arial,Helvetica,sans-serif">'
        . nl2br($escaped, FALSE)
        . '</p>';
    }
    if ($content === '') {
      $content = '<p style="margin:0;color:#26372c;font:16px/1.6 Arial,Helvetica,sans-serif">Your private Studio Review is ready in your FAMtastic workspace.</p>';
    }

    $cta = '';
    if ($reviewUrl !== '') {
      $safeReviewUrl = htmlspecialchars($reviewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $cta = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:22px 0 26px"><tr><td style="border-radius:8px;background:#7cfc00">'
        . '<a href="' . $safeReviewUrl . '" style="display:inline-block;padding:14px 20px;border-radius:8px;color:#102a1c;font:800 15px/1 Arial,Helvetica,sans-serif;text-decoration:none">' . $safeCtaLabel . '</a>'
        . '</td></tr></table>';
    }

    return '<!doctype html><html lang="en"><body style="margin:0;padding:0;background:#070907">'
      . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#070907"><tr><td style="padding:28px 14px">'
      . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#f8fbf7;border:1px solid #263529;border-radius:18px;overflow:hidden">'
      . '<tr><td style="padding:24px 28px;background:#102a1c;color:#ffffff">'
      . '<div style="color:#7cfc00;font:800 12px/1 Arial,Helvetica,sans-serif;letter-spacing:.13em;text-transform:uppercase">FAMtastic Concierge</div>'
      . '<div style="margin-top:10px;font:800 23px/1.15 Arial,Helvetica,sans-serif">' . $safeHeadline . '</div>'
      . '</td></tr>'
      . '<tr><td style="padding:28px">'
      . '<div style="display:inline-block;margin:0 0 18px;padding:7px 10px;border:1px solid #b5d7b2;border-radius:999px;color:#114b31;background:#edf8ea;font:800 12px/1 Arial,Helvetica,sans-serif;letter-spacing:.06em;text-transform:uppercase">' . $safeBadge . '</div>'
      . '<h1 style="margin:0 0 18px;color:#102a1c;font:800 28px/1.15 Arial,Helvetica,sans-serif">' . $safeSubject . '</h1>'
      . $content . $cta
      . '<div style="margin-top:8px;padding:16px;border:1px solid #d8e8d6;border-radius:12px;background:#ffffff;color:#526356;font:14px/1.5 Arial,Helvetica,sans-serif">' . $safeAssurance . '</div>'
      . '<p style="margin:24px 0 0;padding-top:16px;border-top:1px solid #d8e0d8;color:#66736a;font:13px/1.45 Arial,Helvetica,sans-serif">FAMtastic Designs · 1729 NW St. Lucie West Blvd #1181 · Port Saint Lucie, FL 34986</p>'
      . '</td></tr></table></td></tr></table></body></html>';
  }

  /**
   * Pulls the one trusted workspace URL out of the visible Concierge body.
   *
   * The durable plain-text outbox still contains the exact fallback. The HTML
   * presentation deliberately renders it only as a named button, so a long
   * authenticated portal URL never becomes the visual call to action.
   *
   * @return array{0:string,1:string}
   */
  private function extractConciergeCta(string $body): array {
    $url = '';
    $clean = preg_replace_callback('#https?://[^\s]+#i', static function (array $match) use (&$url): string {
      $candidate = rtrim($match[0], '),.;:!?\"\'');
      if ($url === '' && filter_var($candidate, FILTER_VALIDATE_URL)) {
        $url = $candidate;
      }
      return '';
    }, $body) ?? $body;
    // Remove the label that introduced the now-button-only URL, without
    // altering a customer-written sentence elsewhere in the receipt.
    $clean = preg_replace('/^\s*(Open (your )?(private )?(Studio Review|workspace|project)|Review (your )?(private )?(Studio Review|project))\s*:?\s*$/mi', '', $clean) ?? $clean;
    $clean = preg_replace('/\n{3,}/', "\n\n", trim($clean)) ?? trim($clean);
    return [$clean, $url];
  }

  /**
   * Captures deterministic test messages without contacting an SMTP server.
   */
  private function captureMemoryMessage(string $to, string $subject, string $body, array $headers = [], ?string $htmlBody = NULL, string $template = self::TEMPLATE_STANDARD, int $templateVersion = self::TEMPLATE_STANDARD_VERSION): string {
    $path = trim((string) (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE') ?: Settings::get('famtastic_transactional_email_capture', '')));
    if ($path === '' || !is_dir(dirname($path)) || !is_writable(dirname($path))) {
      throw new RuntimeException('notification_capture_path_invalid');
    }
    $messageId = sprintf('<famtastic-test-%s@memory.invalid>', bin2hex(random_bytes(16)));
    $recordData = [
      'message_id' => $messageId,
      'to' => mb_strtolower($to),
      'subject' => $subject,
      'body' => $body,
      'template_id' => $template,
      'template_version' => $templateVersion,
      'captured_at' => gmdate(DATE_ATOM),
    ];
    if ($htmlBody !== NULL) {
      $recordData['html_body'] = $htmlBody;
    }
    if ($headers !== []) {
      $recordData['headers'] = $headers;
    }
    $record = json_encode($recordData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
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
