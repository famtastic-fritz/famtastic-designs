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
    public static function get($name, $default = NULL) {
      return $default;
    }
  }
}

namespace {
  use Drupal\Core\Config\ConfigFactoryInterface;
  use Drupal\famtastic_pipeline\Service\SiteStudioStagingClient;
  use GuzzleHttp\ClientInterface;

  require dirname(__DIR__, 2) . '/src/Service/SiteStudioStagingClient.php';

  final class FakeConfig {
    public function __construct(private readonly string $endpoint) {}
    public function get(string $name): string {
      return $name === 'site_studio_staging_url' ? $this->endpoint : '';
    }
  }

  final class FakeConfigFactory implements ConfigFactoryInterface {
    public function __construct(private readonly string $endpoint) {}
    public function get($name): FakeConfig {
      return new FakeConfig($this->endpoint);
    }
  }

  final class FakeResponse {
    public function __construct(private readonly int $status, private readonly string $body) {}
    public function getStatusCode(): int { return $this->status; }
    public function getBody(): string { return $this->body; }
  }

  final class FakeHttpClient implements ClientInterface {
    public array $last = [];
    public function __construct(private readonly int $status = 202, private readonly ?array $response = NULL) {}
    public function request($method, $uri = '', array $options = []): FakeResponse {
      $this->last = compact('method', 'uri', 'options');
      $packet = json_decode((string) ($options['body'] ?? ''), TRUE)['packet'] ?? [];
      $body = $this->response ?? [
        'accepted' => TRUE,
        'status' => 'accepted_waiting_callback',
        'receipt' => [
          'receipt_id' => 'event-123',
          'packet_id' => $packet['packet_id'] ?? '',
          'idempotency_key' => $packet['idempotency_key'] ?? '',
        ],
      ];
      return new FakeResponse($this->status, json_encode($body, JSON_THROW_ON_ERROR));
    }
  }

  function expect(bool $condition, string $message): void {
    if (!$condition) {
      throw new RuntimeException($message);
    }
  }

  function expectFailure(callable $callback, string $fragment): void {
    try {
      $callback();
    }
    catch (RuntimeException $error) {
      expect(str_contains($error->getMessage(), $fragment), 'Unexpected failure: ' . $error->getMessage());
      return;
    }
    throw new RuntimeException('Expected failure containing: ' . $fragment);
  }

  $packet = [
    'packet_id' => 'packet-123',
    'idempotency_key' => 'packet-123',
  ];
  putenv('FAMTASTIC_STUDIO_DISPATCH_SECRET=runtime-test-secret');
  $http = new FakeHttpClient();
  $client = new SiteStudioStagingClient($http, new FakeConfigFactory('http://127.0.0.1:4312/api/pipeline/staging/accept'));
  $result = $client->dispatch($packet);
  expect($result['status'] === 'accepted_waiting_callback', 'Expected waiting callback state.');
  expect($result['receipt_id'] === 'event-123', 'Expected a durable acceptance receipt id.');
  expect($http->last['method'] === 'POST', 'Expected a POST request.');
  $body = (string) $http->last['options']['body'];
  $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, 'runtime-test-secret');
  expect(hash_equals($expectedSignature, $http->last['options']['headers']['X-FAMtastic-Signature']), 'Expected an exact-body HMAC signature.');
  expect($http->last['options']['headers']['Idempotency-Key'] === 'packet-123', 'Expected packet idempotency header.');

  expectFailure(
    fn () => (new SiteStudioStagingClient(new FakeHttpClient(), new FakeConfigFactory('https://studio.example.com/api/pipeline/run')))->dispatch($packet),
    'exact HTTPS acceptance endpoint',
  );
  expectFailure(
    fn () => (new SiteStudioStagingClient(new FakeHttpClient(200), new FakeConfigFactory('https://studio.example.com/api/pipeline/staging/accept')))->dispatch($packet),
    'did not accept',
  );
  expectFailure(
    fn () => (new SiteStudioStagingClient(new FakeHttpClient(202, [
      'accepted' => TRUE,
      'status' => 'accepted_waiting_callback',
      'receipt' => ['receipt_id' => 'event-123', 'packet_id' => 'other', 'idempotency_key' => 'packet-123'],
    ]), new FakeConfigFactory('https://studio.example.com/api/pipeline/staging/accept')))->dispatch($packet),
    'receipt identity contract',
  );
  putenv('FAMTASTIC_STUDIO_DISPATCH_SECRET');
  expectFailure(
    fn () => (new SiteStudioStagingClient(new FakeHttpClient(), new FakeConfigFactory('')))->dispatch($packet),
    'explicitly configured endpoint and dispatch secret',
  );

  fwrite(STDOUT, "site-studio staging client runtime test: PASS\n");
}
