<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\PostizDropMutationService;
use Drupal\famtastic_pipeline\Utility\CampaignFileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin delete action for one campaign drop's LIVE Postiz record(s).
 *
 * Deletes (soft-deletes) every known Postiz post recorded for this drop in
 * posting-schedule.json — one per connected channel — via
 * PostizDropMutationService, exactly the safe order proven by hand this
 * session: revert-to-draft first if not already draft, then DELETE. Because
 * posting-schedule.json does not record each post's live status (only the
 * coarse drop-level `state`), every known id is passed to the service as
 * currentStatus 'UNKNOWN' — the service's own rule already reverts anything
 * that is not literally 'DRAFT', so this is the conservative, always-safe
 * choice, never a shortcut around it.
 *
 * This form never rewrites posting-schedule.json — that file is the
 * git-tracked, schema-validated campaign manifest, authored through
 * scripts/queue-campaign-drops.py --delete-drop (which also re-validates the
 * resulting manifest against campaign-schedule.schema.json before touching
 * Postiz). Re-running that CLI command afterwards is how the manifest itself
 * gets updated to reflect the deletion; this admin action only ever touches
 * the live Postiz record(s), through PostizDropMutationService directly —
 * never by shelling out to that or any other CLI.
 */
final class PostizDropDeleteForm extends ConfirmFormBase {

  private array $drop = [];
  private string $campaignSlug = '';
  private string $contentId = '';
  private array $knownIds = [];

  public function __construct(
    private readonly PostizDropMutationService $mutation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.postiz_drop_mutation'));
  }

  public function getFormId(): string {
    return 'famtastic_postiz_drop_delete';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $campaign_slug = NULL, ?string $content_id = NULL): array {
    $this->campaignSlug = (string) $campaign_slug;
    $this->contentId = (string) $content_id;

    $schedule = CampaignFileLocator::readJson($this->campaignSlug, 'posting-schedule.json');
    $this->drop = $schedule ? CampaignFileLocator::findDrop($schedule, $this->contentId) : [];
    if (!$this->drop) {
      throw new NotFoundHttpException('Unknown campaign drop.');
    }
    $this->knownIds = CampaignFileLocator::knownProviderIds($this->drop);

    if (!$this->knownIds) {
      $form['notice'] = [
        // '@' placeholders are HTML-escaped by t() automatically.
        '#markup' => '<p>' . $this->t('No live Postiz record exists yet for <code>@cid</code> — nothing to delete.', ['@cid' => $this->contentId]) . '</p>',
      ];
      $form['back'] = [
        '#type' => 'link',
        '#title' => $this->t('Back to Postiz drops'),
        '#url' => $this->getCancelUrl(),
        '#attributes' => ['class' => ['button']],
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): TranslatableMarkup {
    return $this->t('Delete @count live Postiz record(s) for @cid?', [
      '@count' => count($this->knownIds),
      '@cid' => $this->contentId,
    ]);
  }

  public function getDescription(): TranslatableMarkup {
    return $this->t('Each record is reverted to draft first (if not already) then soft-deleted via the Postiz public API — the same order proven safe by hand this session. The campaign manifest file on disk, its copy, and its media are untouched; re-run scripts/queue-campaign-drops.py --delete-drop @campaign/@cid to update posting-schedule.json itself.', [
      '@campaign' => $this->campaignSlug,
      '@cid' => $this->contentId,
    ]);
  }

  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete Postiz record(s)');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'drops'], ['query' => ['campaign' => $this->campaignSlug]]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ok = 0;
    foreach ($this->knownIds as $postId) {
      try {
        // 'UNKNOWN' is deliberate — see class doc. $confirmed = TRUE because
        // reaching this point already proved the 'administer famtastic
        // pipeline' permission and a valid Form API CSRF token.
        $this->mutation->deletePost($postId, 'UNKNOWN', TRUE, FALSE, $this->contentId, $this->campaignSlug);
        $ok++;
        $line = $this->mutation->formatAuditLine('delete', $this->contentId, $this->campaignSlug, NULL, [
          'post_id' => $postId,
          'was_status' => 'UNKNOWN',
          'is_test_flow' => FALSE,
        ]);
        $this->messenger()->addStatus($this->t('Audit log entry: @line', ['@line' => $line]));
      }
      catch (\Throwable $e) {
        $this->messenger()->addError($this->t('Failed to delete record @id: @msg', [
          '@id' => $postId,
          '@msg' => $e->getMessage(),
        ]));
      }
    }

    if ($ok > 0) {
      $this->messenger()->addStatus($this->t('Deleted @ok of @total known Postiz record(s) for @cid.', [
        '@ok' => $ok,
        '@total' => count($this->knownIds),
        '@cid' => $this->contentId,
      ]));
    }

    $form_state->setRedirect('famtastic_pipeline.marketing.tab', ['tab' => 'drops'], ['query' => ['campaign' => $this->campaignSlug]]);
  }

}
