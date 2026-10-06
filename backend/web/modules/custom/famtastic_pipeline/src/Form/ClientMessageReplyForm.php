<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\CommunicationDraftService;
use Drupal\famtastic_pipeline\Service\StaffAiTaskService;
use Drupal\famtastic_pipeline\Service\OutreachMailer;
use Drupal\famtastic_pipeline\Service\ClientMessagingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** A real staff reply form, with history and an exact recipient. */
final class ClientMessageReplyForm extends FormBase {

  public function __construct(protected ClientMessagingService $messages, protected CommunicationDraftService $drafts, protected StaffAiTaskService $ai, protected OutreachMailer $mailer) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.client_messages'), $container->get('famtastic_pipeline.communication_drafts'), $container->get('famtastic_pipeline.staff_ai_tasks'), $container->get('famtastic_pipeline.mailer'));
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
    $source = $this->drafts->source($this->currentUser(), $record['public_id']);
    $draft = $this->drafts->load($this->currentUser(), $record['public_id']);
    if (!$form_state->has('draft_revision')) {
      $form_state->set('draft_revision', (int) $draft['revision']);
      $form_state->set('source_digest', $source['digest']);
    }
    $form['staff_label'] = ['#type' => 'select', '#title' => $this->t('Staff record group'), '#options' => ['active' => 'Customer work', 'test' => 'Explicit test', 'archived' => 'Archived'], '#default_value' => $record['staff_label']];
    $form['purpose'] = ['#type' => 'select', '#title' => $this->t('Message purpose'), '#options' => CommunicationDraftService::recipes(), '#default_value' => $draft['purpose']];
    $form['template_note'] = ['#markup' => '<p>All manual messages use customer_message_reply/v2. Proof-ready, staging and account notices are sent only by their approved project steps.</p>'];
    $form['body'] = ['#type' => 'textarea', '#title' => $this->t('Reply to @name', ['@name' => $record['customer_name'] ?: $record['customer_email']]), '#default_value' => $form_state->get('working_body') ?? $draft['body'], '#rows' => 8, '#maxlength' => 20000,
      '#description' => $this->t('Save a draft, preview it, then review the exact message to @email. Saving and AI assistance never send email.', ['@email' => $record['customer_email']])];
    $ready = $this->ai->readiness('reply');
    $form['ai_help'] = ['#markup' => '<p>' . $escape($ready['message']) . (!empty($ready['provider']) ? ' Provider: ' . $escape($ready['provider']) . '; model: ' . $escape($ready['model']) . '.' : '') . '</p>'];
    if ($summary = $form_state->get('ai_summary')) $form['summary'] = ['#type' => 'details', '#title' => $this->t('AI summary — check against the conversation'), '#open' => TRUE, 'text' => ['#plain_text' => $summary]];
    if ($preview = $form_state->get('preview')) {
      $form['preview'] = ['#type' => 'details', '#title' => $this->t('Review message for @email', ['@email' => $record['customer_email']]), '#open' => TRUE];
      // Sandboxed srcdoc keeps the exact approved email renderer isolated from admin CSS.
      $form['preview']['html'] = ['#type' => 'inline_template', '#template' => '<iframe title="Branded email preview" sandbox="" referrerpolicy="no-referrer" style="width:100%;height:560px;border:1px solid #888" srcdoc="{{ html }}"></iframe>', '#context' => ['html' => $preview['html']]];
      $form['preview']['text'] = ['#type' => 'details', '#title' => $this->t('Plain text'), 'body' => ['#plain_text' => $preview['text']]];
      $form['confirm_recipient'] = ['#type' => 'checkbox', '#title' => $this->t('I reviewed this exact draft and authorize sending it to @email.', ['@email' => $record['customer_email']])];
    }
    $form['actions'] = ['#type' => 'actions'];
    foreach (['label' => 'Save record group', 'recipe' => 'Use purpose template', 'save' => 'Save draft', 'preview' => 'Preview and review', 'ai_reply' => 'Ask AI for a reply draft', 'ai_summary' => 'Summarize conversation', 'reject_ai' => 'Discard AI suggestion'] as $op => $title) {
      $form['actions'][$op] = ['#type' => 'submit', '#name' => $op, '#value' => $this->t($title)];
      if ($op === 'ai_reply') $form['actions'][$op]['#disabled'] = !$ready['ready'];
      if ($op === 'ai_summary') $form['actions'][$op]['#disabled'] = !$this->ai->readiness('summarize')['ready'];
    }
    if ($form_state->get('review_digest')) $form['actions']['send'] = ['#type' => 'submit', '#name' => 'send', '#value' => $this->t('Send reviewed reply'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#name'] ?? '') === 'send' && !$form_state->getValue('confirm_recipient')) $form_state->setErrorByName('confirm_recipient', $this->t('Review and confirm the exact recipient before sending.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $thread = (string) $form_state->get('thread_public_id');
    $op = $form_state->getTriggeringElement()['#name'] ?? 'save';
    $body = (string) $form_state->getValue('body');
    try {
      $source = $this->drafts->source($this->currentUser(), $thread);
      if ($op === 'send') {
        $draft = $this->drafts->load($this->currentUser(), $thread);
        if ($body !== $draft['body']) throw new \RuntimeException('Your text changed after preview. Preview and review it again.');
        $this->drafts->send($this->currentUser(), $thread, (int) $form_state->get('draft_revision'), (string) $form_state->get('review_digest'));
        $this->messenger()->addStatus($this->t('Reply queued. Delivery status is shown in the conversation.'));
        $form_state->setRedirect('famtastic_pipeline.client_messages_admin_thread', ['thread' => $thread]);
        return;
      }
      $form_state->set('preview', NULL)->set('review_digest', NULL);
      if ($op === 'label') { $this->messages->label($this->currentUser(), $thread, (string) $form_state->getValue('staff_label')); $this->messenger()->addStatus($this->t('Record group saved. This is reversible; nothing was deleted.')); }
      elseif ($op === 'recipe') $body = CommunicationDraftService::recipe((string) $form_state->getValue('purpose'), $source['detail']['thread']['customer_name'], $body);
      elseif ($op === 'reject_ai') $body = $this->drafts->load($this->currentUser(), $thread)['body'];
      elseif (in_array($op, ['ai_reply', 'ai_summary'], TRUE)) {
        $data = ['thread' => $thread, 'subject' => $source['source']['subject'], 'messages' => array_map(static fn(array $m): array => ['id' => $m['id'], 'author_type' => $m['author_type'], 'body' => mb_substr($m['body'], 0, 3000)], array_slice($source['source']['messages'], -8))];
        $result = $this->ai->generate($this->currentUser(), $op === 'ai_reply' ? 'reply' : 'summarize', [['id' => $thread, 'digest' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'data' => $data]]);
        if ($op === 'ai_reply') $body = $result['text'];
        else $form_state->set('ai_summary', $result['text']);
        $this->messenger()->addStatus($this->t('AI suggestion only. Provider @provider, model @model, receipt @receipt. Check the facts, then edit or discard. Cost is unknown.', ['@provider' => $result['provider'], '@model' => $result['model'], '@receipt' => $result['receipt_id']]));
      }
      else {
        $draft = $this->drafts->save($this->currentUser(), $thread, $body, (string) $form_state->getValue('purpose'), (int) $form_state->get('draft_revision'), (string) $form_state->get('source_digest'));
        $form_state->set('draft_revision', $draft['revision']);
        $body = $draft['body'];
        $this->messenger()->addStatus($this->t('Draft saved. Nothing was sent.'));
        if ($op === 'preview') {
          $form_state->set('preview', $this->mailer->preview('FAMtastic Concierge — ' . $source['detail']['thread']['subject'], $this->messages->staffEmailBody($source['detail']['thread']['subject'], $draft['body'], $thread)));
          $form_state->set('review_digest', $this->drafts->reviewDigest($draft, $source));
        }
      }
      $form_state->set('working_body', $body);
      $input = $form_state->getUserInput(); $input['body'] = $body; unset($input['confirm_recipient']); $form_state->setUserInput($input);
    }
    catch (\Drupal\Core\Database\DatabaseException | \PDOException $error) { $this->messenger()->addError($this->t('The draft could not be saved. Your text is still here. Try again or reload your saved draft.')); }
    catch (\RuntimeException | \InvalidArgumentException $error) { $this->messenger()->addError($error->getMessage()); }
    $form_state->setRebuild();
  }

}
