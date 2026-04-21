<?php

namespace App\Services;

use App\Facades\Hook;
use App\Models\Form;
use App\Models\Menu;
use App\Models\Page;

class DataHubRegistry
{
    public function __construct(
        protected ContentRouteRegistry $routes,
    ) {}

    public function channels(): array
    {
        $channels = [
            [
                'key' => 'public-graphql',
                'label' => 'Public GraphQL',
                'type' => 'graphql',
                'endpoint' => url('/graphql'),
                'path' => '/graphql',
                'ide_path' => '/graphiql',
                'ide_url' => url('/graphiql'),
                'description' => 'Read-only content delivery channel backed by the cached route manifest.',
                'visibility' => 'Public queries, preview-aware for signed-in admins/editors',
                'queries' => [
                    ['key' => 'route', 'description' => 'Resolve a single content route by path.'],
                    ['key' => 'routes', 'description' => 'List route manifest entries by content type.'],
                    ['key' => 'page', 'description' => 'Fetch a page payload by path or slug.'],
                    ['key' => 'menu', 'description' => 'Fetch a menu tree by slug.'],
                    ['key' => 'form', 'description' => 'Fetch a form schema by slug.'],
                ],
            ],
        ];

        return Hook::applyFilters('datahub.channels', $channels);
    }

    public function resources(): array
    {
        $routeEntries = $this->routes->routes(['page', 'menu', 'form'], true);

        $routeCounts = [
            'page' => count(array_filter($routeEntries, fn (array $route) => $route['type'] === 'page' && ! ($route['is_alias'] ?? false))),
            'menu' => count(array_filter($routeEntries, fn (array $route) => $route['type'] === 'menu')),
            'form' => count(array_filter($routeEntries, fn (array $route) => $route['type'] === 'form')),
        ];

        $resources = [
            [
                'key' => 'page',
                'label' => 'Pages',
                'source' => 'core',
                'model' => Page::class,
                'graphql_type' => 'Page',
                'description' => 'Website pages with blocks, metadata, publish state, and preview support.',
                'record_count' => Page::count(),
                'generated_routes' => $routeCounts['page'],
                'supports' => ['routes', 'graphql', 'preview'],
                'fields' => ['title', 'slug', 'type', 'blocks', 'metadata', 'meta_title'],
            ],
            [
                'key' => 'menu',
                'label' => 'Menus',
                'source' => 'core',
                'model' => Menu::class,
                'graphql_type' => 'Menu',
                'description' => 'Navigation trees that can be delivered through themes and GraphQL clients.',
                'record_count' => Menu::count(),
                'generated_routes' => $routeCounts['menu'],
                'supports' => ['graphql'],
                'fields' => ['name', 'slug', 'items', 'target', 'page_id'],
            ],
            [
                'key' => 'form',
                'label' => 'Forms',
                'source' => 'core',
                'model' => Form::class,
                'graphql_type' => 'Form',
                'description' => 'Builder-backed form schemas and submission endpoints exposed as structured content.',
                'record_count' => Form::count(),
                'generated_routes' => $routeCounts['form'],
                'supports' => ['routes', 'graphql'],
                'fields' => ['name', 'slug', 'description', 'blocks', 'submission_action'],
            ],
        ];

        return Hook::applyFilters('datahub.resources', $resources);
    }

    public function hooks(): array
    {
        return [
            [
                'hook' => 'datahub.channels',
                'description' => 'Register additional delivery channels such as private GraphQL endpoints or future REST feeds.',
            ],
            [
                'hook' => 'datahub.resources',
                'description' => 'Register routeable/exposable resources so plugins and themes can join the DataHub.',
            ],
        ];
    }
}
