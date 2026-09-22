<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/** Exact staff-only private-package attachment; never a queue runner. */
final class FullSiteReviewCommands extends DrushCommands {
  #[CLI\Command(name: 'famtastic:full-site-review-attach')]
  #[CLI\Argument(name: 'manifestPath', description: 'Absolute manifest JSON path outside the public document root.')]
  #[CLI\Argument(name: 'packageDirectory', description: 'Absolute directory containing the static review files.')]
  #[CLI\Option(name: 'request', description: 'Exact website request public UUID.')]
  #[CLI\Option(name: 'customer', description: 'Expected customer id.')]
  #[CLI\Option(name: 'organization', description: 'Expected organization id.')]
  #[CLI\Option(name: 'actor', description: 'Actual agent/staff actor, not a fabricated customer approval.')]
  #[CLI\Option(name: 'authority', description: 'Owner instruction reference authorizing this exact attachment.')]
  #[CLI\Option(name: 'checksum', description: 'Exact SHA-256 of the manifest JSON file.')]
  #[CLI\Option(name: 'staff-uid', description: 'Explicit active staff execution account; never a customer approval attribution.')]
  #[CLI\Option(name: 'new-request-key', description: 'Unique owner-authorized staff draft key; mutually exclusive with request.')]
  #[CLI\Option(name: 'project-name', description: 'Exact name for a new staff-authored draft only.')]
  public function attach(string $manifestPath, string $packageDirectory, array $options = ['request' => '', 'customer' => 0, 'organization' => 0, 'actor' => '', 'authority' => '', 'checksum' => '', 'staff-uid' => 0, 'new-request-key' => '', 'project-name' => '']): int {
    try {
      if (!str_starts_with($manifestPath, '/') || is_link($manifestPath) || !is_file($manifestPath) || filesize($manifestPath) > 1000000 || !str_starts_with($packageDirectory, '/')) throw new \InvalidArgumentException('Use an absolute bounded manifest and static package directory.');
      $bytes = (string) file_get_contents($manifestPath);
      if (!preg_match('/^[a-f0-9]{64}$/', (string) $options['checksum']) || !hash_equals((string) $options['checksum'], hash('sha256', $bytes))) throw new \InvalidArgumentException('Manifest checksum confirmation is required.');
      $create = trim((string) $options['new-request-key']) !== '';
      if ($create === (trim((string) $options['request']) !== '') || (!$create && trim((string) $options['project-name']) !== '')) throw new \InvalidArgumentException('Choose an exact request UUID or an explicit new staff draft key and name.');
      $staff = \Drupal::entityTypeManager()->getStorage('user')->load((int) $options['staff-uid']);
      if (!$staff || !$staff->isActive() || !$staff->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('Choose an active staff execution account.');
      $switcher = \Drupal::service('account_switcher');
      $switcher->switchTo($staff);
      try {
        $reviews = \Drupal::service('famtastic_pipeline.full_site_review');
        $manifest = json_decode($bytes, TRUE, 512, JSON_THROW_ON_ERROR);
        $result = $create
          ? $reviews->createAndAttach((int) $options['customer'], (int) $options['organization'], (string) $options['project-name'], (string) $options['new-request-key'], $packageDirectory, $manifest, (string) $options['actor'], (string) $options['authority'])
          : $reviews->attach((string) $options['request'], (int) $options['customer'], (int) $options['organization'], $packageDirectory, $manifest, (string) $options['actor'], (string) $options['authority']);
      }
      finally { $switcher->switchBack(); }
      $this->io()->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $error) { $this->logger()->error($error->getMessage()); return self::EXIT_FAILURE; }
  }
}
