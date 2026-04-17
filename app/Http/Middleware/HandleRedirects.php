<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only intercept GET/HEAD requests; skip admin and API paths
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $redirect = Redirect::findForPath($request->getPathInfo());

        if ($redirect) {
            // Track hit asynchronously without blocking the response
            $redirect->increment('hits');
            $redirect->update(['last_hit_at' => now()]);

            return redirect($redirect->to_url, $redirect->type);
        }

        return $next($request);
    }
}
