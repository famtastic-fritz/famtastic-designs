<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

final class PipelineSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'famtastic_pipeline_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['famtastic_pipeline.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('famtastic_pipeline.settings');
    $form['notification_to_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Fritz notification email'),
      '#required' => TRUE,
      '#default_value' => $config->get('notification_to_email') ?: 'fritz.medine@gmail.com',
      '#description' => $this->t('New leads, customer portal messages, and SLA alerts are sent here.'),
    ];
    $form['lead_response_sla_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Lead response deadline (days)'),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 30,
      '#default_value' => $config->get('lead_response_sla_days') ?: 3,
    ];
    $form['sla_alerts_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Alert Fritz when a lead misses its response deadline'),
      '#default_value' => $config->get('sla_alerts_enabled') ?? TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('famtastic_pipeline.settings')
      ->set('notification_to_email', mb_strtolower(trim((string) $form_state->getValue('notification_to_email'))))
      ->set('lead_response_sla_days', (int) $form_state->getValue('lead_response_sla_days'))
      ->set('sla_alerts_enabled', (bool) $form_state->getValue('sla_alerts_enabled'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
