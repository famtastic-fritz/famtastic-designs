<?php
/** Run only inside disposable e2e-client-messaging SQLite/memory transport site. */
use Drupal\user\Entity\User;
if (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory') throw new RuntimeException('Requires disposable memory transport.');
$db = \Drupal::database();
$staff = User::load(1);
$thread = $db->select('famtastic_portal_thread', 't')->fields('t', ['public_id'])->range(0, 1)->execute()->fetchField();
if (!$thread) throw new RuntimeException('Seed a conversation first.');
$drafts = \Drupal::service('famtastic_pipeline.communication_drafts');
$messages = \Drupal::service('famtastic_pipeline.client_messages');
$count = static fn(): int => (int) $db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField();
$assert = static function (bool $value, string $label): void { if (!$value) throw new RuntimeException($label); print "PASS $label\n"; };
$before = $count();
$source = $drafts->source($staff, $thread);
$existing = $drafts->load($staff, $thread);
$draft = $drafts->save($staff, $thread, 'Hello <script>alert(1)</script> — Shay-Shay', 'reply', (int) $existing['revision'], $source['digest']);
$assert($drafts->load($staff, $thread)['body'] === $draft['body'], 'persistent reload');
$assert($count() === $before, 'save leaves outbox unchanged');
try { $drafts->save($staff, $thread, 'conflict', 'reply', (int) $existing['revision'], $source['digest']); throw new LogicException('Stale revision accepted'); } catch (RuntimeException $e) { $assert(TRUE, 'stale revision rejected'); }
try { $drafts->save($staff, $thread, 'conflict', 'reply', (int) $draft['revision'], str_repeat('0', 64)); throw new LogicException('Stale source accepted'); } catch (RuntimeException $e) { $assert(TRUE, 'stale source rejected'); }
$preview = \Drupal::service('famtastic_pipeline.mailer')->preview('Preview', '<img src=x onerror=alert(1)>');
$assert(!str_contains($preview['html'], '<img src=x'), 'customer HTML escaped');
$assert($count() === $before, 'preview leaves outbox unchanged');
$review = $drafts->reviewDigest($draft, $source);
$result = $drafts->send($staff, $thread, $draft['revision'], $review);
$assert($count() === $before + 1, 'reviewed send queues exactly one');
$retry = $drafts->send($staff, $thread, $draft['revision'], $review);
$assert($retry['message_id'] === $result['message_id'] && $count() === $before + 1, 'review replay reuses existing message');
$messages->label($staff, $thread, 'test');
$active = array_column($messages->inbox($staff)['threads'], 'public_id');
$assert(!in_array($thread, $active, TRUE), 'explicit test excluded from customer backlog');
$messages->label($staff, $thread, 'active');
$assert(in_array($thread, array_column($messages->inbox($staff)['threads'], 'public_id'), TRUE), 'restore preserves conversation');
