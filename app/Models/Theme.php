<?php

namespace App\Models;

use App\Services\PublicPageCache;
use App\Services\ThemeManifest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class Theme extends Model
{
    public const DEFAULT_THEME_SLUG = 'midnight-blue';

    protected $fillable = [
        'name',
        'slug',
        'path',
        'is_active',
        'settings', // Good to add this now since we planned for it earlier
        'version',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the full path to the theme's view directory.
     */
    public function getViewPath(): string
    {
        return base_path("themes/{$this->slug}/views");
    }

    /**
     * Set this theme as the active one and deactivate others.
     */
    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);

        Cache::forget('active_theme_slug');
        app(PublicPageCache::class)->flush();
    }

    /**
     * Read and decode this theme's theme.json config file.
     *
     * @return array<string,mixed>
     */
    public function getConfig(): array
    {
        $configPath = base_path("themes/{$this->slug}/theme.json");

        return app(ThemeManifest::class)->loadFromPath($configPath);
    }

    public static function discover(): void
    {
        $themePath = base_path('themes');
        if (! File::exists($themePath)) {
            return;
        }

        $directories = File::directories($themePath);
        $foundSlugs = [];

        foreach ($directories as $directory) {
            $slug = basename($directory);
            $foundSlugs[] = $slug;

            // Try to read theme.json for the display name
            $configPath = "{$directory}/theme.json";
            $name = ucfirst($slug);

            if (File::exists($configPath)) {
                $config = json_decode(File::get($configPath), true);
                $name = $config['name'] ?? $name;
                $version = $config['version'] ?? null;
            }

            static::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'path' => $directory, 'version' => $version ?? null]
            );
        }

        // Optional: Delete themes from DB that no longer exist on disk
        static::whereNotIn('slug', $foundSlugs)->delete();

        if (! static::where('is_active', true)->exists()) {
            $defaultTheme = static::where('slug', self::DEFAULT_THEME_SLUG)->first()
                ?? static::orderBy('name')->first();

            if ($defaultTheme) {
                $defaultTheme->activate();
            }
        }
    }
}
