<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\StackMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** Credential-free access to only this customer's public capture/read routes. */
final class BookingCors implements HttpKernelInterface {
  public function __construct(private readonly HttpKernelInterface $httpKernel) {}

  public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = TRUE): Response {
    $origin = $request->headers->get('Origin');
    $path = $request->getPathInfo();
    $methods = [
      '/api/booking-request/site-dffd4cb9c3aa47fd' => 'POST',
      '/api/booking-availability/site-dffd4cb9c3aa47fd' => 'GET',
    ];
    if ($type !== self::MAIN_REQUEST || !in_array($origin, ['https://tightenupyourlocs.com', 'https://www.tightenupyourlocs.com'], TRUE) || !isset($methods[$path])) {
      return $this->httpKernel->handle($request, $type, $catch);
    }
    $method = $methods[$path];
    if ($request->isMethod('OPTIONS')) {
      $headers = array_filter(array_map('trim', explode(',', strtolower($request->headers->get('Access-Control-Request-Headers', '')))));
      if ($request->headers->get('Access-Control-Request-Method') !== $method || array_diff($headers, ['content-type', 'accept'])) {
        return new Response('', 403, ['Cache-Control' => 'private, no-store', 'Vary' => 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers']);
      }
      $response = new Response('', 204);
      $response->headers->set('Access-Control-Allow-Methods', $method);
      $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
      $response->headers->set('Access-Control-Max-Age', '300');
    }
    else {
      if (!$request->isMethod($method)) return $this->httpKernel->handle($request, $type, $catch);
      $response = $this->httpKernel->handle($request, $type, $catch);
    }
    $response->headers->set('Access-Control-Allow-Origin', $origin);
    $response->headers->remove('Access-Control-Allow-Credentials');
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->setVary(['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers'], FALSE);
    return $response;
  }
}
