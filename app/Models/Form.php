<?php

namespace App\Models;

use App\Services\FormBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Form extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'success_message',
        'redirect_url',
        'notify_email',
        'blocks',
        'submission_action',
        'action_config',
    ];

    protected $casts = [
        'blocks' => 'array',
        'action_config' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function editorBlocks(): array
    {
        return FormBuilder::editorBlocks($this);
    }

    public function submissionFields(): array
    {
        return FormBuilder::submissionFields($this);
    }

    public function resolvedSubmissionAction(): string
    {
        return FormBuilder::resolvedActionKey($this);
    }

    public function resolvedActionConfig(): array
    {
        return FormBuilder::resolvedActionConfig($this);
    }
}
