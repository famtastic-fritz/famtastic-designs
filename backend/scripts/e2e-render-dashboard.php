<?php

/**
 * Renders the Campaign Operations dashboard HTML for validator consumption.
 *
 * Drush php:script — prints the rendered page so shell validators can assert
 * against real controller output without an HTTP round-trip.
 */

use Drupal\famtastic_pipeline\Controller\OperationsController;

$controller = OperationsController::create(\Drupal::getContainer());
$build = $controller->dashboard();
print (string) \Drupal::service('renderer')->renderRoot($build);
