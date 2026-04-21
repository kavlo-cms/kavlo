<?php

namespace App\GraphQL\Queries;

use App\Models\ApiKey;
use App\Services\ApiKeyManager;
use App\Services\ContentRouteRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ContentQuery
{
    public function route($_, array $args, GraphQLContext $context): ?array
    {
        $registry = app(ContentRouteRegistry::class);

        return $registry->route((string) $args['path'], $this->previewEnabled($args, $context));
    }

    public function routes($_, array $args, GraphQLContext $context): array
    {
        $registry = app(ContentRouteRegistry::class);

        return $registry->routes($args['types'] ?? [], $this->previewEnabled($args, $context));
    }

    public function page($_, array $args, GraphQLContext $context): ?array
    {
        $registry = app(ContentRouteRegistry::class);
        $page = $registry->resolvePage($this->pagePath($args), $this->previewEnabled($args, $context));

        return $page ? $registry->pagePayload($page) : null;
    }

    public function menu($_, array $args, GraphQLContext $context): ?array
    {
        $registry = app(ContentRouteRegistry::class);
        $menu = $registry->resolveMenu((string) $args['slug']);

        return $menu ? $registry->menuPayload($menu) : null;
    }

    public function form($_, array $args, GraphQLContext $context): ?array
    {
        $registry = app(ContentRouteRegistry::class);
        $form = $registry->resolveForm((string) $args['slug']);

        return $form ? $registry->formPayload($form) : null;
    }

    protected function pagePath(array $args): string
    {
        $path = trim((string) ($args['path'] ?? ''));

        if ($path !== '') {
            return $path;
        }

        $slug = trim((string) ($args['slug'] ?? ''));

        if ($slug === '' || $slug === '/') {
            return '/';
        }

        return '/'.trim($slug, '/');
    }

    protected function previewEnabled(array $args, ?GraphQLContext $context = null): bool
    {
        if (! ($args['preview'] ?? false)) {
            return false;
        }

        $request = $context?->request() ?? request();

        /** @var ApiKey|null $requestApiKey */
        $requestApiKey = $request?->attributes->get('current_api_key');

        if ($requestApiKey) {
            return $requestApiKey->hasAbility('graphql.preview');
        }

        $token = $request?->header('X-API-Key') ?: $request?->bearerToken();

        if ($token) {
            $resolvedApiKey = app(ApiKeyManager::class)->findFromToken($token);

            if ($resolvedApiKey) {
                return $resolvedApiKey->hasAbility('graphql.preview');
            }
        }

        $user = $context?->user() ?? auth()->user();

        if ($user?->getAttribute('authenticated_via_api_key')) {
            return in_array('graphql.preview', $user->getAttribute('current_api_key_abilities') ?? [], true);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = app()->bound('cms.current_api_key')
            ? app('cms.current_api_key')
            : null;

        if ($apiKey) {
            return $apiKey->hasAbility('graphql.preview');
        }

        return (bool) ($user?->can('preview datahub'));
    }
}
