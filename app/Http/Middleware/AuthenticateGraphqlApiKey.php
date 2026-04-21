<?php

namespace App\Http\Middleware;

use App\Services\ApiKeyManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGraphqlApiKey
{
    public function __construct(
        protected ApiKeyManager $apiKeys,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-Key') ?: $request->bearerToken();

        if (! $token) {
            return $next($request);
        }

        $apiKey = $this->apiKeys->findFromToken($token);

        if (! $apiKey || ! $apiKey->user) {
            return $this->errorResponse('Invalid API key.', 401);
        }

        if (! $apiKey->hasAbility('graphql.read')) {
            return $this->errorResponse('This API key does not allow GraphQL access.', 403);
        }

        $this->apiKeys->markUsed($apiKey, $request);
        $user = $apiKey->user;
        $user->setAttribute('authenticated_via_api_key', true);
        $user->setAttribute('current_api_key_abilities', $apiKey->abilities ?? []);

        Auth::shouldUse(config('auth.defaults.guard'));
        Auth::guard()->setUser($user);

        app()->instance('cms.current_api_key', $apiKey);
        $request->attributes->set('current_api_key', $apiKey);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    protected function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'errors' => [
                ['message' => $message],
            ],
        ], $status);
    }
}
