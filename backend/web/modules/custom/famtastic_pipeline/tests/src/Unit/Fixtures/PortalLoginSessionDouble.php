<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Controller;

/** Test-only namespace interception: never starts a real Drupal session. */
final class PortalLoginSessionDouble {
  public static ?\Closure $finalize = NULL;
}

function user_login_finalize($account): void {
  if (!PortalLoginSessionDouble::$finalize) throw new \LogicException('No isolated login finalizer was installed.');
  (PortalLoginSessionDouble::$finalize)($account);
}
