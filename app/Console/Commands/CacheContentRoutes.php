<?php

namespace App\Console\Commands;

use App\Services\ContentRouteRegistry;
use Illuminate\Console\Command;

class CacheContentRoutes extends Command
{
    protected $signature = 'cms:routes-cache {--clear : Remove the cached manifest instead of rebuilding it}';

    protected $description = 'Build or clear the cached CMS content route manifest';

    public function handle(ContentRouteRegistry $registry): int
    {
        if ($this->option('clear')) {
            $registry->forget();
            $this->info('Content route manifest cleared.');

            return self::SUCCESS;
        }

        $manifest = $registry->refresh();

        $this->info(sprintf(
            'Content route manifest cached: %d routes (%d pages, %d forms, %d menus).',
            count($manifest['routes'] ?? []),
            count($manifest['page_paths'] ?? []),
            count($manifest['forms'] ?? []),
            count($manifest['menus'] ?? []),
        ));

        return self::SUCCESS;
    }
}
