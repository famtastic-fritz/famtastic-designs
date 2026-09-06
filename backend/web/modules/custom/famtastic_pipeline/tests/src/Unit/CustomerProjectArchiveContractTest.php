<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Tests\UnitTestCase;

/** @group famtastic_pipeline */
final class CustomerProjectArchiveContractTest extends UnitTestCase {

  public function testCustomerArchiveIsRecoverableAndDoesNotChangeWorkflowStatus(): void {
    $module = dirname(__DIR__, 3);
    $service = file_get_contents($module . '/src/Service/CustomerPortalService.php');
    $controller = file_get_contents($module . '/src/Controller/CustomerPortalController.php');
    $routes = file_get_contents($module . '/famtastic_pipeline.routing.yml');
    $this->assertIsString($service);
    $this->assertIsString($controller);
    $this->assertIsString($routes);

    $this->assertStringContainsString('setWebsiteRequestArchiveState', $service);
    $this->assertStringContainsString("['archive', 'restore']", $service);
    $this->assertStringContainsString("'customer_archived_at' => \$shouldArchive ? \$now : NULL", $service);
    $this->assertStringContainsString("'website_request.customer_archived'", $service);
    $this->assertStringContainsString("'website_request.customer_restored'", $service);
    $this->assertStringNotContainsString("delete('famtastic_project_request')", $service);
    $this->assertStringContainsString('websiteRequestArchive', $controller);
    $this->assertStringContainsString('/api/customer/website-requests/{website_request}/archive', $routes);
    $this->assertStringContainsString("methods: [POST]", $routes);
    $this->assertStringContainsString("_csrf_request_header_token: 'TRUE'", $routes);
    $install = file_get_contents($module . '/famtastic_pipeline.install');
    $this->assertIsString($install);
    $this->assertStringContainsString('function famtastic_pipeline_update_8056', $install);
    $this->assertStringContainsString("'customer_archived_at' => ['type' => 'int'", $install);
  }

}
