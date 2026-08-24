<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Single-record approval action: flips one gate (content/media/publish) on
 * one social campaign record. This is the owner's command-center control.
 * Publish approval never auto-sends; it only marks the record eligible for
 * the separately-gated scheduling step.
 */
final class SocialRecordGateForm extends ConfirmFormBase {

  private const GATES = ['content', 'media', 'publish'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('datetime.time'));
  }

  private array $record = [];
  private string $gate = 'content';
  private int $target = 1;

  public function getFormId(): string {
    return 'famtastic_social_record_gate';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $content_id = NULL, ?string $gate = NULL, ?string $direction = NULL): array {
    $this->record = $this->database->select('famtastic_social_record', 'r')
      ->fields('r')
      ->condition('content_id', (string) $content_id)
      ->execute()->fetchAssoc() ?: [];
    if (!$this->record || !in_array($gate, self::GATES, TRUE)) {
      $form['missing'] = ['#markup' => '<p>' . $this->t('Unknown campaign record or gate.') . '</p>'];
      return $form;
    }
    $this->gate = (string) $gate;
    $this->target = $direction === 'revoke' ? 0 : 1;
    $current = (int) $this->record['approval_' . $this->gate];
    if ($current === $this->target) {
      $form['noop'] = ['#markup' => '<p>' . $this->t('This gate is already @state.', ['@state' => $current ? 'approved' : 'unapproved']) . '</p>'];
      return $form;
    }
    $form['question'] = [
      '#markup' => '<p>' . $this->t('@action the <strong>@gate</strong> gate for <strong>@cid</strong> (day @day, @moment)?',
        ['@action' => $this->target ? 'Approve' : 'Revoke', '@gate' => ucfirst($this->gate),
         '@cid' => $this->record['content_id'], '@day' => $this->record['day'], '@moment' => $this->record['moment']]) . '</p>',
    ];
    if ($this->gate === 'publish' && $this->target === 1) {
      $form['warning'] = ['#markup' => '<p><em>' . $this->t('Publish approval marks the record eligible for scheduling. Actual publishing remains a separate bounded-batch decision.') . '</em></p>'];
    }
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return $this->t('Confirm gate decision');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.operations_metric', ['metric' => 'social-records']);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->database->update('famtastic_social_record')
      ->fields([
        'approval_' . $this->gate => $this->target,
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', (int) $this->record['id'])
      ->execute();
    $this->messenger()->addStatus($this->t('@gate gate @state for @cid.', [
      '@gate' => ucfirst($this->gate),
      '@state' => $this->target ? 'approved' : 'revoked',
      '@cid' => $this->record['content_id'],
    ]));
    $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'social-records']);
  }

}
