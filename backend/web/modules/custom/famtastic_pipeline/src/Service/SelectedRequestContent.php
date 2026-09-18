<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Normal request input to immutable authored page records, without recipe IDs. */
final class SelectedRequestContent {
  public static function record(array $input, int $customerId, ?string $rawInput = NULL): array {
    if ($rawInput !== NULL && json_decode($rawInput, TRUE, 512, JSON_THROW_ON_ERROR) !== $input) throw new \InvalidArgumentException('Request bytes do not match request input.');
    $pages = $input['page_content'] ?? [];
    if (!is_array($pages) || !array_is_list($pages) || count($pages) > 20) throw new \InvalidArgumentException('Page content must be a list of at most 20 pages.');
    $records = []; $names = [];
    foreach ($pages as $page) {
      if (!is_array($page)) throw new \InvalidArgumentException('Each page needs authored text.');
      $clean = [];
      foreach (['page_name', 'title', 'description', 'heading', 'body'] as $field) {
        $value = $page[$field] ?? '';
        if (!is_string($value) || ($field === 'page_name' && !trim($value)) || strlen($value) > 20000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value)) throw new \InvalidArgumentException('Add valid page ' . $field . '.');
        $clean[$field] = trim($value);
      }
      $key = strtolower($clean['page_name']);
      if (isset($names[$key])) throw new \InvalidArgumentException('Each authored page name must be unique.');
      $names[$key] = TRUE;
      $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $records[] = ['schema' => 'famtastic.request-page-content.v1', 'record_id' => 'request-content:' . hash('sha256', $json), 'customer_id' => $customerId,
        'source' => 'authenticated_customer_request', 'text' => $clean, 'sha256' => hash('sha256', $json)];
    }
    return ['schema' => 'famtastic.request-content-submission.v1', 'customer_id' => $customerId, 'pages' => $records,
      'raw_input_json' => $rawInput, 'raw_input_sha256' => $rawInput === NULL ? NULL : hash('sha256', $rawInput),
      'raw_input_retained' => $rawInput !== NULL];
  }
}
