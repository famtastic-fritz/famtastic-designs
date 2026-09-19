<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\WorkerCoordinatorController;
use Drupal\famtastic_pipeline\Service\WorkerCoordinator;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/** Real authentication/controller; persistence is covered separately with SQLite. */
final class WorkerCoordinatorControllerTest extends UnitTestCase {
  private object $coordinator;
  private object $portal;
  protected function setUp(): void {
    parent::setUp();
    $this->coordinator = new class {
      public array $nonces = [];
      public int $claims = 0;
      public function rememberNonce(string $worker, string $nonce): void {
        $key = $worker . ':' . $nonce;
        if (isset($this->nonces[$key])) throw new \RuntimeException('Replay');
        $this->nonces[$key] = TRUE;
      }
      public function claim(string $worker): ?array { $this->claims++; return NULL; }
    };
    $this->portal = new class {
      public array $calls = [];
      public function releaseWebsiteRequestProofAfterQa(int $request, array $research, array $evidence, string $reviewer, array $notice): array {
        $this->calls[] = [$request, $reviewer];
        return ['email_sent_by_this_operation' => FALSE];
      }
    };
    $container = new ContainerBuilder();
    $container->set('famtastic_pipeline.worker_coordinator', $this->coordinator);
    $container->set('famtastic_pipeline.customer_portal', $this->portal);
    $container->set('famtastic_pipeline.pilot_exact_dispatch_lock', new class { public function isActive(): bool { return FALSE; } });
    \Drupal::setContainer($container);
    $this->settings();
  }
  protected function tearDown(): void { new Settings([]); parent::tearDown(); }
  private function settings(bool $enabled = TRUE): void {
    new Settings(['famtastic_bounded_workers_enabled' => $enabled, 'famtastic_worker_registry' => [
      'mac-builder' => ['secret' => str_repeat('b', 32), 'capabilities' => [WorkerCoordinator::CAPABILITY]],
      'qa-reviewer' => ['secret' => str_repeat('q', 32), 'capabilities' => ['proof.review']],
    ]]);
  }
  private function request(string $operation = 'claim', string $worker = 'mac-builder', array $body = []): Request {
    $wire = json_encode($body, JSON_THROW_ON_ERROR);
    $nonce = bin2hex(random_bytes(16)); $time = (string) time();
    $path = '/api/pipeline/worker/' . $operation;
    $request = Request::create('https://authority.example.test/web' . $path, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $wire);
    $secret = str_repeat($worker === 'qa-reviewer' ? 'q' : 'b', 32);
    $signature = hash_hmac('sha256', implode("\n", ['POST', $path, $worker, $time, $nonce, hash('sha256', $wire)]), $secret);
    $request->headers->add(['X-FAMtastic-Worker' => $worker, 'X-FAMtastic-Timestamp' => $time, 'X-FAMtastic-Nonce' => $nonce, 'X-FAMtastic-Signature' => 'sha256=' . $signature]);
    return $request;
  }
  public function testDisabledEndpointCannotClaim(): void {
    $this->settings(FALSE);
    self::assertSame(503, (new WorkerCoordinatorController())->handle($this->request(), 'claim')->getStatusCode());
    self::assertSame(0, $this->coordinator->claims);
  }
  public function testTlsAndSignatureAreMandatory(): void {
    $controller = new WorkerCoordinatorController();
    $http = $this->request(); $http->server->set('HTTPS', 'off');
    self::assertSame(403, $controller->handle($http, 'claim')->getStatusCode());
    $wrong = $this->request(); $wrong->headers->set('X-FAMtastic-Signature', 'sha256=' . str_repeat('0', 64));
    self::assertSame(403, $controller->handle($wrong, 'claim')->getStatusCode());
    self::assertSame(0, $this->coordinator->claims);
  }
  public function testExactSignatureWorksOnceThenReplayFails(): void {
    $controller = new WorkerCoordinatorController(); $request = $this->request();
    self::assertSame(200, $controller->handle($request, 'claim')->getStatusCode());
    self::assertSame(403, $controller->handle($request, 'claim')->getStatusCode());
    self::assertSame(1, $this->coordinator->claims);
  }
  public function testBuilderCannotReviewAndReviewerCannotClaim(): void {
    $controller = new WorkerCoordinatorController();
    self::assertSame(403, $controller->handle($this->request('review'), 'review')->getStatusCode());
    self::assertSame(403, $controller->handle($this->request('claim', 'qa-reviewer'), 'claim')->getStatusCode());
    self::assertSame([], $this->portal->calls); self::assertSame(0, $this->coordinator->claims);
  }
  public function testReviewerIdentityIsDerivedNotTakenFromBody(): void {
    $request = $this->request('review', 'qa-reviewer', ['request_id' => 901, 'reviewer' => 'Fritz', 'uid' => 1]);
    $response = (new WorkerCoordinatorController())->handle($request, 'review');
    self::assertSame(200, $response->getStatusCode());
    self::assertSame([[901, 'automation:qa-reviewer']], $this->portal->calls);
    self::assertFalse(json_decode($response->getContent(), TRUE)['result']['email_sent_by_this_operation']);
  }
}
