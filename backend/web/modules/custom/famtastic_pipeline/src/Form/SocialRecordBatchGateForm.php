<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Batch approval action for all records of a given campaign day.
 *
 * Flips gates (all, content, media, or publish) across all moments of a day
 * in a single owner-confirmed action.
 */
final class SocialRecordBatchGateForm extends ConfirmFormBase {

  private const ALLOWED_GATES = ['all', 'content', 'media', 'publish'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  private int $day = 1;
  private string $gate = 'all';
  private string $direction = 'approve';
  private array $records = [];

  public function getFormId(): string {
    return 'famtastic_social_record_batch_gate';
  }

  public function buildForm(array $form, FormStateInterface $form_state, int $day = 1, string $gate = 'all', string $direction = 'approve'): array {
    $this->day = $day;
    $this->gate = in_array($gate, self::ALLOWED_GATES, TRUE) ? $gate : 'all';
    $this->direction = $direction === 'revoke' ? 'revoke' : 'approve';

    $this->records = $this->database->select('famtastic_social_record', 'r')
      ->fields('r')
      ->condition('day', $this->day)
      ->orderBy('id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    if ($this->records === []) {
      $form['missing'] = ['#markup' => '<p>' . $this->t('No campaign records found for Day @day.', ['@day' => $this->day]) . '</p>'];
      return $form;
    }

    $form['summary'] = [
      '#markup' => '<p>' . $this->t('Targeting <strong>@count moment(s)</strong> on <strong>Day @day</strong>.', [
        '@count' => count($this->records),
        '@day' => $this->day,
      ]) . '</p>',
    ];

    if ($this->gate === 'publish' || $this->gate === 'all') {
      if ($this->direction === 'approve') {
        $form['warning'] = [
          '#markup' => '<p><em>' . $this->t('Publish approval marks records eligible for scheduled dispatch. Actual publishing remains a separate bounded-batch execution.') . '</em></p>',
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): \Drupal\Core\StringTranslation\TranslatableMarkup {
    $gateLabel = $this->gate === 'all' ? $this->t('ALL gates (Content, Media, Publish)') : ucfirst($this->gate) . ' ' . $this->t('gate');
    return $this->t('@action @gate for Day @day?', [
      '@action' => ucfirst($this->direction),
      '@gate' => $gateLabel,
      '@day' => $this->day,
    ]);
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.marketing.tab', ['tab' => 'queue']);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $target = $this->direction === 'approve' ? 1 : 0;
    $now = $this->time->getRequestTime();

    $fields = ['changed' => $now];
    if ($this->gate === 'all') {
      $fields['approval_content'] = $target;
      $fields['approval_media'] = $target;
      $fields['approval_publish'] = $target;
    }
    else {
      $fields['approval_' . $this->gate] = $target;
    }

    $this->database->update('famtastic_social_record')
      ->fields($fields)
      ->condition('day', $this->day)
      ->execute();

    $this->messenger()->addStatus($this->t('Day @day: @action applied to @gate for @count record(s).', [
      '@day' => $this->day,
      '@action' => $this->direction === 'approve' ? $this->t('Approval') : $this->t('Revocation'),
      '@gate' => $this->gate === 'all' ? $this->t('all gates') : $this->gate . ' ' . $this->t('gate'),
      '@count' => count($this->records),
    ]));

    $form_state->setRedirect('famtastic_pipeline.marketing.tab', ['tab' => 'queue']);
  }

}
