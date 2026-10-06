<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\famtastic_pipeline\Service\CampaignWorkspace;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Draft planning only. Form API supplies CSRF and route permissions protect writes. */
final class CampaignAddForm extends FormBase {
  public function __construct(protected CampaignWorkspace $workspace) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('famtastic_pipeline.campaign_workspace')); }
  public function getFormId(): string { return 'famtastic_campaign_add'; }

  public function buildForm(array $form, FormStateInterface $form_state, string $campaign_key = '', string $action = 'create'): array {
    if (!in_array($action, ['create', 'edit', 'duplicate', 'archive', 'restore'], TRUE)) throw new NotFoundHttpException();
    $campaign = $campaign_key !== '' ? $this->workspace->get($campaign_key) : NULL;
    if ($campaign_key !== '' && !$campaign) throw new NotFoundHttpException();
    if ($action !== 'create' && !$campaign) throw new NotFoundHttpException();
    $form_state->set('campaign_key', $campaign_key);
    $form_state->set('action', $action);
    // The first displayed revision remains fixed across validation/rebuilds.
    if ($form_state->get('revision') === NULL) $form_state->set('revision', (int) ($campaign['revision'] ?? 0));
    $plan = $campaign['plan'] ?? [];
    $form['#attached']['library'][] = 'famtastic_pipeline/campaign_workspace';
    $form['#attributes']['class'][] = 'famtastic-campaign-form';
    $form['help'] = ['#markup' => '<p>Save a plan, then prepare and review each item. Saving never sends email or publishes a post. File-backed schedules and delivery history remain separate evidence.</p>'];
    if (in_array($action, ['archive', 'restore'], TRUE)) {
      if (empty($campaign['id'])) throw new NotFoundHttpException('Save this source campaign as a Drupal plan first.');
      $form['confirmation'] = ['#type' => 'checkbox', '#title' => $action === 'archive' ? $this->t('Archive @name from current planning. Existing provider schedules are not cancelled.', ['@name' => $campaign['name']]) : $this->t('Restore @name as a draft. Existing history is retained.', ['@name' => $campaign['name']]), '#required' => TRUE];
    }
    else {
      $form['name'] = ['#type' => 'textfield', '#title' => $this->t('Campaign name'), '#required' => TRUE, '#maxlength' => 255, '#default_value' => ($campaign['name'] ?? '') . ($action === 'duplicate' ? ' copy' : '')];
      $form['campaign_key'] = ['#type' => 'textfield', '#title' => $this->t('Campaign key'), '#description' => $this->t('Stable identifier: lowercase letters, numbers and single dashes. It cannot change after saving.'), '#required' => TRUE, '#maxlength' => 128, '#default_value' => $action === 'duplicate' ? '' : $campaign_key, '#disabled' => $action === 'edit'];
      foreach (['goal' => 'What should this campaign achieve?', 'audience' => 'Who is it for?', 'offer' => 'What are you offering?', 'evidence' => 'Facts and evidence supporting the offer', 'cta' => 'What should people do next?'] as $key => $label) {
        $form[$key] = ['#type' => 'textarea', '#title' => $this->t($label), '#rows' => 2, '#default_value' => $plan[$key] ?? ''];
      }
      $form['channels'] = ['#type' => 'checkboxes', '#title' => $this->t('Planning channels'), '#description' => $this->t('Selecting a channel does not connect or publish to it. Check Channel health before scheduling.'), '#options' => array_combine(CampaignWorkspace::CHANNELS, array_map('ucfirst', CampaignWorkspace::CHANNELS)), '#default_value' => $plan['channels'] ?? []];
      foreach (['start_date' => 'Start date', 'end_date' => 'End date'] as $key => $label) $form[$key] = ['#type' => 'date', '#title' => $this->t($label), '#default_value' => $plan[$key] ?? ''];
      $form['content_plan'] = ['#type' => 'textarea', '#title' => $this->t('Content plan'), '#description' => $this->t('One idea per line. Include its intended date and channel in the text. These are unscheduled planning notes; existing CLI schedules are shown separately.'), '#rows' => 8, '#default_value' => $plan['content_plan'] ?? ''];
      $form['content_items'] = ['#type' => 'table', '#header' => ['Date', 'Channel', 'Draft content'], '#tree' => TRUE];
      $count = $form_state->get('item_count') ?? max(3, count($plan['content_items'] ?? []) + 1);
      for ($i = 0; $i < $count; $i++) {
        $item = $plan['content_items'][$i] ?? [];
        $form['content_items'][$i]['date'] = ['#type' => 'date', '#title' => 'Content date', '#title_display' => 'invisible', '#default_value' => $item['date'] ?? ''];
        $form['content_items'][$i]['channel'] = ['#type' => 'select', '#title' => 'Content channel', '#title_display' => 'invisible', '#options' => ['' => 'Choose channel'] + array_combine(CampaignWorkspace::CHANNELS, array_map('ucfirst', CampaignWorkspace::CHANNELS)), '#default_value' => $item['channel'] ?? ''];
        $form['content_items'][$i]['copy'] = ['#type' => 'textarea', '#title' => 'Draft content', '#title_display' => 'invisible', '#rows' => 3, '#default_value' => $item['copy'] ?? ''];
      }
      if ($action === 'edit' && !empty($campaign['id'])) {
        $readiness = \Drupal::service('famtastic_pipeline.staff_ai_tasks')->readiness('campaign');
        $form['ai_help'] = ['#plain_text' => $readiness['ready'] ? 'AI uses the last saved campaign brief. Save your changes first; generated copy remains an editable draft.' : $readiness['message']];
        $form['generate'] = ['#type' => 'submit', '#value' => $this->t('Draft campaign ideas with AI'), '#submit' => ['::generateIdeas'], '#limit_validation_errors' => [], '#disabled' => !$readiness['ready']];
        $form['ai_setup'] = ['#type' => 'link', '#title' => $this->t('AI drafting setup'), '#url' => \Drupal\Core\Url::fromRoute('famtastic_pipeline.staff_ai_settings')];
      }
      $form['more'] = ['#type' => 'submit', '#value' => $this->t('Add content row'), '#submit' => ['::addRow'], '#limit_validation_errors' => []];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => match ($action) { 'archive' => $this->t('Archive campaign'), 'restore' => $this->t('Restore as draft'), default => $this->t('Save draft plan') }, '#button_type' => 'primary'];
    return $form;
  }

  public function generateIdeas(array &$form, FormStateInterface $form_state): void {
    $campaign = $this->workspace->get((string) $form_state->get('campaign_key'));
    if (!$campaign || empty($campaign['id']) || $campaign['status'] === 'archived') throw new NotFoundHttpException();
    $data = ['name' => $campaign['name'], 'campaign_key' => $campaign['campaign_key'], 'revision' => $campaign['revision'], 'plan' => $campaign['plan']];
    try {
      $result = \Drupal::service('famtastic_pipeline.staff_ai_tasks')->generate($this->currentUser(), 'campaign', [['id' => 'campaign:' . $campaign['id'], 'digest' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'data' => $data]]);
      $input = $form_state->getUserInput();
      $input['content_plan'] = $result['text'];
      $form_state->setUserInput($input);
      $form_state->setValue('content_plan', $result['text']);
      $this->messenger()->addStatus($this->t('AI draft prepared with @provider / @model. Receipt @receipt. Review and save it; nothing was sent.', ['@provider' => $result['provider'], '@model' => $result['model'], '@receipt' => $result['receipt_id']]));
    }
    catch (\Throwable $e) { $this->messenger()->addError('AI could not prepare the draft. Your saved campaign and unsaved edits are unchanged. Check AI drafting setup and try again.'); }
    $form_state->setRebuild();
  }

  public function addRow(array &$form, FormStateInterface $form_state): void {
    $form_state->set('item_count', count(array_filter(array_keys($form['content_items']), 'is_int')) + 1);
    $form_state->setRebuild();
  }

  private function data(FormStateInterface $state): array {
    $plan = [];
    foreach (['goal', 'audience', 'offer', 'evidence', 'cta', 'start_date', 'end_date', 'content_plan'] as $key) $plan[$key] = trim((string) $state->getValue($key));
    $plan['channels'] = array_values(array_filter((array) $state->getValue('channels')));
    $plan['content_items'] = array_values(array_filter((array) $state->getValue('content_items'), static fn(array $item): bool => trim((string) ($item['copy'] ?? '')) !== ''));
    return ['campaign_key' => $state->get('action') === 'edit' ? $state->get('campaign_key') : trim((string) $state->getValue('campaign_key')), 'name' => trim((string) $state->getValue('name')), 'plan' => $plan];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (in_array($form_state->get('action'), ['archive', 'restore'], TRUE)) return;
    $data = $this->data($form_state);
    foreach (CampaignWorkspace::validate($data) as $field => $error) $form_state->setErrorByName($field, $error);
    if ($form_state->get('action') !== 'edit' && $this->workspace->get($data['campaign_key'])) $form_state->setErrorByName('campaign_key', $this->t('This key already exists. Choose a new key for the draft.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = $form_state->get('action');
    $key = (string) $form_state->get('campaign_key');
    try {
      if (in_array($action, ['archive', 'restore'], TRUE)) $this->workspace->changeStatus($key, $form_state->get('revision'), $action === 'archive');
      else {
        $existing = $this->workspace->get($key);
        $key = $this->workspace->save($this->data($form_state), $action === 'edit' && !empty($existing['id']) ? $form_state->get('revision') : NULL);
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e instanceof \UnexpectedValueException ? $e->getMessage() : 'The campaign could not be saved. The key may already exist. Your text is still shown.');
      $form_state->setRebuild();
      return;
    }
    $this->messenger()->addStatus($this->t('Campaign saved. No email or post was sent.'));
    $form_state->setRedirect('famtastic_pipeline.marketing', [], ['query' => ['campaign' => $key]]);
  }
}
