<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteScript extends Model
{
    protected $fillable = [
        'name',
        'placement',
        'source_type',
        'source_url',
        'file_path',
        'inline_content',
        'load_strategy',
        'sort_order',
        'is_enabled',
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
