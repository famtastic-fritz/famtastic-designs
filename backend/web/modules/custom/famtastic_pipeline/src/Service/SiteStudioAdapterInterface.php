<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\famtastic_pipeline\Entity\Project;

/**
 * Boundary to Site Studio (FAMtastic's production engine).
 *
 * V1 ships FileExportAdapter (writes the request for manual copy/submission).
 * Future ApiAdapter / QueueAdapter / McpAdapter implement this same contract
 * without changing any calling code.
 */
interface SiteStudioAdapterInterface {

  /**
   * Hands off a generated request.
   *
   * @return array{status:string,location:?string,note:string}
   */
  public function submit(array $json, string $brief, Project $project): array;

}
