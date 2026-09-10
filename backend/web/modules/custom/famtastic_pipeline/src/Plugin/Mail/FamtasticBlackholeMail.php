<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Accepts Drupal Mail API messages without opening an external transport.
 */
#[Mail(
  id: 'famtastic_blackhole',
  label: new TranslatableMarkup('FAMtastic protected-staging blackhole'),
  description: new TranslatableMarkup('Captures message digests through the pipeline mail alter hook and never sends.'),
)]
final class FamtasticBlackholeMail implements MailInterface {

  public function format(array $message): array {
    $message['body'] = implode("\n\n", array_map('strval', (array) ($message['body'] ?? [])));
    return $message;
  }

  public function mail(array $message): bool {
    return TRUE;
  }

}
