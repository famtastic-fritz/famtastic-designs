<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\AcquisitionIndustryPreviewController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once dirname(__DIR__, 3) . '/src/Controller/AcquisitionIndustryPreviewController.php';

#[Group('famtastic_pipeline')]
final class AcquisitionIndustryPreviewControllerTest extends UnitTestCase {

  public function testHvacHasIndependentFlagAndExactPublicAssetAllowlist(): void {
    new Settings(['famtastic_acquisition_hvac_preview_enabled' => TRUE]);
    $this->installPrefixedUrlGenerator();
    $controller = new AcquisitionIndustryPreviewController();
    $response = $controller->preview('hvac-coastal-current', 'lab');
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('Your HVAC company', (string) $response->getContent());
    self::assertStringNotContainsString('{{business_name}}', (string) $response->getContent());
    self::assertStringNotContainsString('__HVAC_CONTINUATION__', (string) $response->getContent());
    self::assertSame(404, $controller->preview('hvac-coastal-current', 'email')->getStatusCode());
    self::assertSame(404, $controller->preview('mobile-detailing', 'lab')->getStatusCode());
    self::assertSame(404, $controller->asset('hvac-coastal-current', 'manifest.json')->getStatusCode());
    self::assertSame(404, $controller->asset('hvac-coastal-current', '../lab.css')->getStatusCode());
    self::assertSame(200, $controller->asset('hvac-coastal-current', 'maintenance.webp')->getStatusCode());
    self::assertSame('image/webp', $controller->asset('hvac-coastal-current', 'maintenance.webp')->headers->get('Content-Type'));
    self::assertSame('font/woff2', $controller->asset('hvac-coastal-current', 'fraunces.woff2')->headers->get('Content-Type'));
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testLocalCandidateRouteIsOffByDefaultAndMissingUnknownSlugs(): void {
    new Settings([]);
    $controller = new AcquisitionIndustryPreviewController();

    self::assertSame(404, $controller->preview('mobile-detailing', 'lab')->getStatusCode());

    new Settings(['famtastic_acquisition_industry_previews_enabled' => TRUE]);
    self::assertSame(404, $controller->preview('other-industry', 'lab')->getStatusCode());
    self::assertSame(404, $controller->preview('mobile-detailing', 'send')->getStatusCode());
    self::assertSame(404, $controller->asset('mobile-detailing', '../email.html')->getStatusCode());
  }

  public function testEnabledHtmlUsesBasePathAwareRoutesAndRestrictiveCsp(): void {
    new Settings(['famtastic_acquisition_industry_previews_enabled' => TRUE]);
    $this->installPrefixedUrlGenerator();

    $controller = new AcquisitionIndustryPreviewController();
    $response = $controller->preview('mobile-detailing', 'lab');
    self::assertSame(200, $response->getStatusCode());
    $html = (string) $response->getContent();
    self::assertStringContainsString('href="/web/samples/industry/mobile-detailing/assets/lab.css"', $html);
    self::assertStringContainsString('src="/web/samples/industry/mobile-detailing/assets/lab.js"', $html);
    self::assertStringContainsString('/web/samples/industry-assets/famtastic-designs-logo-v1.png', $html);
    self::assertStringContainsString('connect-src \'none\'', (string) $response->headers->get('Content-Security-Policy'));
    self::assertStringContainsString('form-action \'none\'', (string) $response->headers->get('Content-Security-Policy'));
    self::assertStringContainsString("script-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html);
    self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    self::assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
  }

  public function testEnabledAssetsAreReadOnlyAndServeOnlyApprovedFiles(): void {
    new Settings(['famtastic_acquisition_industry_previews_enabled' => TRUE]);
    $this->installPrefixedUrlGenerator();
    $controller = new AcquisitionIndustryPreviewController();

    $previewRoot = dirname(__DIR__, 8) . '/marketing/campaigns/acquisition-199/industry-previews';
    $industries = [
      'mobile-detailing', 'fitness-meal-prep', 'photography-media',
      'baking-catering', 'home-services', 'events-rentals',
      'pet-services', 'consulting-tutoring', 'handcrafted-boutiques',
    ];
    foreach ($industries as $industry) {
      $directory = $previewRoot . '/' . $industry;
      if (!is_dir($directory)) {
        continue;
      }
      foreach (['email.html', 'email.txt', 'email-images-blocked.html', 'lab.html', 'lab.css', 'lab.js'] as $required) {
        self::assertFileExists($directory . '/' . $required, $industry . ' missing ' . $required);
      }
      $html = file_get_contents($directory . '/lab.html');
      self::assertIsString($html);
      self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html, $industry . ' has inline event handler');

      $emailResponse = $controller->preview($industry, 'email');
      self::assertSame(200, $emailResponse->getStatusCode(), $industry . ' email');
      $emailHtml = (string) $emailResponse->getContent();
      self::assertStringContainsString('/web/samples/industry-assets/famtastic-designs-logo-v1.png', $emailHtml, $industry . ' logo route');
      self::assertStringContainsString('/web/samples/industry-assets/connect-qr.png', $emailHtml, $industry . ' QR route');
      self::assertDoesNotMatchRegularExpression('/(?:\.\.\/)+generic-review\/assets\/(?:famtastic-designs-logo-v1\.png|connect-qr\.png)/', $emailHtml, $industry . ' email brand assets must be base-path-aware');

      $script = $controller->asset($industry, 'lab.js');
      self::assertSame(200, $script->getStatusCode(), $industry . ' JS');
      self::assertSame('application/javascript; charset=UTF-8', $script->headers->get('Content-Type'));
      self::assertDoesNotMatchRegularExpression('/\bfetch\s*\(|\bXMLHttpRequest\b|\blocalStorage\b|\bsessionStorage\b|\bsendBeacon\s*\(/i', (string) $script->getContent(), $industry . ' JS must remain local');

      $css = $controller->asset($industry, 'lab.css');
      self::assertSame(200, $css->getStatusCode(), $industry . ' CSS');
      $cssContent = (string) $css->getContent();
      self::assertStringContainsString('/web/samples/industry-assets/', $cssContent, $industry . ' font URLs must honor the install base path');
      self::assertDoesNotMatchRegularExpression('/url\([^)]*generic-review\/assets\/(?:metropolis|lora)-/', $cssContent, $industry . ' CSS font assets must use the common route');

      foreach (glob($directory . '/*') ?: [] as $candidate) {
        $asset = basename($candidate);
        if (preg_match('/\.(?:svg|png|jpe?g|webp|woff2)$/i', $asset) !== 1) {
          continue;
        }
        self::assertSame(200, $controller->asset($industry, $asset)->getStatusCode(), $industry . ' asset ' . $asset);
      }
    }

    foreach (['famtastic-designs-logo-v1.png', 'connect-qr.png', 'metropolis-regular.woff2', 'lora-bold.woff2'] as $asset) {
      self::assertSame(200, $controller->commonAsset($asset)->getStatusCode(), $asset);
    }
    self::assertSame(404, $controller->commonAsset('../settings.php')->getStatusCode());
  }

  private function installPrefixedUrlGenerator(): void {
    $container = new ContainerBuilder();
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturnCallback(static function (string $name, array $parameters): string {
      return match ($name) {
        'famtastic_pipeline.acquisition_industry_preview' => '/web/samples/industry/' . $parameters['industry'] . '/' . $parameters['view'],
        'famtastic_pipeline.acquisition_industry_preview_asset' => '/web/samples/industry/' . $parameters['industry'] . '/assets/' . $parameters['asset'],
        'famtastic_pipeline.acquisition_industry_preview_common_asset' => '/web/samples/industry-assets/' . $parameters['asset'],
        default => throw new \LogicException('Unexpected route: ' . $name),
      };
    });
    $container->set('url_generator', $generator);
    \Drupal::setContainer($container);
  }

}
