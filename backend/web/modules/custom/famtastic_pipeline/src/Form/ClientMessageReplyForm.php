<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\ClientMessagingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** A real staff reply form, with history and an exact recipient. */
final class ClientMessageReplyForm extends FormBase {

  public function __construct(private readonly ClientMessagingService $messages) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.client_messages'));
  }

  public function getFormId(): string { return 'famtastic_client_message_reply'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $thread = NULL): array {
    try { $detail = $this->messages->detail($this->currentUser(), (string) $thread); }
    catch (\RuntimeException) { throw new NotFoundHttpException('Conversation not found.'); }
    $record = $detail['thread'];
    $form_state->set('thread_public_id', $record['public_id']);
    if (!$form_state->get('client_message_id')) $form_state->set('client_message_id', bin2hex(random_bytes(20)));
    $escape = static fn(mixed $value): string => Html::escape((string) $value);
    $form['#attached']['library'][] = 'famtastic_pipeline/client_messages';
    $form['#attributes']['class'][] = 'famtastic-inbox';
    $form['#cache']['max-age'] = 0;
    $form['back'] = ['#type' => 'link', '#title' => $this->t('← All messages'), '#url' => Url::fromRoute('famtastic_pipeline.client_messages_admin')];
    $form['heading'] = ['#markup' => '<div class="famtastic-inbox__heading"><span>' . ($record['source'] === 'contact_form' ? 'Contact form' : 'Customer portal') . '</span><h2>' . $escape($record['subject']) . '</h2><p>' . $escape($record['customer_name']) . ' · ' . $escape($record['customer_email']) . '</p><strong>' . ($record['status'] === 'closed' ? 'Closed' : ($record['needs_reply'] ? 'Needs your reply' : 'Waiting for client')) . '</strong></div>'];
    $context = [];
    foreach (['admin_intake_url' => 'Original contact request', 'admin_request_url' => 'Project and proofs'] as $key => $label) {
      if (!empty($record['context'][$key])) $context[] = '<a href="' . $escape($record['context'][$key]) . '">' . $escape($label) . '</a>';
    }
    if ($context) $form['context'] = ['#markup' => '<nav class="famtastic-inbox__context">' . implode(' · ', $context) . '</nav>'];
    $history = '';
    foreach ($detail['messages'] as $message) {
      $label = match ($message['delivery_status']) {
        'received' => 'Received in inbox', 'queued' => 'Email queued', 'sent' => 'Email accepted by mail service',
        'failed' => 'Email failed — saved in portal', default => 'Saved in portal',
      };
      $history .= '<article class="famtastic-inbox__message famtastic-inbox__message--' . $escape($message['author_type']) . '"><header><strong>' . ($message['author_type'] === 'staff' ? 'FAMtastic Concierge' : $escape($record['customer_name'] ?: 'Customer')) . '</strong><time>' . $escape(gmdate('M j, Y g:ia', $message['created']) . ' UTC') . '</time></header><p>' . nl2br($escape($message['body'])) . '</p><small>' . $escape($label) . '</small></article>';
    }
    $form['history'] = ['#markup' => '<section class="famtastic-inbox__history" aria-label="Conversation history">' . $history . '</section>'];
    $form['body'] = ['#type' => 'textarea', '#title' => $this->t('Reply to @name', ['@name' => $record['customer_name'] ?: $record['customer_email']]), '#required' => TRUE, '#rows' => 5, '#maxlength' => 20000,
      '#description' => $this->t('Your reply is saved in the conversation and an email is queued to @email. The email status appears above.', ['@email' => $record['customer_email']])];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = ['#type' => 'submit', '#value' => $this->t('Send reply'), '#button_type' => 'primary'];
    if (!$form_state->isSubmitted() && $record['last_message_id']) $this->messages->markRead($this->currentUser(), $record['public_id'], $record['last_message_id']);
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $body = trim(strip_tags((string) $form_state->getValue('body')));
    if ($body === '' || mb_strlen($body) > 20000) $form_state->setErrorByName('body', $this->t('Enter a message of 1–20,000 characters.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->messages->reply($this->currentUser(), (string) $form_state->get('thread_public_id'), (string) $form_state->getValue('body'), (string) $form_state->get('client_message_id'));
      $this->messenger()->addStatus($this->t('Reply saved in the conversation. Customer email queued; its delivery status is shown with the message.'));
      $form_state->setRedirect('famtastic_pipeline.client_messages_admin_thread', ['thread' => $form_state->get('thread_public_id')]);
    }
    catch (\RuntimeException | \InvalidArgumentException $error) {
      $this->messenger()->addError($this->t('Reply could not be saved: @reason', ['@reason' => $error->getMessage()]));
      $form_state->setRebuild();
    }
  }

}
