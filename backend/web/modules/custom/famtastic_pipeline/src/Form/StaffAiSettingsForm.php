<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\StaffAiTaskService;

/** Explicit task enrollment, separate from provider configuration. */
final class StaffAiSettingsForm extends ConfigFormBase {
  public function getFormId(): string { return 'famtastic_staff_ai_settings'; }
  protected function getEditableConfigNames(): array { return ['famtastic_pipeline.staff_ai']; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('famtastic_pipeline.staff_ai');
    $form['explanation'] = ['#markup' => '<p>A default chat model chooses which configured provider handles your AI buttons. Enable only the tasks you want. AI produces suggestions you review; it cannot send email, publish a campaign or approve a project. Calls may have provider costs. Start with a low hourly limit.</p>'];
    $form['provider'] = ['#type' => 'link', '#title' => $this->t('Open AI provider settings'), '#url' => Url::fromUserInput('/admin/config/ai')];
    $form['enabled'] = ['#type' => 'fieldset', '#title' => $this->t('Allow these staff tasks'), '#tree' => TRUE];
    $labels = ['reply' => 'Draft a customer reply', 'summarize' => 'Summarize a conversation', 'campaign' => 'Suggest a campaign plan', 'needs_me' => 'Summarize work needing attention'];
    foreach (StaffAiTaskService::TASKS as $task) $form['enabled'][$task] = ['#type' => 'checkbox', '#title' => $this->t($labels[$task]), '#default_value' => (bool) $config->get('enabled.' . $task)];
    $form['hourly_limit'] = ['#type' => 'number', '#title' => $this->t('Maximum AI requests per staff member per hour'), '#min' => 1, '#max' => 20, '#required' => TRUE, '#default_value' => $config->get('hourly_limit') ?: 5];
    return parent::buildForm($form, $form_state);
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('famtastic_pipeline.staff_ai')->set('enabled', array_map('boolval', $form_state->getValue('enabled')))->set('hourly_limit', (int) $form_state->getValue('hourly_limit'))->save();
    parent::submitForm($form, $form_state);
  }
}
