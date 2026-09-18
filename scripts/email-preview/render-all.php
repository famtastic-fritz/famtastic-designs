<?php
// Non-sending pure-render harness. Tests bootstrap only isolated Settings.
require __DIR__ . '/test.php';
$output = dirname(__DIR__, 2) . '/.local-email-preview';
@mkdir($output, 0700, TRUE);
$cases = [
  'standard' => ['Verified FAMtastic customer registration — sample@example.test', "Customer: Sample customer\nEmail: sample@example.test\nEmail verification completed.\nOpen customers: https://famtasticdesigns.com/web/admin/famtastic/metric/customers"],
  'customer_intake_submitted' => ['Your design review has started', "Hi Sample,\n\nYour intake is received.\n\nOpen your workspace:\nhttps://famtasticdesigns.com/portal"],
  'customer_proof_ready' => ['Your concepts are ready', "Hi Sample,\n\nReview your concepts.\n\nOpen your workspace:\nhttps://famtasticdesigns.com/portal"],
  'customer_revision_received' => ['Your feedback was received', "Hi Sample,\n\nYour feedback is saved.\n\nOpen your project:\nhttps://famtasticdesigns.com/portal"],
  'customer_message_reply' => ['A reply from Shay', "Hi Sample,\n\nHere is your project update.\n\nShay\n\nOpen your workspace:\nhttps://famtasticdesigns.com/portal?section=messages&thread=12345678-1234-1234-1234-123456789012\n\nSign in with the email address that received this message to continue the conversation."],
  'customer_staging_review_ready' => [$fixture['subject'], $fixture['body']],
];
foreach ($cases as $template => [$subject, $body]) {
  file_put_contents($output . '/' . $template . '.html', $method->invoke($mailer, $subject, $body, $template));
}
file_put_contents($output . '/index.html', '<!doctype html><title>Email brand review</title><h1>Shared email branding — synthetic previews</h1>' . implode('', array_map(fn($key) => '<p><a href="' . $key . '.html">' . $key . '</a></p>', array_keys($cases))));
echo "Rendered six templates without sending.\n";
