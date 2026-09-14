<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class OwnerOnly {
    public function handle(Request $request, Closure $next) {
        abort_unless($request->user()?->is_owner && $request->user()?->email_verified_at, 403);
        return $next($request);
    }
}
