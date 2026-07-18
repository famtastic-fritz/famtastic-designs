<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Outreach / transactional email boundary.
 *
 * V1 default: log the intended message (no SMTP credentials are assumed). To
 * enable real delivery from support@famtasticdesigns.com, install a mail
 * transport (e.g. symfony_mailer) and set SMTP_* env vars — the call sites do
 * not change. This class is the single seam for that swap.
 */
class OutreachMailer {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Sends (or, in V1, logs) a transactional message.
   */
  public function send(string $to, string $subject, string $body): void {
    $from = (string) $this->configFactory->get('famtastic_pipeline.settings')->get('support_from_email');
    // V1: record intent. Swap this line for a real transport when configured.
    $this->logger->info('OUTREACH EMAIL (logged, not sent) from @from to @to: @subject', [
      '@from' => $from,
      '@to' => $to,
      '@subject' => $subject,
    ]);
  }

}
