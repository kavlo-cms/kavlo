<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyPermission([
            'view pages',
            'view media',
            'view menus',
            'view settings',
            'view users',
            'view datahub',
            'view forms',
            'view email templates',
            'view redirects',
            'view scripts',
            'view themes',
            'view plugins',
            'view analytics',
            'view activity log',
            'manage backups',
            'manage cache',
            'manage maintenance',
        ])) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
