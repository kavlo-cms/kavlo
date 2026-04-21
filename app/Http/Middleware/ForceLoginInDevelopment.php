<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForceLoginInDevelopment
{
    public function handle(Request $request, Closure $next): Response
    {
        $forceLoginEmail = app()->isLocal() ? env('CMS_FORCE_LOGIN_EMAIL') : null;

        if (! $forceLoginEmail || Auth::check()) {
            return $next($request);
        }

        if ($request->routeIs(
            'login',
            'login.store',
            'logout',
            'register',
            'register.store',
            'password.*',
            'verification.*',
            'two-factor.*',
            'admin.*',
        )) {
            return $next($request);
        }

        $user = User::where('email', $forceLoginEmail)->first();

        if ($user) {
            Auth::login($user);
        }

        return $next($request);
    }
}
