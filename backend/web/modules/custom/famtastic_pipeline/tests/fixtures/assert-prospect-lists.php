<?php

declare(strict_types=1);

/** Assert mutations only inside the disposable SQLite / memory-mail fixture. */
if (\Drupal::database()->driver() !== 'sqlite' || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory') {
  throw new \RuntimeException('This fixture requires isolated SQLite and memory mail.');
}
$db = \Drupal::database();
$state = json_decode(file_get_contents((string) getenv('FAMTASTIC_INBOX_STATE')), TRUE, flags: JSON_THROW_ON_ERROR);
$id = $state['prospect_id'];
$results = [];
$check = static function (string $name, bool $valid) use (&$results): void {
  $results[$name] = $valid;
  if (!$valid) throw new \RuntimeException($name);
};
$after = $db->select('famtastic_prospect', 'p')->fields('p')->condition('id', $id)->execute()->fetchAssoc();
$before = $state['prospect_before'];
foreach (['staff_list_state', 'staff_list_changed_at', 'staff_list_changed_by'] as $field) { unset($after[$field], $before[$field]); }
$check('all_original_prospect_fields_preserved', $before == $after);
$events = $db->select('famtastic_event', 'e')->fields('e')->condition('prospect_id', $id)->condition('event_type', 'prospect.list_changed')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
$check('five_real_moves_recorded_once', count($events) === 5);
$directions = [];
foreach ($events as $event) {
  $payload = json_decode($event['payload'], TRUE, flags: JSON_THROW_ON_ERROR);
  $check('audit_actor_' . $event['id'], $payload['actor_uid'] === $state['accounts']['staff']['uid'] && $event['provider'] === 'staff');
  $directions[] = $payload['from'] . ':' . $payload['to'];
}
$check('audit_preserves_complete_move_history', $directions === ['active:completed', 'completed:archived', 'archived:active', 'active:completed', 'completed:active']);
$service = \Drupal::service('famtastic_pipeline.prospect_lists');
$actor = \Drupal::entityTypeManager()->getStorage('user')->load($state['accounts']['staff']['uid']);
$check('repeat_move_is_idempotent', $service->move($id, 'active', 'active', $actor) === FALSE);
$count = (int) $db->select('famtastic_event', 'e')->condition('event_type', 'prospect.list_changed')->countQuery()->execute()->fetchField();
$check('idempotent_repeat_adds_no_event', $count === 5);
$customer = \Drupal::entityTypeManager()->getStorage('user')->load($state['accounts']['customer']['uid']);
try { $service->move($id, 'archived', 'active', $customer); $check('service_denies_customer', FALSE); }
catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) { $check('service_denies_customer', TRUE); }
$request = $db->select('famtastic_project_request', 'r')->fields('r', ['status', 'customer_archived_at', 'selected_proof_direction'])->condition('id', $state['request_id'])->execute()->fetchAssoc();
$check('request_status_selection_and_visibility_preserved', $request['status'] === 'submitted' && $request['customer_archived_at'] === NULL && empty($request['selected_proof_direction']));
$check('original_intake_still_exists', (bool) $db->select('famtastic_intake', 'i')->condition('id', $state['intake_id'])->countQuery()->execute()->fetchField());
$check('only_two_explicit_fixture_replies_queued_email', (int) $db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField() === $state['initial_outbox_count'] + 2);
print json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
