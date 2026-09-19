<?php
namespace Drupal\Core\Render { final class Markup { public static function create($value) { return $value; } } }
namespace Drupal\famtastic_pipeline\Theme { final class AdminErrorContext { public static function applies(...$args) { return FALSE; } } }
namespace {
  final class Drupal {
    public static function routeMatch() { return new class { public function getRouteName() { return 'user.login'; } public function getRouteObject() { return NULL; } }; }
    public static function service($name) { return NULL; }
  }
  require dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/CreatorCredit.php';
  require dirname(__DIR__) . '/backend/web/themes/custom/famtastic_admin/famtastic_admin.theme';
  $variables = ['page_bottom' => ['existing' => ['#markup' => 'Preserve existing bottom']], 'attributes' => ['class' => []]];
  famtastic_admin_preprocess_html($variables);
  famtastic_admin_preprocess_html($variables);
  if (count($variables['page_bottom']) !== 2 || !isset($variables['page_bottom']['existing'])) throw new \RuntimeException('Existing bottom lost or credit duplicated');
  if (substr_count($variables['page_bottom']['famtastic_creator_credit']['#markup'], 'data-famtastic-creator-credit="v1"') !== 1) throw new \RuntimeException('Credit missing');
  echo "PASS: admin HTML hook preserves existing page bottom and adds one credit\n";
}
