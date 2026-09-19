<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/** Trusted code dependency, never request input, settings or editable config. */
interface PrivatePurchaseAuthorityInterface {
  /**
   * The one approved reunion scope identity for this installation.
   *
   * Test implementations belong outside the deployed module, in isolated runtimes.
   * The production implementation preserves the owner's exact closed allowlist.
   *
   * @return array{request_id:int,public_id:string,customer_id:int,organization_id:int,prospect_id:int,email:string,sku:string,scope_hash:string,policy:string,event_key:string,authority:string}
   */
  public function reunion(): array;
}
