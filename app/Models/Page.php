<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Support\LogOptions;

class Page extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'type', 'is_homepage', 'content', 'is_published', 'metadata', 'blocks', 'author_id',
        'parent_id', 'order', 'meta_title', 'meta_description', 'og_image', 'publish_at', 'unpublish_at',
    ];

    protected $casts = [
        'blocks'       => 'array',
        'metadata'     => 'array',
        'published_at' => 'datetime',
        'publish_at'   => 'datetime',
        'unpublish_at' => 'datetime',
        'is_published' => 'boolean',
        'is_homepage'  => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'is_published', 'type'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }

    public function metadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    public function revisions()
    {
        return $this->hasMany(Revision::class)->latest();
    }

    public function restore(Revision $revision): bool
    {
        return $this->update([
            'blocks'   => $revision->content_snapshot,
            'metadata' => $revision->meta_snapshot,
        ]);
    }
}
