<?php
declare(strict_types=1);

// Owner-authorized, exact-account correction. Uses the deployed renderer/outbox;
// never rewrites the original send, changes selection, or drains other mail.
umask(0077);
$db = \Drupal::database();
$mode = getenv('FAMTASTIC_PROOF_LINK_MODE') ?: 'preflight';
if (!in_array($mode, ['preflight', 'send'], TRUE)) throw new RuntimeException('Invalid mode.');
$id = (int) (getenv('FAMTASTIC_PROOF_LINK_REQUEST') ?: 17);
$approved = [
  17 => [15, 56, 769, '4940a4fd-91af-40c4-b8a5-2b4dad1a3b95', '40b7e3101e75e42ced6bfc36b1fbabcc234572ebc6f7b8fb202c2bef088d938c', 'StockandShip98', 'Hey there,'],
  16 => [14, 57, 767, '8000fc68-aae3-4f40-a3de-2251bd09076a', 'c567dd203cd7b700c716da319cb69fdb600e5d936990ffb8c9a7158dd153ce7b', 'Class of 2000 reunion', 'Hi Class of 2000 family,'],
];
if (!isset($approved[$id])) throw new RuntimeException('Request not authorized for this correction.');
[$customerId, $campaignId, $originalId, $publicId, $originalHash, $projectName, $greeting] = $approved[$id];
$request = $db->select('famtastic_project_request', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc();
$customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('id', $customerId)->execute()->fetchAssoc();
$original = $db->select('famtastic_notification_outbox', 'n')->fields('n')->condition('id', $originalId)->execute()->fetchAssoc();
if (!$request || !$customer || !$original
  || (int) $request['customer_id'] !== $customerId || (int) $request['proof_campaign_id'] !== $campaignId
  || $request['public_id'] !== $publicId
  || !$customer['verified_at'] || strtolower($customer['email']) !== $original['recipient']
  || $original['status'] !== 'sent' || !$original['provider_message_id']
  || hash('sha256', $original['body']) !== $originalHash
  || !in_array($request['proof_review_status'], ['customer_ready', 'notified'], TRUE)
  || $request['selected_proof_direction'] !== '') throw new RuntimeException('Original receipt/account/review state changed; reconcile before sending.');
$url = 'https://famtasticdesigns.com/portal/?section=projects&request=' . $request['public_id'];
$body = preg_replace('~https?://[^\s]+~', '', $original['body']);
$body = str_replace($greeting, "$greeting\n\nQuick correction from me: the links in my last email did not give you the smooth sign-in experience you should have had. I'm sorry about that. Please use the Open your proof set button in this email instead. If you're already signed in, it opens your $projectName project. Otherwise, sign in with the email address receiving this message and we'll take you straight back to your three directions. If you need it, Forgot password? is on that same sign-in screen.", $body);
$body = str_replace('Sign in to your FAMtastic account first, then open your project to compare all three and choose your direction:', 'The button opens all three directions together, where you can compare them and save your choice:', $body);
$body = str_replace('You can also keep questions and ideas in your private project conversation with Fritz and me:', 'You can reply to this email, or use Messages in your portal to keep questions and ideas in your private project conversation with Fritz and me.', $body);
$body = str_replace('Sign in to your FAMtastic account first, then open your private project review to explore all three and save your choice:', 'The button opens all three directions together, where you can compare them and save your choice:', $body);
$body = str_replace("Here's that reference:", 'We can discuss that reference in your project conversation.', $body);
$body = str_replace('Your private project conversation keeps you connected with Fritz and me. Use it for questions, ideas and the details you want us to include:', 'Your private project conversation in Messages keeps you connected with Fritz and me. Use it for questions, ideas and the details you want us to include, or simply reply to this email.', $body);
$body = preg_replace('/\n{3,}/', "\n\n", trim($body));
$body .= "\n\nOpen your project:\n" . $url;
$subject = $id === 17 ? 'Corrected access: your StockandShip98 directions are ready' : 'Corrected access: your Class of 2000 reunion directions';
$key = "website-request:$id:proofs:$campaignId:portal-link-correction-v1";
$template = 'customer_proof_ready';
$version = 4;
$mailer = \Drupal::service('famtastic_pipeline.mailer');
$html = (new ReflectionMethod($mailer, 'renderHtmlMessage'))->invoke($mailer, $subject, $body, $template);
$doc = new DOMDocument();
@$doc->loadHTML($html);
$anchors = $doc->getElementsByTagName('a');
$visible = $doc->getElementsByTagName('body')->item(0)->textContent;
if ($anchors->length !== 1 || $anchors->item(0)->getAttribute('href') !== $url
  || !str_contains($anchors->item(0)->getAttribute('class'), 'cta')
  || preg_match('~https?://|/web/api/|\{\{|\}\}~i', $visible)
  || !str_contains($html, 'data-famtastic-email-brand="v1"')
  || !str_contains($body, "Always FAMtastic,\nShay")) throw new RuntimeException('Correction presentation failed.');
$directory = '/home/xrdj7j99xhzt/.config/famtastic/client-delivery/request' . $id . '-proof-navigation-correction';
if (!is_dir($directory) && !mkdir($directory, 0700, TRUE)) throw new RuntimeException('Private preview directory unavailable.');
file_put_contents($directory . '/email.html', $html);
file_put_contents($directory . '/email.txt', $body);
$receipt = ['request_id' => $id, 'customer_id' => $customerId, 'original_outbox_id' => $originalId, 'key' => $key,
  'template' => "$template/v$version", 'recipient_verified' => TRUE, 'mode' => $mode,
  'body_sha256' => hash('sha256', $body), 'html_sha256' => hash('sha256', $html),
  'portal_destination' => $url, 'raw_visible_urls' => 0, 'cta_count' => 1];
if ($mode === 'preflight') { echo json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); return; }
if (getenv('FAMTASTIC_PROOF_LINK_CONFIRM') !== "request$id-correct-and-resend-20260918"
  || getenv('FAMTASTIC_PROOF_LINK_BODY_SHA256') !== $receipt['body_sha256']
  || getenv('FAMTASTIC_PROOF_LINK_HTML_SHA256') !== $receipt['html_sha256']) throw new RuntimeException('Exact reviewed content confirmation required.');
