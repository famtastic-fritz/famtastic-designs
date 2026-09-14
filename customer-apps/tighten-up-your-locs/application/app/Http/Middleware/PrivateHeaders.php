<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class PrivateHeaders {
    public function handle(Request $request, Closure $next) {
        if(app()->environment('production')) abort_unless(in_array($request->getHost(),['tightenupyourlocs.com','www.tightenupyourlocs.com'],true),400);
        $response = $next($request);
        $response->headers->set('Cache-Control','no-store, private');
        $response->headers->set('X-Robots-Tag','noindex, nofollow');
        $response->headers->set('Referrer-Policy','no-referrer');
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        return $response;
    }
}
