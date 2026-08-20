<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Registers and retrieves immutable Build DNA ledger projections.
 */
final class BuildDnaCommands extends DrushCommands {

  /**
   * Registers one Build DNA manifest in the searchable Drupal run ledger.
   */
  #[CLI\Command(name: 'famtastic:build-dna-register', aliases: ['fbdnr'])]
  #[CLI\Argument(name: 'path', description: 'Absolute path to build-dna.json.')]
  public function register(string $path): int {
    if (!is_file($path)) {
      $this->logger()->error('Build DNA file does not exist.');
      return self::EXIT_FAILURE;
    }
    try {
      $dna = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
      /** @var \Drupal\famtastic_pipeline\Service\BuildTelemetryService $telemetry */
      $telemetry = \Drupal::service('famtastic_pipeline.build_telemetry');
      $id = $telemetry->recordBuildDna($dna);
      $this->io()->writeln(json_encode([
        'ok' => TRUE,
        'id' => $id,
        'build_id' => $dna['build_id'],
        'build_key' => 'build-dna:' . $dna['build_id'],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      return self::EXIT_SUCCESS;
    }
    catch (\Throwable $e) {
      $this->logger()->error($e->getMessage());
      return self::EXIT_FAILURE;
    }
  }

  /**
   * Retrieves one Build DNA manifest by its immutable build ID.
   */
  #[CLI\Command(name: 'famtastic:build-dna-show', aliases: ['fbdns'])]
  #[CLI\Argument(name: 'buildId', description: 'Exact Build DNA build_id.')]
  public function show(string $buildId): int {
    $buildId = trim($buildId);
    if ($buildId === '') {
      $this->logger()->error('Build DNA build_id is required.');
      return self::EXIT_FAILURE;
    }
    $record = \Drupal::database()->select('famtastic_build_run', 'b')
      ->fields('b', ['id', 'build_key', 'status', 'source_sha', 'artifact_checksum', 'started_at', 'completed_at', 'output_manifest'])
      ->condition('build_key', 'build-dna:' . $buildId)
      ->execute()
      ->fetchAssoc();
    if (!$record) {
      $this->logger()->error('Build DNA record was not found.');
      return self::EXIT_FAILURE;
    }
    $manifest = json_decode((string) $record['output_manifest'], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->io()->writeln(json_encode([
      'record' => [
        'id' => (int) $record['id'],
        'build_key' => $record['build_key'],
        'status' => $record['status'],
        'source_sha' => $record['source_sha'],
        'artifact_checksum' => $record['artifact_checksum'],
        'started_at' => (int) $record['started_at'],
        'completed_at' => $record['completed_at'] === NULL ? NULL : (int) $record['completed_at'],
      ],
      'build_dna' => $manifest,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

}
