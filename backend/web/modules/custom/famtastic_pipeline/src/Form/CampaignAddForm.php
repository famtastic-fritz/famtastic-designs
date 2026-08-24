<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a new outreach campaign (FAMtastic Operations → Campaigns).
 * Fills the missing CRUD path: campaigns were previously listable but
 * could not be created from the admin.
 */
final class CampaignAddForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('datetime.time'));
  }

  public function getFormId(): string {
    return 'famtastic_campaign_add';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#markup' => '<p>' . $this->t('The campaign key is the stable identifier used by manifests, UTMs, and reporting. Use lowercase letters, numbers, and dashes.') . '</p>',
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign name'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#placeholder' => $this->t('Web Basics — Fall outreach'),
    ];
    $form['campaign_key'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Campaign key'),
      '#machine_name' => [
        'exists' => [$this, 'keyExists'],
        'source' => ['name'],
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      '#required' => TRUE,
    ];
    $form['channel'] = [
      '#type' => 'select',
      '#title' => $this->t('Primary channel'),
      '#options' => [
        '' => $this->t('Unassigned'),
        'email' => $this->t('Email'),
        'facebook' => $this->t('Facebook'),
        'instagram' => $this->t('Instagram'),
        'multi' => $this->t('Multi-channel'),
      ],
    ];
    $form['source_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source filter (optional)'),
      '#description' => $this->t('Restrict this campaign to prospects from one acquisition source. Leave empty for all sources.'),
      '#maxlength' => 255,
    ];
    $form['budget'] = [
      '#type' => 'number',
      '#title' => $this->t('Budget (USD, optional)'),
      '#min' => 0,
      '#step' => '0.01',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create campaign'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function keyExists(string $value): bool {
    return (bool) $this->database->select('famtastic_campaign', 'c')
      ->condition('campaign_key', $value)
      ->countQuery()->execute()->fetchField();
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $budget = trim((string) $form_state->getValue('budget'));
    if ($budget !== '' && (!is_numeric($budget) || (float) $budget < 0)) {
      $form_state->setErrorByName('budget', $this->t('Budget must be a non-negative number.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $now = $this->time->getRequestTime();
    $budget = trim((string) $form_state->getValue('budget'));
    $this->database->insert('famtastic_campaign')->fields([
      'campaign_key' => (string) $form_state->getValue('campaign_key'),
      'name' => trim((string) $form_state->getValue('name')),
      'status' => 'active',
      'channel' => (string) $form_state->getValue('channel'),
      'source_filter' => trim((string) $form_state->getValue('source_filter')),
      'budget_minor' => $budget !== '' ? (int) round(((float) $budget) * 100) : 0,
      'currency' => 'usd',
      'created' => $now,
      'changed' => $now,
    ])->execute();
    $this->messenger()->addStatus($this->t('Campaign @key created.', ['@key' => $form_state->getValue('campaign_key')]));
    $form_state->setRedirect('famtastic_pipeline.campaign_operations');
  }

}
