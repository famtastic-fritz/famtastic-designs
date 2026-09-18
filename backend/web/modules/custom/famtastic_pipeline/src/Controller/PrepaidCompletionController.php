<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Same authenticated purchase; never a coupon or a second checkout. */
final class PrepaidCompletionController extends ControllerBase {
  public function complete(Request $request, string $order): JsonResponse {
    $account = $this->currentUser();
    if (!$account->isAuthenticated()) return $this->response(['ok' => FALSE, 'error' => 'authentication_required'], 401);
    $flood = \Drupal::service('flood');
    $identifier = 'uid:' . $account->id();
    if (!$flood->isAllowed('famtastic.prepaid_completion', 10, 600, $identifier)) return $this->response(['ok' => FALSE, 'error' => 'retry_later'], 429);
    $flood->register('famtastic.prepaid_completion', 600, $identifier);
    try {
      $input = json_decode($request->getContent(), TRUE, 32, JSON_THROW_ON_ERROR);
      if (!is_array($input)) throw new \InvalidArgumentException('Invalid request');
      $result = (new OfflinePrepaymentService())->complete((int) $order, $account, (string) ($input['completion_code'] ?? ''), $input);
      return $this->response(['ok' => TRUE] + $result, 200);
    }
    catch (\JsonException|\InvalidArgumentException $error) { return $this->response(['ok' => FALSE, 'error' => 'invalid_request'], 400); }
    catch (\RuntimeException $error) { return $this->response(['ok' => FALSE, 'error' => 'completion_unavailable', 'message' => 'The completion code, account or current scope could not be verified.'], 409); }
  }

  private function response(array $data, int $status): JsonResponse {
    return new JsonResponse($data, $status, ['Cache-Control' => 'no-store, private']);
  }
}
