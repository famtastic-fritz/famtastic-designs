<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Project;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Generates and persists a Site Studio request onto a project.
 *
 * Called from both the admin UI and a drush command. Produces the human brief +
 * machine JSON, stores them on the project, and hands off via the adapter.
 */
class StudioRequestGenerator {

  public function __construct(
    protected PipelineRepository $repository,
    protected SiteStudioRequestBuilder $builder,
    protected SiteStudioAdapterInterface $adapter,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected OperationalLedger $ledger,
  ) {}

  /**
   * Generates the request for a prospect and returns the project.
   *
   * @return array{project:\Drupal\famtastic_pipeline\Entity\Project,handoff:array}
   */
  public function generate(Prospect $prospect): array {
    $intake = $this->repository->getIntake($prospect);
    $order = $this->repository->getOrder($prospect);

    $json = $this->builder->buildJson($prospect, $intake, $order);

    $project = $this->repository->getProject($prospect);
    if (!$project) {
      $project = Project::create(['prospect_ref' => $prospect->id()]);
    }
    if ($order) {
      $project->set('order_ref', $order->id());
      $offer = $this->ledger->activeOffer((string) $order->get('package')->value);
      $project->set('revision_limit', (int) ($offer['included_revisions'] ?? 1));
    }
    if ($intake) {
      $project->set('intake_ref', $intake->id());
    }
    // Stamp the project id into the JSON now that we can persist it.
    $project->save();
    $json['project_id'] = (int) $project->id();

    $brief = $this->builder->buildBrief($json);
    $project->set('studio_json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $project->set('studio_brief', $brief);
    if (in_array($project->get('delivery_status')->value, ['', 'draft'], TRUE)) {
      $project->set('delivery_status', 'request_generated');
    }
    $project->save();

    $handoff = $this->adapter->submit($json, $brief, $project);

    return ['project' => $project, 'handoff' => $handoff];
  }

}
