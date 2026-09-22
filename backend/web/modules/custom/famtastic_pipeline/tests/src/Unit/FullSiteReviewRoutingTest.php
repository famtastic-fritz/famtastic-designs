<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\MatcherDumper;
use Drupal\Core\Routing\RouteProvider;
use Drupal\Core\State\StateInterface;
use Drupal\famtastic_pipeline\Service\FullSiteReviewPackage;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 3) . '/src/Service/FullSiteReviewPackage.php';

/** Actual route YAML -> Drupal database dumper/provider -> Symfony matching. */
final class FullSiteReviewRoutingTest extends UnitTestCase {
  private const PUBLIC_ID = '11111111-2222-4333-8444-555555555555';

  private function definitions(): array {
    return array_filter(Yaml::parseFile(dirname(__DIR__, 3) . '/famtastic_pipeline.routing.yml'), static fn(string $name): bool => str_contains($name, '_full_site_review_'), ARRAY_FILTER_USE_KEY);
  }

  private function provider(array $definitions): array {
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $database = new Connection(Connection::open($options), $options);
    $values = [];
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(static function ($key, $default = NULL) use (&$values) { return $values[$key] ?? $default; });
    $state->method('set')->willReturnCallback(static function ($key, $value) use (&$values): void { $values[$key] = $value; });
    $routes = new RouteCollection();
    foreach ($definitions as $name => $definition) $routes->add($name, new Route($definition['path'], $definition['defaults'], $definition['requirements'], $definition['options'], '', [], $definition['methods']));
    // Use the real Drupal dumper so outlines, number_parts and fit masks are
    // exactly those stored during a production router rebuild.
    $dumper = new MatcherDumper($database, $state, new NullLogger());
    $dumper->addRoutes($routes); $dumper->dump();
    $cache = $this->createMock(CacheBackendInterface::class); $cache->method('get')->willReturn(FALSE);
    $processor = $this->createMock(InboundPathProcessorInterface::class); $processor->method('processInbound')->willReturnCallback(static fn(string $path): string => $path);
    $language = $this->createMock(LanguageInterface::class); $language->method('getId')->willReturn('en');
    $languages = $this->createMock(LanguageManagerInterface::class); $languages->method('getCurrentLanguage')->willReturn($language);
    $provider = new RouteProvider($database, $state, new CurrentPathStack(new RequestStack()), $cache, $processor, $this->createMock(CacheTagsInvalidatorInterface::class), $cache, 'router', $languages);
    return [$provider, $routes];
  }

  private function paths(string $artifact): array {
    // /web is Drupal's base path; Request::getPathInfo omits it in production.
    return [substr(FullSiteReviewPackage::url(self::PUBLIC_ID, $artifact), 4), substr(FullSiteReviewPackage::staffUrl(93, $artifact), 4)];
  }

  public function testEverySupportedDepthSurvivesTheRealDatabaseCandidateFilter(): void {
    [$provider] = $this->provider($this->definitions());
    foreach (['index.html', 'pricing/index.html', 'request-delivery/index.html', 'review-documents/design.txt', 'assets/site.css', 'assets/fonts/example.woff2', 'assets/images/example.png'] as $artifact) {
      foreach ($this->paths($artifact) as $path) {
        foreach (['GET', 'HEAD'] as $method) {
          $request = Request::create($path . '?plan=starter&service=legal-courier', $method);
          $candidates = $provider->getRouteCollectionForRequest($request);
          self::assertCount(1, $candidates, $method . ' ' . $path);
          $match = (new UrlMatcher($candidates, (new RequestContext())->fromRequest($request)))->match($request->getPathInfo());
          $parts = [$match['artifact_path'], $match['artifact_part_2'] ?? '', $match['artifact_part_3'] ?? ''];
          self::assertSame($artifact, implode('/', array_filter($parts, static fn(string $part): bool => $part !== '')));
          self::assertSame('starter', $request->query->get('plan'));
          self::assertSame(str_starts_with($path, '/admin/') ? '93' : self::PUBLIC_ID, $match['website_request']);
        }
      }
    }
  }

  public function testOldSlashWildcardMatchesSymfonyButIsExcludedByDrupal(): void {
    $name = 'famtastic_pipeline.admin_full_site_review_file';
    $legacy = $this->definitions()[$name];
    $legacy['requirements']['artifact_path'] = '.+';
    [$provider, $routes] = $this->provider([$name => $legacy]);
    $path = $this->paths('pricing/index.html')[1];
    self::assertSame('pricing/index.html', (new UrlMatcher($routes, new RequestContext()))->match($path)['artifact_path']);
    self::assertCount(0, $provider->getRouteCollectionForRequest(Request::create($path)));
    self::assertCount(1, $provider->getRouteCollectionForRequest(Request::create($this->paths('index.html')[1])));
  }

  public function testAccessRequirementsAndReadOnlyMethodsRemainOnEveryRoute(): void {
    $definitions = $this->definitions();
    self::assertCount(6, $definitions);
    foreach ($definitions as $name => $definition) {
      self::assertSame(['GET', 'HEAD'], $definition['methods']);
      self::assertSame('TRUE', $definition['options']['no_cache']);
      $staff = str_contains($name, '.admin_');
      self::assertSame($staff ? 'administer famtastic pipeline' : 'TRUE', $definition['requirements'][$staff ? '_permission' : '_access']);
      self::assertStringEndsWith($staff ? '::adminFile' : '::file', $definition['defaults']['_controller']);
    }
    [$provider] = $this->provider($definitions);
    foreach (['index.html', 'pricing/index.html', 'assets/fonts/example.woff2'] as $artifact) {
      foreach ($this->paths($artifact) as $path) {
        $request = Request::create($path, 'POST');
        try { (new UrlMatcher($provider->getRouteCollectionForRequest($request), (new RequestContext())->fromRequest($request)))->match($path); self::fail('Write method matched.'); }
        catch (MethodNotAllowedException $error) { self::assertSame(['GET', 'HEAD'], $error->getAllowedMethods()); }
      }
    }
  }

  public function testDepthAndTraversalRemainBoundedBeforeThePrivateReader(): void {
    [$provider] = $this->provider($this->definitions());
    foreach (['assets/fonts/deeper/example.woff2', '../index.html', '%2e%2e/index.html', 'assets/../index.html', 'assets/%252e%252e/index.html'] as $artifact) {
      foreach ($this->paths('index.html') as $entry) {
        $path = substr($entry, 0, -strlen('index.html')) . $artifact;
        $request = Request::create($path);
        try { (new UrlMatcher($provider->getRouteCollectionForRequest($request), new RequestContext()))->match($path); self::fail('Unsafe or unsupported path matched: ' . $artifact); }
        catch (ResourceNotFoundException) { self::assertTrue(TRUE); }
      }
      try { FullSiteReviewPackage::path($artifact); self::fail('Unsafe or unsupported package path accepted.'); }
      catch (\InvalidArgumentException) { self::assertTrue(TRUE); }
    }
  }
}
