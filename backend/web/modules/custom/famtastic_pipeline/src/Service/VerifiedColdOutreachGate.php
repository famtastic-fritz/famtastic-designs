<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/**
 * Fail-closed transport gate for owner-approved verified-cold mail.
 *
 * The public-preview dispatcher is intentionally allowed to use the shared
 * transactional mailer for ordinary owner invitations. A researched cold
 * campaign is commercial outreach, however, so it needs its own explicit
 * real-send acknowledgement in addition to the existing global gate. The
 * default SMTP setting is therefore never sufficient to send this lane.
 */
final class VerifiedColdOutreachGate {

  /**
   * Allows only an explicitly enabled local-memory rehearsal or a real send
   * with both the global and verified-cold gates deliberately acknowledged.
   */
  public function assertDispatchAllowed(): void {
    $transport = strtolower(trim($this->value(
      'FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT',
      'famtastic_transactional_email_transport',
      'smtp',
    )));

    if ($transport === 'memory') {
      if (!$this->enabled('FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH', 'famtastic_allow_verified_cold_memory_dispatch')) {
        throw new \RuntimeException('Verified-cold dispatch is memory-only for a local rehearsal, but memory dispatch is not explicitly enabled. Set FAMTASTIC_ALLOW_VERIFIED_COLD_MEMORY_DISPATCH=true only with FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=memory and a local capture path.');
      }
      return;
    }

    if ($transport !== 'smtp') {
      throw new \RuntimeException('Verified-cold dispatch is disabled because the transactional email transport is neither approved local memory nor SMTP.');
    }

    if (!$this->enabled('FAMTASTIC_ALLOW_REAL_OUTREACH', 'famtastic_allow_real_outreach')) {
      throw new \RuntimeException('Verified-cold real outreach requires the existing FAMTASTIC_ALLOW_REAL_OUTREACH=true owner gate. No email was sent.');
    }
    if (!$this->enabled('FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH', 'famtastic_allow_verified_cold_real_outreach')) {
      throw new \RuntimeException('Verified-cold real outreach is disabled. An owner must explicitly set FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH=true after reviewing the exact held delivery IDs. No email was sent.');
    }
  }

  private function enabled(string $environment, string $setting): bool {
    return filter_var($this->value($environment, $setting, FALSE), FILTER_VALIDATE_BOOL);
  }

  private function value(string $environment, string $setting, mixed $default): mixed {
    $environmentValue = getenv($environment);
    if ($environmentValue !== FALSE && trim((string) $environmentValue) !== '') {
      return $environmentValue;
    }
    // This deliberately mirrors OutreachMailer. A gate may never interpret a
    // config value as local-memory if the mailer will actually select SMTP.
    return Settings::get($setting, $default);
  }

}
