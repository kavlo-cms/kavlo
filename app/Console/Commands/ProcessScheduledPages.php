<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\ContentRouteRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessScheduledPages extends Command
{
    protected $signature = 'pages:process-scheduled';

    protected $description = 'Publish or unpublish pages based on their scheduled times';

    public function handle(): void
    {
        $now = now();

        // Publish scheduled pages
        $toPublish = Page::whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->where('is_published', false)
            ->get();

        foreach ($toPublish as $page) {
            $page->update([
                'is_published' => true,
                'published_at' => $page->publish_at,
                'publish_at' => null,
            ]);
            $this->line("Published: {$page->title}");
        }

        // Unpublish scheduled pages
        $toUnpublish = Page::whereNotNull('unpublish_at')
            ->where('unpublish_at', '<=', $now)
            ->where('is_published', true)
            ->get();

        foreach ($toUnpublish as $page) {
            $page->update([
                'is_published' => false,
                'unpublish_at' => null,
            ]);
            $this->line("Unpublished: {$page->title}");
        }

        Cache::forget('sitemap');
        app(ContentRouteRegistry::class)->forget();
    }
}
