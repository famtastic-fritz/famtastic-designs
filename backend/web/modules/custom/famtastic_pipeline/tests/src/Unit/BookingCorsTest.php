<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\famtastic_pipeline\StackMiddleware\BookingCors;
require_once dirname(__DIR__, 3) . '/src/StackMiddleware/BookingCors.php';
final class BookingCorsTest extends TestCase {
  private function kernel(): BookingCors {
    $app = $this->createMock(HttpKernelInterface::class);
    $app->method('handle')->willReturn(new Response('{"ok":false}', 409));
    // Include Drupal's inner CORS middleware: outer preflight must bypass its denial.
    return new BookingCors(new \Asm89\Stack\Cors($app, ['allowedOrigins'=>['https://famtasticdesigns.com'],'supportsCredentials'=>TRUE]));
  }
  public function testPublicPreflightOnly(): void {
    foreach (['https://tightenupyourlocs.com','https://www.tightenupyourlocs.com'] as $origin) {
      $r=Request::create('/api/booking-request/site-dffd4cb9c3aa47fd','OPTIONS');
      $r->headers->add(['Origin'=>$origin,'Access-Control-Request-Method'=>'POST','Access-Control-Request-Headers'=>'content-type, accept']);
      $s=$this->kernel()->handle($r); self::assertSame(204,$s->getStatusCode()); self::assertSame($origin,$s->headers->get('Access-Control-Allow-Origin')); self::assertFalse($s->headers->has('Access-Control-Allow-Credentials'));
    }
  }
  public function testPrivateRoutesOtherSitesAndOtherOriginsAreNotGranted(): void {
    foreach (['/api/customer/workspace','/api/booking-request/site-dffd4cb9c3aa47fd/owner','/api/booking-request/another-site'] as $path) {
      $r=Request::create($path,'GET'); $r->headers->set('Origin','https://tightenupyourlocs.com'); self::assertNotSame('https://tightenupyourlocs.com',$this->kernel()->handle($r)->headers->get('Access-Control-Allow-Origin'));
    }
    $r=Request::create('/api/booking-request/site-dffd4cb9c3aa47fd','POST');$r->headers->set('Origin','https://evil.example'); self::assertNotSame('https://evil.example',$this->kernel()->handle($r)->headers->get('Access-Control-Allow-Origin'));
  }
  public function testAuthHeadersAndWrongMethodsRejected(): void {
    foreach ([['POST','authorization'],['DELETE','content-type']] as [$method,$header]) {
      $r=Request::create('/api/booking-request/site-dffd4cb9c3aa47fd','OPTIONS'); $r->headers->add(['Origin'=>'https://tightenupyourlocs.com','Access-Control-Request-Method'=>$method,'Access-Control-Request-Headers'=>$header]); self::assertSame(403,$this->kernel()->handle($r)->getStatusCode());
    }
  }
  public function testActualErrorAndAvailabilityResponsesRetainReadableNoCredentialsCors(): void {
    foreach (['booking-request'=>'POST','booking-availability'=>'GET'] as $path=>$method) {
      $r=Request::create('/api/'.$path.'/site-dffd4cb9c3aa47fd',$method);$r->headers->set('Origin','https://tightenupyourlocs.com');$s=$this->kernel()->handle($r);self::assertSame(409,$s->getStatusCode());self::assertSame('https://tightenupyourlocs.com',$s->headers->get('Access-Control-Allow-Origin'));self::assertFalse($s->headers->has('Access-Control-Allow-Credentials'));self::assertStringContainsString('no-store',$s->headers->get('Cache-Control'));
    }
  }
}
