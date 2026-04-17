<?php

namespace App\Observers;

use App\Models\Page;

class PageObserver
{
    public function updating(Page $page)
    {
        // Only create a revision if the content actually changed
        if ($page->isDirty(['blocks', 'metadata'])) {
            $page->revisions()->create([
                'user_id' => auth()->id(),
                'content_snapshot' => $page->getOriginal('blocks'),
                'meta_snapshot' => $page->getOriginal('metadata'),
            ]);
        }
    }
}
