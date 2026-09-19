<?php

declare(strict_types=1);

namespace Drupal\famtastic_private_probe;

use Drupal\famtastic_pipeline\Service\PrivatePurchaseAuthorityInterface;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;

/** No HTTP/settings selector; this class is absent from the deployed module. */
final class SyntheticPrivatePurchaseAuthority implements PrivatePurchaseAuthorityInterface {
  public const DECLARATION = 'Synthetic offline fixture declaration; no human approval';

  public static function binding(): array {
    $file = ProbeBoundary::bindingPath();
    if (!is_file($file) || realpath($file) !== $file || (fileperms($file) & 0077) !== 0 || filesize($file) > 16384) {
      throw new \RuntimeException('synthetic_probe_binding_missing_or_unsafe');
    }
    $binding = json_decode(file_get_contents($file), TRUE, 512, JSON_THROW_ON_ERROR);
    $a = $binding['authority'] ?? [];
    $run = $binding['run_id'] ?? '';
    if (($binding['schema'] ?? '') !== 'famtastic.synthetic-private-authority.v1'
      || !is_string($run) || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $run)
      || ($a['request_id'] ?? NULL) !== 901
      || !is_int($a['customer_id'] ?? NULL) || $a['customer_id'] < 1001
      || !is_int($a['organization_id'] ?? NULL) || $a['organization_id'] < 2001
      || $a['customer_id'] === $a['organization_id']
      || !is_int($a['prospect_id'] ?? NULL) || $a['prospect_id'] < 1 || $a['prospect_id'] === 298
      || !is_string($a['email'] ?? NULL) || $a['email'] !== "owner+$run@probe.test"
      || ($a['sku'] ?? '') !== 'PRIVATE-SYNTHETIC-199'
      || ($a['policy'] ?? '') !== "synthetic-private-native-v1:$run"
      || ($a['event_key'] ?? '') !== "synthetic-private-scope:$run:offered"
      || ($a['authority'] ?? '') !== self::DECLARATION
      || in_array($a['public_id'] ?? '', [PrivatePurchaseService::REUNION, PrivatePurchaseService::STOCK], TRUE)
      || ($a['scope_hash'] ?? '') === PrivatePurchaseService::REUNION_HASH
      || !is_array($binding['scope'] ?? NULL)
      || ($a['scope_hash'] ?? '') !== hash('sha256', json_encode($binding['scope'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))) {
      throw new \RuntimeException('synthetic_probe_binding_refused');
    }
    return $binding;
  }

  public function reunion(): array {
    return self::binding()['authority'];
  }
}
