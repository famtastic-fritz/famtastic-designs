<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\famtastic_pipeline\Service\ClientMessagingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Session-backed messaging API and the staff inbox over the same records. */
final class ClientMessagesController extends ControllerBase {

  public function __construct(private readonly ClientMessagingService $messages, private readonly AccountProxyInterface $account) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('famtastic_pipeline.client_messages'), $container->get('current_user'));
  }

  public function inbox(Request $request): JsonResponse {
    return $this->respond(fn(): array => $this->messages->inbox($this->account, $this->filters($request)));
  }

  public function detail(string $thread): JsonResponse {
    return $this->respond(fn(): array => $this->messages->detail($this->account, $thread));
  }

  public function reply(Request $request, string $thread): JsonResponse {
    return $this->respond(function () use ($request, $thread): array {
      $data = $this->body($request);
      return $this->messages->reply($this->account, $thread, (string) ($data['body'] ?? ''), (string) ($data['client_message_id'] ?? ''));
    });
  }

  public function read(Request $request, string $thread): JsonResponse {
    return $this->respond(function () use ($request, $thread): array {
      $data = $this->body($request);
      $this->messages->markRead($this->account, $thread, (int) ($data['last_message_id'] ?? 0));
      return ['ok' => TRUE];
    });
  }

  private function body(Request $request): array {
    if (strlen($request->getContent()) > 90000) throw new \InvalidArgumentException('This message is too long.');
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) throw new \InvalidArgumentException('A JSON message is required.');
    foreach (['body', 'client_message_id', 'last_message_id'] as $key) {
      if (isset($data[$key]) && !is_scalar($data[$key])) throw new \InvalidArgumentException('Invalid message field.');
    }
    return $data;
  }

  private function respond(callable $operation): JsonResponse {
    try { $response = new JsonResponse($operation()); }
    catch (\UnexpectedValueException $error) {
      $code = $error->getMessage();
      $response = new JsonResponse(['ok' => FALSE, 'error' => $code, 'message' => $code === 'verification_required' ? 'Verify your email to open private messages.' : 'Sign in to continue.'], $code === 'authentication_required' ? 401 : 403);
    }
    catch (\InvalidArgumentException $error) {
      $response = new JsonResponse(['ok' => FALSE, 'error' => 'invalid_message', 'message' => $error->getMessage()], 422);
    }
    catch (\RuntimeException $error) {
      $notFound = $error->getMessage() === 'Conversation not found.';
      $response = new JsonResponse(['ok' => FALSE, 'error' => 'conversation_unavailable', 'message' => $notFound ? 'Conversation not found.' : 'Messages are temporarily unavailable. Please retry.'], $notFound ? 404 : 503);
    }
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }

  public function admin(Request $request): array {
    $filters = $this->filters($request);
    $inbox = $this->messages->inbox($this->account, $filters);
    $escape = static fn(mixed $value): string => Html::escape((string) $value);
    $choices = ['' => 'All conversations', 'needs_reply' => 'Needs your reply', 'unread' => 'Unread', 'waiting' => 'Waiting for client'];
    $options = '';
    foreach ($choices as $value => $label) $options .= '<option value="' . $escape($value) . '"' . ($value === $filters['status'] ? ' selected' : '') . '>' . $escape($label) . '</option>';
    $rows = [];
    foreach ($inbox['threads'] as $thread) {
      $url = Url::fromRoute('famtastic_pipeline.client_messages_admin_thread', ['thread' => $thread['public_id']])->toString();
      $status = $thread['needs_reply'] ? 'Needs your reply' : 'Waiting for client';
      if ($thread['status'] === 'closed') $status = 'Closed';
      $rows[] = ['#markup' => '<a class="famtastic-inbox__row" href="' . $escape($url) . '"><div><strong>' . $escape($thread['customer_name'] ?: $thread['customer_email']) . '</strong><span>' . $escape($thread['subject']) . '</span><p>' . $escape($thread['last_message_preview']) . '</p></div><div><b>' . $escape($status) . '</b>' . ($thread['unread_count'] ? '<span class="famtastic-inbox__unread">' . (int) $thread['unread_count'] . ' unread</span>' : '') . '<span>' . ($thread['source'] === 'contact_form' ? 'Contact form' : 'Portal') . '</span><time>' . $escape(gmdate('M j, Y g:ia', $thread['last_message_at']) . ' UTC') . '</time></div></a>'];
    }
    return [
      '#attached' => ['library' => ['famtastic_pipeline/client_messages']], '#cache' => ['max-age' => 0],
      '#prefix' => '<div class="famtastic-inbox">', '#suffix' => '</div>',
      'heading' => ['#markup' => '<div class="famtastic-inbox__heading"><span>FAMtastic Concierge</span><h2>Messages</h2><p><strong>' . $inbox['needs_reply_count'] . ' need your reply</strong> · ' . $inbox['unread_count'] . ' unread messages</p><p>Contact forms and customer conversations stay together here and in your portal.</p></div>'],
      'filters' => ['#type' => 'inline_template', '#template' => '<form class="famtastic-inbox__filters" method="get"><label>Find a conversation<input type="search" name="q" value="{{ query }}" placeholder="Customer, email, or message"></label><label>Show<select name="status">{{ options|raw }}</select></label><button type="submit">Filter messages</button></form>', '#context' => ['query' => $filters['q'], 'options' => $options]],
      'rows' => $rows ?: ['#markup' => '<p>No conversations match these filters.</p>'],
    ];
  }

  /** Invalid array-shaped filter input never reaches database/render methods. */
  private function filters(Request $request): array {
    $query = $request->query->all();
    $search = is_scalar($query['q'] ?? '') ? mb_substr(trim((string) ($query['q'] ?? '')), 0, 200) : '';
    $status = is_string($query['status'] ?? NULL) ? $query['status'] : '';
    return ['q' => $search, 'status' => in_array($status, ['unread', 'needs_reply', 'waiting'], TRUE) ? $status : ''];
  }

}
