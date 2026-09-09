<?php

declare(strict_types=1);

namespace GuzzleHttp {
  interface ClientInterface {
    public function request($method, $uri = '', array $options = []);
  }
}

namespace Drupal\Core\Config {
  interface ConfigFactoryInterface {
    public function get($name);
  }
}

namespace Drupal\Core\Site {
  final class Settings {
    public static function get($name, $default = NULL) { return $default; }
  }
}

namespace {
  use Drupal\Core\Config\ConfigFactoryInterface;
  use Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService;
  use Drupal\famtastic_pipeline\Service\SiteStudioStagingClient;
  use GuzzleHttp\ClientInterface;

  require dirname(__DIR__, 2) . '/src/Service/SiteStudioBuildPacketService.php';
  require dirname(__DIR__, 2) . '/src/Service/SiteStudioStagingClient.php';

  final class RuntimeConfig {
    public function __construct(private readonly string $endpoint) {}
    public function get(string $name): string { return $name === 'site_studio_staging_url' ? $this->endpoint : ''; }
  }

  final class RuntimeConfigFactory implements ConfigFactoryInterface {
    public function __construct(private readonly string $endpoint) {}
    public function get($name): RuntimeConfig { return new RuntimeConfig($this->endpoint); }
  }

  final class RuntimeResponse {
    public function __construct(private readonly int $status, private readonly string $body) {}
    public function getStatusCode(): int { return $this->status; }
    public function getBody(): string { return $this->body; }
  }

  final class NativeHttpClient implements ClientInterface {
    public function request($method, $uri = '', array $options = []): RuntimeResponse {
      $headers = [];
      foreach (($options['headers'] ?? []) as $name => $value) {
        $headers[] = $name . ': ' . $value;
      }
      $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => (string) ($options['body'] ?? ''),
        'ignore_errors' => TRUE,
        'timeout' => (int) ($options['timeout'] ?? 30),
      ]]);
      $body = file_get_contents((string) $uri, FALSE, $context);
      if ($body === FALSE) {
        throw new RuntimeException('Unable to reach the isolated Site Studio test server.');
      }
      $status = 0;
      foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
          $status = (int) $match[1];
        }
      }
      return new RuntimeResponse($status, $body);
    }
  }

  $endpoint = getenv('SITE_STUDIO_STAGING_URL') ?: '';
  if ($endpoint === '') {
    throw new RuntimeException('SITE_STUDIO_STAGING_URL is required for the cross-repository runtime test.');
  }
  $artifacts = [
    ['role' => 'selected_preview', 'path' => 'proofs/12/b/index.html', 'sha256' => str_repeat('a', 64), 'bytes' => 1200],
    ['role' => 'source_material', 'path' => 'proofs/12/b/assets/hero.webp', 'sha256' => str_repeat('b', 64), 'bytes' => 2400],
  ];
  $packet = [
    'schema' => 'famtastic.site-studio.build-packet.v1',
    'packet_id' => 'cross-repo-runtime-packet-1',
    'idempotency_key' => 'cross-repo-runtime-packet-1',
    'request_id' => 'request-runtime-12',
    'project_id' => '42',
    'build_class' => 'prepayment_selected_direction_staging',
    'selected_direction_ids' => ['direction-b'],
    'artifacts' => $artifacts,
    'artifact_manifest_sha256' => SiteStudioBuildPacketService::artifactManifestDigest($artifacts),
    'selected_artifacts' => [[
      'direction_id' => 'direction-b',
      'source_artifact_path' => $artifacts[0]['path'],
      'source_artifact_sha256' => $artifacts[0]['sha256'],
      'source_artifact_bytes' => $artifacts[0]['bytes'],
    ]],
    'boundary' => ['deploy_authorized' => FALSE],
  ];

  $client = new SiteStudioStagingClient(new NativeHttpClient(), new RuntimeConfigFactory($endpoint));
  $result = $client->dispatch($packet);
  if (($result['status'] ?? '') !== 'accepted_waiting_callback' || ($result['packet_id'] ?? '') !== $packet['packet_id']) {
    throw new RuntimeException('Cross-repository staging acceptance did not preserve packet identity.');
  }
  fwrite(STDOUT, "cross-repository staging dispatch: PASS\n");
}
