<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Exact existing owner approval. No environment/config/request overrides. */
final class ApprovedPrivatePurchaseAuthority implements PrivatePurchaseAuthorityInterface {
  public function reunion(): array {
    return [
      'request_id' => 16,
      'public_id' => PrivatePurchaseService::REUNION,
      'customer_id' => 14,
      'organization_id' => 14,
      'prospect_id' => 298,
      'email' => 'mbshclassof2000@gmail.com',
      'sku' => 'PRIVATE-REUNION16-199',
      'scope_hash' => PrivatePurchaseService::REUNION_HASH,
      'policy' => PrivatePurchaseService::REUNION_POLICY,
      'event_key' => 'private-scope:request:16:class-of-2000-v1',
      'authority' => 'Fritz Medine explicit approval',
    ];
  }
}
