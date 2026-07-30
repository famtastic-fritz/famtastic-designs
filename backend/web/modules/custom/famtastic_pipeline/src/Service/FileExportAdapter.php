<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\famtastic_pipeline\Entity\Project;
use Psr\Log\LoggerInterface;

/**
 * V1 Site Studio adapter: writes the request to the private files area for a
 * human (Fritz) to review and submit to Site Studio manually.
 */
class FileExportAdapter implements SiteStudioAdapterInterface {

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function submit(array $json, string $brief, Project $project): array {
    $dir = 'private://site-studio-requests';
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $base = $dir . '/project-' . $project->id();
    $jsonPath = $base . '.json';
    $briefPath = $base . '.md';
    $this->fileSystem->saveData(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $jsonPath, FileSystemInterface::EXISTS_REPLACE);
    $this->fileSystem->saveData($brief, $briefPath, FileSystemInterface::EXISTS_REPLACE);
    $realJson = $this->fileSystem->realpath($jsonPath);
    $this->logger->info('Site Studio request exported for project @id to @p', ['@id' => $project->id(), '@p' => $realJson]);
    return [
      'status' => 'exported',
      'location' => $realJson ?: $jsonPath,
      'note' => 'V1 manual handoff: copy this JSON/brief into Site Studio.',
    ];
  }

}
