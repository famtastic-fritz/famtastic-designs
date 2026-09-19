<?php

declare(strict_types=1);

namespace Drupal\famtastic_private_probe;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/** Overrides only the trusted authority dependency in the disposable runtime. */
final class FamtasticPrivateProbeServiceProvider extends ServiceProviderBase {
  public function alter(ContainerBuilder $container): void {
    ProbeBoundary::paths((string) $container->getParameter('app.root'));
    if (!$container->hasDefinition('famtastic_pipeline.private_purchase_authority')) {
      throw new \RuntimeException('synthetic_probe_authority_definition_missing');
    }
    // Do not instantiate the authority here: the seed writes its private binding
    // after module installation and before the first private-purchase access.
    $container->getDefinition('famtastic_pipeline.private_purchase_authority')
      ->setClass(SyntheticPrivatePurchaseAuthority::class)->setArguments([]);
  }
}
