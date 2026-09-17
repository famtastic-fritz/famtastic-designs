<?php
declare(strict_types=1);

namespace Drupal\Core\Site {
  // Isolated presentation harness, not Drupal or a transport bootstrap.
  final class Settings {
    public static string $logo = '';
    public static function get(string $name, mixed $default = NULL): mixed {
      return $name === 'famtastic_staging_review_logo_url' ? self::$logo : $default;
    }
  }
}
namespace {
  use Drupal\famtastic_pipeline\Service\StagingReviewEmail;
  use Drupal\famtastic_pipeline\Service\OutreachMailer;
  $root = dirname(__DIR__, 2);
  $service = '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
  require $root . $service . 'StagingReviewEmail.php';
  require $root . $service . 'OutreachMailer.php';
  $fixture = require __DIR__ . '/valerie.php';
  $count = 0;
  function check(bool $value, string $message): void {
    global $count;
    if (!$value) { throw new \RuntimeException($message); }
    $count++;
  }
  function rejected(callable $call): void {
    try { $call(); } catch (\InvalidArgumentException $e) { check(TRUE, $e->getMessage()); return; }
    throw new \RuntimeException('Expected rejection');
  }
  $logo = './assets/famtastic-designs-logo-v1.png';
  $render = fn(string $body) => StagingReviewEmail::render($fixture['subject'], $body, $logo, TRUE);
  $html = $render($fixture['body']);
  check(str_contains($html, '<strong>The Signal Room</strong>'), 'Selected direction');
  check(substr_count($html, 'href=') === 1, 'One action only');
  check(str_contains($html, 'href="https://prosintraining.famtasticinc.com/"'), 'Exact destination');
  check(!preg_match('/\{\{\s*[a-z_]+\s*\}\}|555-0123|View in browser/i', $html), 'No placeholders');
  check(str_contains($fixture['body'], "Always FAMtastic,\nShay"), 'Exact signature');
  check(str_contains($fixture['body'], 'https://prosintraining.famtasticinc.com/'), 'Plain text destination');
  $attack = $render("<script>alert(1)</script> & \"quote\" https://evil.invalid/\n\n" . $fixture['body']);
  check(!str_contains($attack, '<script>'), 'Customer markup escaped');
  check(str_contains($attack, '&lt;script&gt;') && str_contains($attack, '&quot;quote&quot;'), 'Entities escaped');
  check(substr_count($attack, 'href=') === 1, 'Customer URLs never become actions');
  check(str_contains(StagingReviewEmail::render('<img src=x>', $fixture['body'], $logo, TRUE), '&lt;img src=x&gt;'), 'Subject escaped');
  foreach (['javascript:alert(1)', 'http://prosintraining.famtasticinc.com/', 'https://evil.invalid/', 'https://prosintraining.famtasticinc.com.evil.invalid/', 'https://user@prosintraining.famtasticinc.com/', 'https://prosintraining.famtasticinc.com:8443/', 'https://prosintraining.famtasticinc.com/?next=https://evil.invalid', 'https://prosintraining.famtasticinc.com/redirect', 'https://prosintraining.famtasticinc.com/#x'] as $url) {
    rejected(fn() => $render(str_replace('https://prosintraining.famtasticinc.com/', $url, $fixture['body'])));
  }
  rejected(fn() => $render('No system destination'));
  foreach (['', $logo, 'http://famtasticdesigns.com/logo.png', 'https://evil.invalid/logo.png', 'https://famtasticdesigns.com/logo.png" onload="x'] as $unsafeLogo) {
    rejected(fn() => StagingReviewEmail::render($fixture['subject'], $fixture['body'], $unsafeLogo));
  }
  check(OutreachMailer::supportsTemplate('customer_staging_review_ready', 1), 'Version 1 supported');
  check(!OutreachMailer::supportsTemplate('customer_staging_review_ready', 2), 'Unknown version rejected');
  $reflection = new \ReflectionClass(OutreachMailer::class);
  $mailer = $reflection->newInstanceWithoutConstructor();
  $method = $reflection->getMethod('renderHtmlMessage');
  rejected(fn() => $method->invoke($mailer, $fixture['subject'], $fixture['body'], 'customer_staging_review_ready'));
  \Drupal\Core\Site\Settings::$logo = 'https://famtasticdesigns.com/brand/famtastic-designs-logo-v1.png';
  $integrated = $method->invoke($mailer, $fixture['subject'], $fixture['body'], 'customer_staging_review_ready');
  check($integrated === StagingReviewEmail::render($fixture['subject'], $fixture['body'], \Drupal\Core\Site\Settings::$logo), 'Mailer uses exact new renderer');
  // Compare every previous template against the exact pre-change source.
  $baseline = shell_exec('git -C ' . escapeshellarg($root) . ' show 70d2a8cf:backend/web/modules/custom/famtastic_pipeline/src/Service/OutreachMailer.php');
  check(is_string($baseline) && str_starts_with($baseline, '<?php'), 'Baseline available');
  eval(str_replace(['<?php', 'class OutreachMailer {'], ['', 'class BaselineOutreachMailer {'], $baseline));
  $oldClass = new \ReflectionClass('Drupal\\famtastic_pipeline\\Service\\BaselineOutreachMailer');
  $old = $oldClass->newInstanceWithoutConstructor();
  $oldMethod = $oldClass->getMethod('renderHtmlMessage');
  foreach (['standard', 'customer_intake_submitted', 'customer_proof_ready', 'customer_revision_received', 'customer_message_reply'] as $template) {
    $body = "Hello & welcome.\n\nOpen your workspace:\nhttps://famtasticdesigns.com/portal";
    check($method->invoke($mailer, 'A <review>', $body, $template) === $oldMethod->invoke($old, 'A <review>', $body, $template), 'Legacy unchanged: ' . $template);
  }
  echo "PASS: {$count} presentation assertions; no transport, queue or database loaded.\n";
}
