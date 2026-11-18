<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Clickjacking / sniffing / XSS hardening headers on every web response. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "frame-ancestors 'none'; form-action 'self'");

        return $response;
    }
}
