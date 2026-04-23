<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    protected $fillable = [
        'page_id',
        'locale',
        'title',
        'slug',
        'content',
        'is_published',
        'metadata',
        'blocks',
        'meta_title',
        'meta_description',
        'og_image',
        'publish_at',
        'unpublish_at',
        'published_at',
    ];

    protected $casts = [
        'blocks' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'unpublish_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
