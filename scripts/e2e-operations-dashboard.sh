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

    $prospect = \Drupal::entityTypeManager()->getStorage("famtastic_prospect")->create([
      "business_name" => "Workspace route test",
      "campaign" => "public_quote",
      "status" => "acknowledged",
      "public_email" => "workspace-test@example.invalid",
    ]);
    $prospect->save();
    $workspacePath = "/admin/famtastic/prospect/" . $prospect->id() . "/workspace";
    $prospects = (string) $renderer->renderRoot($controller->metric("prospects"));
    if (!str_contains($prospects, $workspacePath) || !str_contains($prospects, "Review today")) {
      throw new \RuntimeException("Prospect list does not expose its actionable lead workspace.");
    }
    $workspace = (string) $renderer->renderRoot($controller->prospectWorkspace($prospect));
    if (!str_contains($workspace, "Lead workspace") || !str_contains($workspace, "Automation boundary")) {
      throw new \RuntimeException("Lead workspace did not render its decision and safety context.");
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
    // Remove every synthetic workspace-test prospect (including ones left by
    // earlier runs) so repeat runs stay idempotent and the needs-response
    // queue count is not inflated by test data.
    $storage = \Drupal::entityTypeManager()->getStorage("famtastic_prospect");
    $staleIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition("public_email", "workspace-test@example.invalid")
      ->execute();
    if (!empty($staleIds)) {
      $storage->delete($storage->loadMultiple($staleIds));
      print "CLEANUP: removed " . count($staleIds) . " synthetic workspace-test prospect(s).\n";
    }
    $switcher->switchBack();
  }
'
