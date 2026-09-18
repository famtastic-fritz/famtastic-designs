<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/** Keeps old People bookmarks usable without exposing account data. */
final class LegacyAdminUserController extends ControllerBase {

  public function redirectToPeople(Request $request): LocalRedirectResponse {
    // Fixed named destination; never forward a caller-supplied destination.
    $request->query->remove('destination');
    $response = new LocalRedirectResponse(Url::fromRoute('entity.user.collection')->toString());
    $response->getCacheableMetadata()->setCacheMaxAge(0);
    return $response;
  }

}
