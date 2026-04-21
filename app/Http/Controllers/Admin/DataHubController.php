<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ContentRouteRegistry;
use App\Services\DataHubRegistry;
use Inertia\Inertia;
use Inertia\Response;

class DataHubController extends Controller
{
    public function index(DataHubRegistry $dataHub, ContentRouteRegistry $routes): Response
    {
        $routeEntries = array_values(array_map(function (array $route) {
            return [
                'type' => $route['type'],
                'label' => $route['label'],
                'key' => $route['key'],
                'path' => $route['path'],
                'published' => (bool) $route['published'],
                'updated_at' => $route['updated_at'],
            ];
        }, $routes->routes(['page', 'menu', 'form'], true)));

        return Inertia::render('DataHub/Index', [
            'summary' => [
                'channels' => count($dataHub->channels()),
                'resources' => count($dataHub->resources()),
                'routes' => count($routeEntries),
            ],
            'channels' => $dataHub->channels(),
            'resources' => $dataHub->resources(),
            'routes' => $routeEntries,
            'hooks' => $dataHub->hooks(),
            'commands' => [
                'lando artisan kavlo:routes-cache',
                'lando artisan route:list --path=graphql',
                'lando artisan route:list --path=graphiql',
                'lando artisan lighthouse:print-schema',
                'lando artisan graphiql:download-assets',
            ],
        ]);
    }
}
