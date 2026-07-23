<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\famtastic_pipeline\Entity\Project;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\StudioRequestGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin actions for generating and exporting the Site Studio request.
 */
class StudioController extends ControllerBase {

  public function __construct(
    protected StudioRequestGenerator $generator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.studio_generator'));
  }

  /**
   * Generates the Site Studio request for a prospect (admin button).
   */
  public function generate(Prospect $famtastic_prospect) {
    $result = $this->generator->generate($famtastic_prospect);
    $this->messenger()->addStatus($this->t('Site Studio request generated. Exported to: @loc', [
      '@loc' => $result['handoff']['location'] ?? 'n/a',
    ]));
    return $this->redirect('entity.famtastic_project.edit_form', ['famtastic_project' => $result['project']->id()]);
  }

  /**
   * Downloads the machine JSON request.
   */
  public function exportJson(Project $famtastic_project): Response {
    $json = $famtastic_project->get('studio_json')->value ?: '{}';
    return new Response($json, 200, [
      'Content-Type' => 'application/json',
      'Content-Disposition' => 'attachment; filename="site-studio-project-' . $famtastic_project->id() . '.json"',
    ]);
  }

  /**
   * Downloads the human-readable brief.
   */
  public function exportBrief(Project $famtastic_project): Response {
    $brief = $famtastic_project->get('studio_brief')->value ?: '';
    return new Response($brief, 200, [
      'Content-Type' => 'text/markdown; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="site-studio-project-' . $famtastic_project->id() . '.md"',
    ]);
  }

}
