<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\DeepDiveInvitationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Token-scoped private interview API for owner-invited discovery. */
final class DeepDiveController extends ControllerBase {

  public function __construct(private readonly DeepDiveInvitationService $deepDives) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('famtastic_pipeline.deep_dive_invitations'));
  }

  public function view(Request $request, string $invitation): JsonResponse {
    try {
      return $this->noStore(new JsonResponse([
        'ok' => TRUE,
        'deep_dive' => $this->deepDives->view($invitation, (string) $request->headers->get('X-Deep-Dive-Token')),
      ]));
    }
    catch (\InvalidArgumentException $error) {
      return $this->noStore(new JsonResponse(['ok' => FALSE, 'error' => 'deep_dive_unavailable', 'message' => $error->getMessage()], 404));
    }
  }

  public function answer(Request $request, string $invitation): JsonResponse {
    $data = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($data)) {
      return $this->noStore(new JsonResponse(['ok' => FALSE, 'error' => 'invalid_request', 'message' => 'Send one answer at a time.'], 422));
    }
    try {
      return $this->noStore(new JsonResponse([
        'ok' => TRUE,
        'deep_dive' => $this->deepDives->answer(
          $invitation,
          (string) $request->headers->get('X-Deep-Dive-Token'),
          (string) ($data['key'] ?? ''),
          $data['answer'] ?? NULL,
        ),
      ]));
    }
    catch (\InvalidArgumentException $error) {
      return $this->noStore(new JsonResponse(['ok' => FALSE, 'error' => 'invalid_answer', 'message' => $error->getMessage()], 422));
    }
  }

  private function noStore(JsonResponse $response): JsonResponse {
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Referrer-Policy', 'no-referrer');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    return $response;
  }

}