if (\Drupal\Core\Site\Settings::get('famtastic_protected_staging', FALSE)
  || (getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') ?: \Drupal\Core\Site\Settings::get('famtastic_transactional_email_transport', 'smtp')) !== 'smtp'
  || \Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive()) throw new RuntimeException('Production scoped transport unavailable.');
$lock = \Drupal::lock();
if (!$lock->acquire($key, 180)) throw new RuntimeException('Correction already in progress.');
try {
  $find = fn() => $db->select('famtastic_notification_outbox', 'n')->fields('n')->condition('notification_key', $key)->execute()->fetchAssoc();
  $row = $find();
  if (!$row) {
    $transaction = $db->startTransaction();
    \Drupal::service('famtastic_pipeline.customer_portal')->queueNotification($key, 'operational', $original['recipient'], $subject, $body, $template, $version);
    $db->update('famtastic_notification_outbox')->fields(['max_attempts' => 1])->condition('notification_key', $key)->condition('attempts', 0)->execute();
    unset($transaction);
    $row = $find();
  }
  if ($row['body'] !== $body || $row['subject'] !== $subject || $row['recipient'] !== $original['recipient']
    || $row['template_id'] !== $template || (int) $row['template_version'] !== $version) throw new RuntimeException('Immutable correction mismatch.');
  if ($row['status'] !== 'sent') {
    if ($row['status'] !== 'queued' || (int) $row['attempts'] !== 0) throw new RuntimeException('Uncertain attempt: reconcile, never resend automatically.');
    $receipt['dispatch'] = \Drupal::service('famtastic_pipeline.lifecycle_operations')->dispatchNotifications(1, [$key]);
    $row = $find();
  }
  $receipt['outbox'] = array_intersect_key($row, array_flip(['id', 'status', 'attempts', 'provider_message_id', 'sent_at']));
  $receipt['original_unchanged'] = $original === $db->select('famtastic_notification_outbox', 'n')->fields('n')->condition('id', $originalId)->execute()->fetchAssoc();
  $receipt['inbox_or_readership_verified'] = FALSE;
  file_put_contents($directory . '/receipt.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  echo json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($row['status'] !== 'sent' || !$row['provider_message_id'] || !$receipt['original_unchanged']) throw new RuntimeException('Acceptance/original receipt not verified.');
}
finally { $lock->release($key); }
