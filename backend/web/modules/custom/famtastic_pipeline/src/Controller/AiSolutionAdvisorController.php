<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Service\AiSolutionAdvisorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public AI advisor and brief synthesizer API controller.
 */
class AiSolutionAdvisorController extends ControllerBase {

  public function __construct(
    private readonly AiSolutionAdvisorService $advisor,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('famtastic_pipeline.ai_solution_advisor'),
    );
  }

  /**
   * Responds to project advice requests.
   */
  public function advise(Request $request): JsonResponse {
    $content = (string) $request->getContent();
    $data = Json::decode($content) ?: [];

    $prompt = (string) ($data['prompt'] ?? '');
    $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
    $context = is_array($data['context'] ?? null) ? $data['context'] : [];

    $result = $this->advisor->advise($prompt, $answers, $context);

    return new JsonResponse([
      'status' => 'ok',
      'recommendation' => $result,
    ], Response::HTTP_OK, [
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'no-cache, private',
    ]);
  }

  /**
   * Responds to brief synthesis requests.
   */
  public function synthesize(Request $request): JsonResponse {
    $content = (string) $request->getContent();
    $data = Json::decode($content) ?: [];

    $brief = $this->advisor->synthesizeBrief($data);

    return new JsonResponse([
      'status' => 'ok',
      'brief' => $brief,
    ], Response::HTTP_OK, [
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'no-cache, private',
    ]);
  }

}
