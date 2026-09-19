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
  require $root . $service . 'BrandedEmail.php';
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
  check(substr_count($html, 'href=') === 2, 'One operational action plus creator credit');
  check(substr_count($html, 'data-famtastic-creator-credit="v1"') === 1, 'One final creator credit');
  check(str_contains($html, 'href="https://famtasticdesigns.com/?utm_source=famtastic-designs&amp;utm_medium=creator_credit&amp;utm_campaign=created_by_famtastic"'), 'Exact public attribution');
  check(str_contains($html, 'href="https://prosintraining.famtasticinc.com/"'), 'Exact destination');
  check(!preg_match('/\{\{\s*[a-z_]+\s*\}\}|555-0123|View in browser/i', $html), 'No placeholders');
  check(str_contains($fixture['body'], "Always FAMtastic,\nShay"), 'Exact signature');
  check(str_contains($fixture['body'], 'https://prosintraining.famtasticinc.com/'), 'Plain text destination');
  $attack = $render("<script>alert(1)</script> & \"quote\" https://evil.invalid/\n\n" . $fixture['body']);
  check(!str_contains($attack, '<script>'), 'Customer markup escaped');
  check(str_contains($attack, '&lt;script&gt;') && str_contains($attack, '&quot;quote&quot;'), 'Entities escaped');
  check(substr_count($attack, 'href=') === 2, 'Customer URLs never become actions');
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
  // Preserve destinations while intentionally replacing every legacy layout.
  foreach (['standard', 'customer_intake_submitted', 'customer_proof_ready', 'customer_revision_received', 'customer_message_reply'] as $template) {
    $body = "Hello & welcome.\n\nOpen your workspace:\nhttps://famtasticdesigns.com/portal";
    $current = $method->invoke($mailer, 'A <review>', $body, $template);
    preg_match_all('/href="([^"]+)"/', $current, $afterLinks);
    $expectedLinks = $template === 'customer_message_reply' ? [] : ['https://famtasticdesigns.com/portal'];
    $expectedLinks[] = 'https://famtasticdesigns.com/?utm_source=famtastic-designs&amp;utm_medium=creator_credit&amp;utm_campaign=created_by_famtastic';
    check($expectedLinks === $afterLinks[1], 'Operational destinations unchanged plus exact credit: ' . $template);
    check(str_contains($current, 'data-famtastic-email-brand="v1"'), 'Shared brand: ' . $template);
    check(str_contains($current, 'famtastic-designs-logo-v1.png'), 'Original logo: ' . $template);
    check(str_contains($current, 'Hello &amp; welcome.'), 'Body preserved: ' . $template);
    check(str_contains($current, 'A &lt;review&gt;'), 'Subject escaped: ' . $template);
    check(!str_contains($current, 'background:#102a1c') && !str_contains($current, 'background:#edf1eb'), 'Legacy shell absent: ' . $template);
    check(!str_contains($current, 'No payment is due at this review stage.'), 'No staging claim leaked: ' . $template);
  }
  $reply = "<script>bad</script> https://evil.invalid/\n\nOpen your workspace:\nhttps://famtasticdesigns.com/portal?section=messages&thread=12345678-1234-1234-1234-123456789012\n\nSign in with the email address that received this message to continue the conversation.";
  $replyHtml = $method->invoke($mailer, 'Reply', $reply, 'customer_message_reply');
  check(!str_contains($replyHtml, '<script>') && !str_contains($replyHtml, 'href="https://evil.invalid/"'), 'Reply content cannot replace trusted CTA');
  check(str_contains($replyHtml, 'thread=12345678-1234-1234-1234-123456789012'), 'Reply thread preserved');
  foreach (['javascript:alert(1)', 'https://user:pass@example.test/'] as $badAction) {
    rejected(fn() => \Drupal\famtastic_pipeline\Service\BrandedEmail::render('Subject', '<p>Safe</p>', url: $badAction));
  }
  check(OutreachMailer::supportsTemplate('standard', 1) && OutreachMailer::supportsTemplate('standard', 2), 'Old queued and new standard versions supported');
  check(OutreachMailer::supportsTemplate('customer_proof_ready', 3) && OutreachMailer::supportsTemplate('customer_proof_ready', 4), 'Proof version compatibility');
  check(!OutreachMailer::supportsTemplate('standard', 99), 'Unknown versions rejected');
  $source = file_get_contents($root . $service . 'OutreachMailer.php');
  check(!str_contains($source, '<!doctype html>'), 'Mailer may not own a parallel HTML shell');
  $projectUrl = 'https://famtasticdesigns.com/portal/?section=projects&request=12345678-1234-1234-1234-123456789012';
  $legacyApi = 'https://famtasticdesigns.com/web/api/customer/website-requests/12345678-1234-1234-1234-123456789012/proofs/c';
  foreach (['standard', 'customer_proof_ready'] as $template) {
    $message = $method->invoke($mailer, 'Proof test', "Hello <script>x</script>\n\nOpen your project:\n$projectUrl\n\nAlways FAMtastic,\nShay", $template);
    $doc = new \DOMDocument(); @$doc->loadHTML($message);
    check(!str_contains($doc->getElementsByTagName('body')->item(0)->textContent, 'https://'), 'No visible raw URLs: ' . $template);
    check($doc->getElementsByTagName('a')->length === 2, 'One named action plus credit: ' . $template);
    check($doc->getElementsByTagName('a')->item(0)->getAttribute('href') === $projectUrl, 'Exact query preserved: ' . $template);
    check(!str_contains($message, '<script>'), 'Escaped content: ' . $template);
  }
  $legacy = $method->invoke($mailer, 'Legacy queued', $legacyApi, 'standard');
  check(str_contains($legacy, htmlspecialchars($projectUrl, ENT_QUOTES)), 'Legacy protected proof converted to portal button');
  check(!str_contains($legacy, '/web/api/'), 'API URL never an email entry');
  foreach (['https://user:password@evil.invalid/', 'https://evil.invalid/"onclick="x'] as $unsafe) {
    $bad = $method->invoke($mailer, 'Unsafe', $unsafe, 'standard');
    check(substr_count($bad, 'href=') === 1 && !str_contains($bad, 'href="https://evil.invalid'), 'Invalid action rejected; only fixed creator credit remains');
  }
  echo "PASS: {$count} presentation assertions; no transport, queue or database loaded.\n";
}
