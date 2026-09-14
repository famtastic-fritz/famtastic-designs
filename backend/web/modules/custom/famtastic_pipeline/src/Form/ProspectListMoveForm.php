<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\ProspectListService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Native CSRF-protected staff controls for completed/archive/restore. */
final class ProspectListMoveForm extends ConfirmFormBase {

  private ?Prospect $prospect = NULL;
  private string $target = 'active';

  public function __construct(private readonly ProspectListService $lists, private readonly CsrfTokenGenerator $tokens) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.prospect_lists'), $container->get('csrf_token'));
  }

  public function getFormId(): string { return 'famtastic_prospect_list_move'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?Prospect $famtastic_prospect = NULL, string $list_state = 'active'): array {
    if (!$famtastic_prospect || !isset(ProspectListService::LABELS[$list_state])) throw new \InvalidArgumentException('Invalid prospect list.');
    $this->prospect = $famtastic_prospect;
    $this->target = $list_state;
    // Drupal intentionally does not cache form state on GET. Sign the observed
    // list, bound to this session, record and target, and validate it on POST.
    $original = ProspectListService::state($famtastic_prospect->get('staff_list_state')->value);
    $form['original_list'] = ['#type' => 'hidden', '#default_value' => $original];
    $form['original_list_token'] = ['#type' => 'hidden', '#default_value' => $this->tokens->get($this->snapshotKey($original))];
    $form['record'] = ['#plain_text' => $famtastic_prospect->label() . ' · Prospect #' . $famtastic_prospect->id()];
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion() {
    return $this->t('Move this prospect to @list?', ['@list' => ProspectListService::LABELS[$this->target]]);
  }

  public function getDescription() {
    return $this->t('Keep the complete lead history in the @list list. You can move it back to Active at any time. The customer project keeps its current stage.', ['@list' => ProspectListService::LABELS[$this->target]]);
  }

  public function getConfirmText() { return $this->t('Move to @list', ['@list' => ProspectListService::LABELS[$this->target]]); }

  public function getCancelUrl(): Url {
    return Url::fromRoute('famtastic_pipeline.prospect_workspace', ['famtastic_prospect' => $this->prospect?->id()]);
  }

  private function snapshotKey(string $original): string {
    return 'prospect-list:' . $this->prospect->id() . ':' . $this->target . ':' . $original;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $original = $form_state->getValue('original_list');
    $token = $form_state->getValue('original_list_token');
    if (!is_string($original) || !isset(ProspectListService::LABELS[$original]) || !is_string($token) || !$this->tokens->validate($token, $this->snapshotKey($original))) {
      $form_state->setErrorByName('original_list', $this->t('The prospect list confirmation expired or changed. Reload the page and try again.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->lists->move((int) $this->prospect->id(), $this->target, (string) $form_state->getValue('original_list'), $this->currentUser());
      $this->messenger()->addStatus($this->t('Prospect moved to @list.', ['@list' => ProspectListService::LABELS[$this->target]]));
      $form_state->setRedirect('famtastic_pipeline.operations_metric', ['metric' => 'prospects'], ['query' => ['list' => $this->target]]);
    }
    catch (\RuntimeException $error) {
      $this->messenger()->addError($error->getMessage());
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
  }

}
