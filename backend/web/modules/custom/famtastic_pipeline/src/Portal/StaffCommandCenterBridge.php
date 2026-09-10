<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Portal;

use Drupal\Core\Session\AccountInterface;

/**
 * Adds the staff-only Drupal command center capability to a portal session.
 */
final class StaffCommandCenterBridge {

  public const PERMISSION = 'administer famtastic pipeline';

  /**
   * Returns the session unchanged unless Drupal grants the exact permission.
   */
  public static function enrichSession(AccountInterface $account, array $session): array {
    if (!$account->hasPermission(self::PERMISSION)) {
      return $session;
    }

    $session['staff'] = [
      'can_access_command_center' => TRUE,
      'command_center_url' => '/web/admin/famtastic',
    ];
    return $session;
  }

}
