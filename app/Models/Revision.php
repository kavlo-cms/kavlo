<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Revision extends Model
{
    protected $fillable = [
        'page_id',
        'locale',
        'user_id',
        'content_snapshot',
        'meta_snapshot',
        'page_snapshot',
        'label',
    ];

    protected $casts = [
        'content_snapshot' => 'array',
        'meta_snapshot' => 'array',
        'page_snapshot' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
