<?php
declare(strict_types=1);
// Pure attachment-contract test. No Drupal bootstrap, DB, session or transport.
class Drupal {
  public static string $active = 'famtastic_admin';
  public static function theme(): object { return new class {
    public function getActiveTheme(): object { return new class {
      public function getName(): string { return Drupal::$active; }
    }; }
  }; }
  public static function service(string $id): object { return new class {
    public function getPath(string $name): string { return 'themes/custom/' . $name; }
  }; }
}
function base_path(): string { return '/web/'; }
require __DIR__ . '/../backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.module';
function check(bool $value, string $label): void { if (!$value) throw new RuntimeException($label); }
foreach (['famtastic_admin', 'famtastic_customer'] as $theme) {
  Drupal::$active = $theme;
  $attachments = ['#attached' => ['html_head_link' => [
    [['rel' => 'shortcut icon', 'href' => '/old.ico'], FALSE],
    [['rel' => 'canonical', 'href' => '/unchanged'], TRUE],
  ]]];
  famtastic_pipeline_page_attachments_alter($attachments);
  $links = $attachments['#attached']['html_head_link'];
  check(count($links) === 4, 'one canonical and three new icon links');
  check($links[0][0]['href'] === '/unchanged', 'canonical untouched');
  check(str_starts_with($links[1][0]['href'], '/web/themes/custom/famtastic_admin/brand/'), 'subdirectory base path');
  check($attachments['#cache']['contexts'] === ['theme'], 'theme cache context');
  check(!str_contains(json_encode($links), '/old.ico'), 'legacy icon removed');
}
Drupal::$active = 'customer_independent';
$attachments = ['#attached' => ['library' => ['existing/library']]];
$original = $attachments;
famtastic_pipeline_page_attachments_alter($attachments);
check($attachments === $original, 'other themes untouched');
echo "PASS: agency theme icon replacement, canonical preservation, base path, cache context and other-theme exclusion. Stub contract only; not Drupal integration.\n";
