<?php

namespace App\Observers;

use App\Models\Page;
use App\Services\SiteLocaleManager;
use Illuminate\Support\Facades\Schema;

class PageObserver
{
    public function saved(Page $page): void
    {
        if (! Schema::hasTable('page_translations')) {
            return;
        }

        $page->translations()->updateOrCreate(
            ['locale' => app(SiteLocaleManager::class)->defaultLocale()],
            [
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'is_published' => $page->is_published,
                'metadata' => $page->metadata ?? [],
                'blocks' => $page->blocks ?? [],
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'og_image' => $page->og_image,
                'publish_at' => $page->publish_at,
                'unpublish_at' => $page->unpublish_at,
                'published_at' => $page->published_at,
            ],
        );
    }

    public function updating(Page $page)
    {
        // Only create a revision if the content actually changed
        if ($page->isDirty(['blocks', 'metadata'])) {
            $page->revisions()->create([
                'locale' => app(SiteLocaleManager::class)->defaultLocale(),
                'user_id' => auth()->id(),
                'content_snapshot' => $page->getOriginal('blocks'),
                'meta_snapshot' => $page->getOriginal('metadata'),
                'page_snapshot' => $page->revisionSnapshot(),
            ]);
        }
    }
}
