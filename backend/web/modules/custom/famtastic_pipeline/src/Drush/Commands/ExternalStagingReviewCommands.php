<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Staff-only import of exact external staging evidence; no other side effect.
 */
final class ExternalStagingReviewCommands extends DrushCommands {

  /**
   * Attaches one checksum-confirmed external release to an existing request.
   */
  #[CLI\Command(name: 'famtastic:external-staging-review-attach')]
  #[CLI\Argument(name: 'packetPath', description: 'Absolute private path to the external staging import JSON packet.')]
  #[CLI\Option(name: 'request', description: 'Exact existing website request public UUID.')]
  #[CLI\Option(name: 'customer', description: 'Expected customer id.')]
  #[CLI\Option(name: 'organization', description: 'Expected organization id.')]
  #[CLI\Option(name: 'actor', description: 'Actual agent/staff actor, never a fabricated customer approval.')]
  #[CLI\Option(name: 'authority', description: 'Owner instruction reference authorizing this exact attachment.')]
  #[CLI\Option(name: 'checksum', description: 'Exact SHA-256 of the import packet file.')]
  #[CLI\Option(name: 'staff-uid', description: 'Explicit active staff execution account; never a customer approval attribution.')]
  #[CLI\Usage(name: 'drush famtastic:external-staging-review-attach /private/external-stage.json --request=<uuid> --customer=13 --organization=13 --actor=codex:operator --authority="Owner-approved import" --checksum=<sha256> --staff-uid=1', description: 'Locks one already-built release to an exact account-owned full-site review without accepting, billing, mailing, deploying, or changing DNS.')]
  public function attach(
    string $packetPath,
    array $options = [
      'request' => '',
      'customer' => 0,
      'organization' => 0,
      'actor' => '',
      'authority' => '',
      'checksum' => '',
      'staff-uid' => 0,
    ],
  ): int {
    try {
      $realPacket = realpath($packetPath);
      $documentRoot = defined('DRUPAL_ROOT') ? realpath(DRUPAL_ROOT) : FALSE;
      if (!str_starts_with($packetPath, '/') || is_link($packetPath) || !$realPacket || !is_file($realPacket) || filesize($realPacket) > 250000
        || ($documentRoot && str_starts_with($realPacket, $documentRoot . DIRECTORY_SEPARATOR))) {
        throw new \InvalidArgumentException('Use an absolute bounded import packet outside the public document root.');
      }
      $bytes = file_get_contents($realPacket);
      if ($bytes === FALSE || !preg_match('/^[a-f0-9]{64}$/', (string) $options['checksum']) || !hash_equals((string) $options['checksum'], hash('sha256', $bytes))) {
        throw new \InvalidArgumentException('Import packet checksum confirmation is required.');
      }
      $staff = \Drupal::entityTypeManager()->getStorage('user')->load((int) $options['staff-uid']);
      if (!$staff || !$staff->isActive() || !$staff->hasPermission('administer famtastic pipeline')) {
        throw new \RuntimeException('Choose an active staff execution account.');
      }
      $switcher = \Drupal::service('account_switcher');
      $switcher->switchTo($staff);
      try {
        $packet = json_decode($bytes, TRUE, 512, JSON_THROW_ON_ERROR);
        if (!is_array($packet)) {
          throw new \InvalidArgumentException('External staging import packet must be a JSON object.');
        }
        $result = \Drupal::service('famtastic_pipeline.external_staging_review')->attach(
          (string) $options['request'],
          (int) $options['customer'],
          (int) $options['organization'],
          $packet,
          (string) $options['actor'],
          (string) $options['authority'],
        );
      }
      finally {
        $switcher->switchBack();
      }
      $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) {
      $this->logger()->error($error->getMessage());
      return self::EXIT_FAILURE;
    }
  }

}
