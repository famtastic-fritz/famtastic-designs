<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\PostizDropMutationService;
use Drupal\famtastic_pipeline\Utility\CampaignFileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin edit action for one campaign drop's LIVE Postiz record(s).
 *
 * Postiz's public API v1 has no in-place content-edit endpoint for a post
 * that already exists — only a status change (draft <-> schedule) and a
 * delete (see PostizDropMutationService). So "edit" here is scoped to
 * exactly that: flipping every known Postiz record for this drop between
 * draft and scheduled. Changing copy, media, channels, or the schedule time
 * itself remains the campaign-manifest authoring path
 * (scripts/queue-campaign-drops.py --edit-drop against posting-schedule.json,
 * reviewed and committed like any other content change) — it is never
 * reimplemented here against a live web request, and this form never shells
 * out to that script or any other CLI. It calls PostizDropMutationService
 * directly, as an injected Drupal service.
 *
 * A drop can carry more than one live Postiz record — one per connected
 * channel (posting-schedule.json's provider_ids.postiz_scheduled_group) —
 * so this form applies the status change to every known id for the drop,
 * not just one.
 *
 * CSRF: Drupal's Form API attaches and validates a form_token on every POST
 * automatically; reaching submitForm() already proves both a valid token and
 * the 'administer famtastic pipeline' permission (the route requirement),
 * so $confirmed passed to the mutation service is TRUE — this is the human
 * authorization the service demands, not a default.
 */
final class PostizDropEditForm extends FormBase {

  private array $drop = [];
  private string $campaignSlug = '';
  private string $contentId = '';

  public function __construct(
    private readonly PostizDropMutationService $mutation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.postiz_drop_mutation'));
  }

  public function getFormId(): string {
    return 'famtastic_postiz_drop_edit';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $campaign_slug = NULL, ?string $content_id = NULL): array {
    $this->campaignSlug = (string) $campaign_slug;
    $this->contentId = (string) $content_id;

    $schedule = CampaignFileLocator::readJson($this->campaignSlug, 'posting-schedule.json');
    $this->drop = $schedule ? CampaignFileLocator::findDrop($schedule, $this->contentId) : [];
    if (!$this->drop) {
      throw new NotFoundHttpException('Unknown campaign drop.');
    }

    $knownIds = CampaignFileLocator::knownProviderIds($this->drop);

    $form['campaign_slug'] = ['#type' => 'value', '#value' => $this->campaignSlug];
    $form['content_id'] = ['#type' => 'value', '#value' => $this->contentId];
    $form['known_ids'] = ['#type' => 'value', '#value' => $knownIds];

    // NOTE: '@'-prefixed t() placeholders are HTML-escaped by Drupal
    // automatically on substitution — pre-escaping the value here as well
    // would double-encode entities (e.g. an "&" in a drop theme rendering as
    // "&amp;amp;"). Pass raw values to '@' placeholders; escape only when
    // building a raw HTML string by hand (no t() placeholder involved).
    $form['summary'] = [
      '#markup' => '<div class="famtastic-ops__lede"><p>'
        . $this->t('Editing <code>@cid</code> in campaign <code>@campaign</code>.', [
          '@cid' => $this->contentId,
          '@campaign' => $this->campaignSlug,
        ])
        . '</p><p>' . $this->t('Theme: @theme. Manifest state: @state. Known live Postiz record(s): @count.', [
          '@theme' => (string) ($this->drop['theme'] ?? '—'),
          '@state' => (string) ($this->drop['state'] ?? 'idea'),
          '@count' => (string) count($knownIds),
        ]) . '</p></div>',
    ];

    if (!$knownIds) {
      $form['notice'] = [
        '#markup' => '<p>' . $this->t('No live Postiz record exists yet for this drop — nothing to edit here. Queue it first with scripts/queue-campaign-drops.py.') . '</p>',
      ];
      return $form;
    }

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('New status for every known record'),
      '#options' => [
        'draft' => $this->t('Draft (inert — will not fire)'),
        'schedule' => $this->t('Scheduled (will fire at its stored time)'),
      ],
      '#default_value' => 'draft',
      '#description' => $this->t('Applies to all @count known Postiz record(s) for this drop.', ['@count' => count($knownIds)]),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply status change'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'drops'], ['query' => ['campaign' => $this->campaignSlug]]),
      '#attributes' => ['class' => ['button']],
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $campaignSlug = (string) $form_state->getValue('campaign_slug');
    $contentId = (string) $form_state->getValue('content_id');
    $status = (string) $form_state->getValue('status');
    $knownIds = (array) $form_state->getValue('known_ids');

    $ok = 0;
    foreach ($knownIds as $postId) {
      $postId = (string) $postId;
      try {
        $this->mutation->changeStatus($postId, $status, TRUE, FALSE, $contentId, $campaignSlug);
        $ok++;
        $line = $this->mutation->formatAuditLine('change_status', $contentId, $campaignSlug, NULL, [
          'post_id' => $postId,
          'new_status' => $status,
          'is_test_flow' => FALSE,
        ]);
        $this->messenger()->addStatus($this->t('Audit log entry: @line', ['@line' => $line]));
      }
      catch (\Throwable $e) {
        $this->messenger()->addError($this->t('Failed to change status for record @id: @msg', [
          '@id' => $postId,
          '@msg' => $e->getMessage(),
        ]));
      }
    }

    if ($ok > 0) {
      $this->messenger()->addStatus($this->t('Set @ok of @total known record(s) to @status for @cid.', [
        '@ok' => $ok,
        '@total' => count($knownIds),
        '@status' => $status,
        '@cid' => $contentId,
      ]));
    }

    $form_state->setRedirect('famtastic_pipeline.marketing.tab', ['tab' => 'drops'], ['query' => ['campaign' => $campaignSlug]]);
  }

}
