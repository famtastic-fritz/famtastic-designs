<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\AcquisitionCampaignReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Staff-only read-only aggregate; route permission is mandatory. */
final class AcquisitionCampaignReportController extends ControllerBase {
  public function __construct(private readonly AcquisitionCampaignReportService $reports) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.acquisition_report'));
  }

  public function report(string $campaign_key): JsonResponse {
    try {
      return new JsonResponse($this->reports->report($campaign_key), 200, ['Cache-Control' => 'no-store, private', 'Vary' => 'Cookie']);
    }
    catch (\OutOfBoundsException) {
      return new JsonResponse(['error' => 'campaign_not_found'], 404, ['Cache-Control' => 'no-store, private']);
    }
    catch (\InvalidArgumentException) {
      return new JsonResponse(['error' => 'invalid_campaign_key'], 400, ['Cache-Control' => 'no-store, private']);
    }
  }
}
