<?php
declare(strict_types=1);

// CLI only. No Drupal bootstrap, database, mailer, queue or transport calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/backend/web/modules/custom/famtastic_pipeline/src/Service/BrandedEmail.php';
require $root . '/backend/web/modules/custom/famtastic_pipeline/src/Service/StagingReviewEmail.php';
$fixture = require __DIR__ . '/valerie.php';
$output = $root . '/.local-email-preview';
if (!is_dir($output . '/assets')) { mkdir($output . '/assets', 0700, TRUE); }
copy($root . '/docs/design/assets/famtastic-designs-logo-v1.png', $output . '/assets/famtastic-designs-logo-v1.png');
$html = \Drupal\famtastic_pipeline\Service\StagingReviewEmail::render($fixture['subject'], $fixture['body'], './assets/famtastic-designs-logo-v1.png', TRUE);
file_put_contents($output . '/email.html', $html);
file_put_contents($output . '/email.txt', $fixture['subject'] . "\n\n" . $fixture['body']);
copy(__DIR__ . '/gallery.html', $output . '/index.html');
if (isset($argv[1]) && is_file($argv[1])) { copy($argv[1], $output . '/reference.png'); }
echo "Local preview rendered: {$output}/index.html\nNo mail transport was loaded.\n";
