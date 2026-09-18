<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Immutable raw ingress evidence. No upstream filesystem locator is followed. */
final class SelectedSourceCapture {
  public static function write(string $directory, string $raw, string $event, string $campaign, string $job, string $direction, string $html): array {
    $data = json_decode($raw, FALSE, 512, JSON_THROW_ON_ERROR);
    if (!is_object($data) || ($data->event_id ?? '') !== $event || ($data->campaign_id ?? '') !== $campaign || ($data->job_id ?? '') !== $job) throw new \InvalidArgumentException('selected_source_capture_identity_mismatch');
    $variants = array_values(array_filter($data->variants ?? [], static fn($v): bool => is_object($v) && ($v->direction_id ?? '') === $direction));
    if (count($variants) !== 1 || ($variants[0]->html ?? '') !== $html) throw new \InvalidArgumentException('selected_source_capture_bytes_mismatch');
    $hash = hash('sha256', $raw);
    $name = 'source-callback-' . $hash . '.json';
    $path = $directory . '/' . $name;
    if (is_file($path)) {
      if (file_get_contents($path) !== $raw) throw new \RuntimeException('selected_source_capture_conflict');
    } else {
      $handle = fopen($path, 'xb');
      if (!$handle) throw new \RuntimeException('selected_source_capture_write_failed');
      try { if (fwrite($handle, $raw) !== strlen($raw)) throw new \RuntimeException('selected_source_capture_write_failed'); }
      finally { fclose($handle); }
      chmod($path, 0600);
    }
    return ['schema' => 'famtastic.selected-source-capture.v1', 'raw_callback_file' => $name,
      'raw_callback_sha256' => $hash, 'raw_callback_bytes' => strlen($raw),
      'event_id' => $event, 'campaign_id' => $campaign, 'job_id' => $job, 'direction_id' => $direction,
      'selected_sha256' => hash('sha256', $html), 'selected_bytes' => strlen($html),
      'template_provenance' => 'selected_source_only_uninspected', 'original_template_received' => FALSE];
  }
}
