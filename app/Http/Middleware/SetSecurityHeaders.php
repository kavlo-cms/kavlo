<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', (string) config('cms.security.headers.content_security_policy'));
        $response->headers->set('Permissions-Policy', (string) config('cms.security.headers.permissions_policy'));
        $response->headers->set('Referrer-Policy', (string) config('cms.security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', (string) config('cms.security.headers.x_frame_options', 'SAMEORIGIN'));

        if ($request->isSecure()) {
            $value = 'max-age='.max(0, (int) config('cms.security.headers.hsts_max_age', 31536000));

            if ((bool) config('cms.security.headers.hsts_include_subdomains', true)) {
                $value .= '; includeSubDomains';
            }

            if ((bool) config('cms.security.headers.hsts_preload', false)) {
                $value .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $value);
        }

        return $response;
    }
}
