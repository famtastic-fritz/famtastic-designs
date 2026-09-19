<?php

declare(strict_types=1);

namespace Drupal\famtastic_private_probe;

/** Test-only containment, independently checked at compilation and use. */
final class ProbeBoundary {
  public const FILES = [
    'famtastic_private_probe.info.yml',
    'src/ProbeBoundary.php',
    'src/FamtasticPrivateProbeServiceProvider.php',
    'src/SyntheticPrivatePurchaseAuthority.php',
  ];

  /** No database, mail service or authority access during module installation. */
  public static function paths(string $root): string {
    $sandbox = realpath(getenv('SELECTED_DRUPAL_SANDBOX') ?: '') ?: '';
    if (PHP_SAPI !== 'cli' || !preg_match('#/famtastic-selected-drupal\.[A-Za-z0-9]{6}$#D', $sandbox)
      || realpath($root) !== "$sandbox/backend/web"
      || realpath(dirname(__DIR__)) !== "$sandbox/backend/web/modules/custom/famtastic_private_probe"
      || ini_get('allow_url_fopen') !== '0'
      || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory'
      || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE') !== "$sandbox/mail.jsonl"
      || getenv('FAMTASTIC_EMAIL_TRANSPORT') !== 'memory'
      || getenv('FAMTASTIC_ALLOW_REAL_OUTREACH') !== 'false'
      || getenv('FAMTASTIC_DEPLOY_TRANSPORT') !== 'disabled'
      || getenv('FAMTASTIC_HOSTING_BILLING_PROVIDER') !== 'disabled') {
      throw new \RuntimeException('synthetic_probe_boundary_refused');
    }
    foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect', 'mail'] as $function) {
      if (function_exists($function)) throw new \RuntimeException('synthetic_probe_transport_refused');
    }
    // The installed module must be the exact copy from the isolated scripts tree.
    foreach (self::FILES as $file) {
      $source = "$sandbox/scripts/private-purchase-synthetic/famtastic_private_probe/$file";
      $installed = dirname(__DIR__) . '/' . $file;
      if (realpath($source) !== $source || !is_file($source) || !is_file($installed)
        || hash_file('sha256', $source) !== hash_file('sha256', $installed)) {
        throw new \RuntimeException('synthetic_probe_source_refused');
      }
    }
    return $sandbox;
  }

  public static function runtime(): string {
    $sandbox = self::paths(\Drupal::root());
    $options = \Drupal::database()->getConnectionOptions();
    if (($options['driver'] ?? '') !== 'sqlite'
      || realpath($options['database'] ?? '') !== "$sandbox/backend/web/sites/default/files/.ht.sqlite"
      || \Drupal::config('system.mail')->get('interface.default') !== 'test_mail_collector'
      || realpath("$sandbox/backend/private") !== "$sandbox/backend/private") {
      throw new \RuntimeException('synthetic_probe_runtime_refused');
    }
    return $sandbox;
  }

  public static function bindingPath(): string {
    return self::runtime() . '/backend/private/private-purchase-synthetic.json';
  }
}
