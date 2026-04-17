<?php

namespace App\GraphQL\Queries;

use App\Services\ContentRouteRegistry;

class ContentQuery
{
    public function route($_, array $args, ContentRouteRegistry $registry): ?array
    {
        return $registry->route((string) $args['path'], $this->previewEnabled($args));
    }

    public function routes($_, array $args, ContentRouteRegistry $registry): array
    {
        return $registry->routes($args['types'] ?? [], $this->previewEnabled($args));
    }

    public function page($_, array $args, ContentRouteRegistry $registry): ?array
    {
        $page = $registry->resolvePage($this->pagePath($args), $this->previewEnabled($args));

        return $page ? $registry->pagePayload($page) : null;
    }

    public function menu($_, array $args, ContentRouteRegistry $registry): ?array
    {
        $menu = $registry->resolveMenu((string) $args['slug']);

        return $menu ? $registry->menuPayload($menu) : null;
    }

    public function form($_, array $args, ContentRouteRegistry $registry): ?array
    {
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

        return '/' . trim($slug, '/');
    }

    protected function previewEnabled(array $args): bool
    {
        if (! ($args['preview'] ?? false)) {
            return false;
        }

        $user = auth()->user();

        return (bool) ($user?->hasAnyRole(['super-admin', 'admin', 'editor']));
    }
}
