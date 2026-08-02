#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DRUSH="$REPO_ROOT/backend/vendor/bin/drush"

"$DRUSH" -r "$REPO_ROOT/backend/web" php:eval '
  $metrics = [
    "campaigns",
    "prospects",
    "proofs-ready",
    "emails-sent",
    "clicks",
    "paid-orders",
    "open-jobs",
    "open-exceptions",
  ];
  $switcher = \Drupal::service("account_switcher");
  $switcher->switchTo(\Drupal::entityTypeManager()->getStorage("user")->load(1));
  try {
    $controller = \Drupal::classResolver()->getInstanceFromDefinition(\Drupal\famtastic_pipeline\Controller\OperationsController::class);
    $renderer = \Drupal::service("renderer");
    $dashboard = (string) $renderer->renderRoot($controller->dashboard());
    foreach ($metrics as $metric) {
      $path = "/admin/famtastic/metric/" . $metric;
      if (!str_contains($dashboard, $path)) {
        throw new \RuntimeException("Dashboard is missing metric link: " . $path);
      }
      $page = (string) $renderer->renderRoot($controller->metric($metric));
      if (!str_contains($page, "Operations Dashboard")) {
        throw new \RuntimeException("Metric page is missing its dashboard return link: " . $metric);
      }
    }

    $paidCount = (int) \Drupal::database()->select("famtastic_order", "o")
      ->condition("payment_status", "paid")
      ->countQuery()
      ->execute()
      ->fetchField();
    if (!str_contains($dashboard, "View Paid Orders records (" . $paidCount . ")")) {
      throw new \RuntimeException("Paid Orders card does not expose its exact count.");
    }
    $paidOrders = (string) $renderer->renderRoot($controller->metric("paid-orders"));
    if ($paidCount > 0 && !str_contains($paidOrders, "Order #")) {
      throw new \RuntimeException("Paid Orders page does not expose the stored paid orders.");
    }
    print "PASS: operations tiles and all eight exact-record drill-downs render; paid orders: " . $paidCount . ".\n";
  }
  finally {
    $switcher->switchBack();
  }
'
