<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Outreach / transactional email boundary.
 */
class OutreachMailer {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $languageManager,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Sends a transactional message through Drupal's active mail transport.
   */
  public function send(string $to, string $subject, string $body): void {
    $from = (string) $this->configFactory->get('famtastic_pipeline.settings')->get('support_from_email');
    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $result = $this->mailManager->mail('famtastic_pipeline', 'outreach', $to, $langcode, [
      'subject' => $subject,
      'body' => $body,
      'from' => $from,
    ], $from, TRUE);

    if (($result['result'] ?? FALSE) !== TRUE) {
      $this->logger->error('OUTREACH EMAIL failed from @from to @to: @subject', [
        '@from' => $from,
        '@to' => $to,
        '@subject' => $subject,
      ]);
      throw new RuntimeException('notification_delivery_failed');
    }

    $this->logger->info('OUTREACH EMAIL sent from @from to @to: @subject', [
      '@from' => $from,
      '@to' => $to,
      '@subject' => $subject,
    ]);
  }

}
