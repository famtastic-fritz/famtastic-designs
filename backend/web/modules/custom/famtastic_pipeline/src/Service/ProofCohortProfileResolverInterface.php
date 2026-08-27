<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Resolves a named proof cohort profile before a public proof job is created.
 */
interface ProofCohortProfileResolverInterface {

  /**
   * Returns one validated anonymous-public profile.
   *
   * @return array{id:string,audience:string,direction_count:int,directions:array<string,array{name:string,intent:string}>}
   */
  public function resolveAnonymous(?string $profileId = NULL): array;

}
