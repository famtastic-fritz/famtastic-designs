<?php
require dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/CreatorCredit.php';
use Drupal\famtastic_pipeline\Service\CreatorCredit;
$html = '<html><body><footer>Original immutable credit</footer></body></html>';
$hash = hash('sha256', $html);
$rendered = CreatorCredit::present($html);
if ($hash !== hash('sha256', $html)) throw new RuntimeException('Source changed');
if (substr_count($rendered, 'data-famtastic-creator-credit="v1"') !== 1) throw new RuntimeException('Missing credit');
if (!str_contains($rendered, 'src="/brand/famtastic-designs-logo-v1.png"')) throw new RuntimeException('CSP same-origin image missing');
if (!str_contains($rendered, '<footer>Original immutable credit</footer>')) throw new RuntimeException('Footer lost');
if (!str_ends_with($rendered, "</body></html>")) throw new RuntimeException('Credit must remain inside body');
if (CreatorCredit::present($rendered) !== $rendered) throw new RuntimeException('Duplicate credit');
echo "PASS: response-only credit preserves source/hash/footer, same-origin CSP, final placement and idempotence\n";
