<?php

namespace App\Models;

use App\Services\SiteLocaleManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, SoftDeletes;

    public const LOCALIZED_FIELDS = [
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

    protected static ?bool $supportsEditorMode = null;

    protected ?string $resolvedLocale = null;

    protected $fillable = [
        'title', 'slug', 'type', 'editor_mode', 'is_homepage', 'content', 'is_published', 'metadata', 'blocks', 'author_id',
        'parent_id', 'order', 'meta_title', 'meta_description', 'og_image', 'publish_at', 'unpublish_at',
    ];

    protected $casts = [
        'blocks' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'unpublish_at' => 'datetime',
        'is_published' => 'boolean',
        'is_homepage' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        $logOnly = ['title', 'slug', 'is_published', 'type'];

        if (static::supportsEditorMode()) {
            $logOnly[] = 'editor_mode';
        }

        return LogOptions::defaults()
            ->logOnly($logOnly)
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public static function supportsEditorMode(): bool
    {
        return static::$supportsEditorMode ??= Schema::hasColumn((new static)->getTable(), 'editor_mode');
    }

    public static function resetEditorModeSupportCache(): void
    {
        static::$supportsEditorMode = null;
    }

    public static function sanitizePersistedAttributes(array $attributes): array
    {
        if (! static::supportsEditorMode()) {
            unset($attributes['editor_mode']);
        }

        return $attributes;
    }

    public function getEditorModeAttribute($value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return filled(trim((string) ($this->attributes['content'] ?? ''))) ? 'content' : 'builder';
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

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function translationFor(?string $locale): ?PageTranslation
    {
        $locale = app(SiteLocaleManager::class)->normalizeLocale($locale);

        if ($locale === null) {
            return null;
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    public function applyLocale(?string $locale): static
    {
        $locale = app(SiteLocaleManager::class)->normalizeLocale($locale);
        $translation = $this->translationFor($locale);

        if ($translation) {
            foreach (self::LOCALIZED_FIELDS as $field) {
                $this->setAttribute($field, $translation->getAttribute($field));
            }
        }

        $this->resolvedLocale = $locale;

        return $this;
    }

    public function resolvedLocale(): ?string
    {
        return $this->resolvedLocale;
    }

    public function localizedPath(?string $locale = null): string
    {
        $manager = app(SiteLocaleManager::class);
        $locale ??= $this->resolvedLocale;
        $resolvedLocale = $manager->normalizeLocale($locale) ?? $manager->defaultLocale();
        $defaultSlug = $this->translationFor($manager->defaultLocale())?->slug
            ?? ($this->getOriginal('slug') ?: $this->slug);
        $translation = $this->translationFor($resolvedLocale);

        if (! $manager->isDefaultLocale($resolvedLocale) && ! $translation) {
            $resolvedLocale = $manager->defaultLocale();
        }

        return $manager->pathForLocale(
            (string) ($translation?->slug ?? $defaultSlug),
            $resolvedLocale,
            (bool) $this->is_homepage,
        );
    }

    public function localizedRevisionSnapshot(?string $locale = null): array
    {
        $translation = $this->translationFor($locale);

        return [
            'locale' => app(SiteLocaleManager::class)->normalizeLocale($locale),
            'title' => $translation?->title ?? $this->title,
            'slug' => $translation?->slug ?? $this->slug,
            'type' => $this->type,
            'editor_mode' => $this->editor_mode ?? 'builder',
            'content' => $translation?->content ?? $this->content,
            'is_homepage' => (bool) $this->is_homepage,
            'is_published' => (bool) ($translation?->is_published ?? $this->is_published),
            'parent_id' => $this->parent_id,
            'meta_title' => $translation?->meta_title ?? $this->meta_title,
            'meta_description' => $translation?->meta_description ?? $this->meta_description,
            'og_image' => $translation?->og_image ?? $this->og_image,
            'publish_at' => ($translation?->publish_at ?? $this->publish_at)?->toDateTimeString(),
            'unpublish_at' => ($translation?->unpublish_at ?? $this->unpublish_at)?->toDateTimeString(),
            'published_at' => ($translation?->published_at ?? $this->published_at)?->toDateTimeString(),
        ];
    }

    public function revisionSnapshot(): array
    {
        return $this->localizedRevisionSnapshot();
    }

    public function restore(Revision $revision): bool
    {
        $attributes = [
            'blocks' => $revision->content_snapshot ?? [],
            'metadata' => $revision->meta_snapshot ?? [],
        ];

        if (is_array($revision->page_snapshot)) {
            foreach ([
                'title',
                'slug',
                'type',
                'editor_mode',
                'content',
                'is_homepage',
                'is_published',
                'parent_id',
                'meta_title',
                'meta_description',
                'og_image',
                'publish_at',
                'unpublish_at',
                'published_at',
            ] as $field) {
                if (array_key_exists($field, $revision->page_snapshot)) {
                    $attributes[$field] = $revision->page_snapshot[$field];
                }
            }
        }

        return $this->update(static::sanitizePersistedAttributes($attributes));
    }

    public static function slugSegment(?string $slug, string $fallbackTitle): string
    {
        $segments = array_values(array_filter(explode('/', (string) $slug)));
        $segment = end($segments) ?: Str::slug($fallbackTitle);

        return (string) $segment;
    }
}
