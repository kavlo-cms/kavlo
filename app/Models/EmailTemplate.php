<?php

namespace App\Models;

use App\Services\EmailTemplateBuilder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EmailTemplate extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'context_key',
        'subject',
        'blocks',
    ];

    protected $casts = [
        'blocks' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'context_key', 'subject'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function editorBlocks(): array
    {
        return app(EmailTemplateBuilder::class)->normalizeBlocks($this->blocks ?? []);
    }
}
