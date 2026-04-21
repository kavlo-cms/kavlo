<?php

namespace App\Services;

use App\Facades\Hook;
use App\Models\Theme;
use Illuminate\Support\Str;

class PageTypeManager
{
    /**
     * Core page types always available regardless of theme.
     */
    private static array $coreTypes = [
        ['type' => 'page', 'label' => 'Standard Page', 'view' => 'pages.show'],
    ];

    /**
     * Return all registered page types for the active theme.
     * Theme-declared types override core types with the same slug.
     * Plugins can add or modify types via the 'page_types' filter.
     */
    public static function all(): array
    {
        $types = collect(self::$coreTypes)
            ->mapWithKeys(fn (array $type) => [
                $type['type'] => self::normalizeType($type, 'core', 'Core'),
            ]);

        $activeTheme = Theme::query()
            ->select('slug', 'name')
            ->where('is_active', true)
            ->first();

        if ($activeTheme) {
            $configPath = base_path("themes/{$activeTheme->slug}/theme.json");
            $config = app(ThemeManifest::class)->loadFromPath($configPath);

            foreach ($config['pageTypes'] ?? [] as $themeType) {
                if (! empty($themeType['type'])) {
                    $types->put(
                        $themeType['type'],
                        self::normalizeType(
                            $themeType,
                            'theme:'.$activeTheme->slug,
                            'Theme: '.$activeTheme->name,
                        ),
                    );
                }
            }
        }

        $result = Hook::applyFilters('page_types', $types->values()->all());

        return collect($result)
            ->filter(fn (mixed $type) => is_array($type) && ! empty($type['type']) && ! empty($type['label']) && ! empty($type['view']))
            ->map(fn (array $type) => self::normalizeType($type))
            ->values()
            ->all();
    }

    /**
     * Return the Blade view name for a given type, falling back to 'pages.show'.
     */
    public static function viewFor(string $type): string
    {
        $found = collect(self::all())->firstWhere('type', $type);

        return $found['view'] ?? 'pages.show';
    }

    /**
     * @param  array<string, mixed>  $type
     * @return array<string, mixed>
     */
    private static function normalizeType(array $type, string $defaultSource = 'custom', ?string $defaultSourceLabel = null): array
    {
        $normalized = $type;
        $normalized['type'] = (string) ($type['type'] ?? '');
        $normalized['label'] = (string) ($type['label'] ?? '');
        $normalized['view'] = (string) ($type['view'] ?? 'pages.show');
        $normalized['description'] = filled($type['description'] ?? null)
            ? (string) $type['description']
            : null;
        $normalized['source'] = (string) ($type['source'] ?? $defaultSource);
        $normalized['source_label'] = (string) ($type['source_label'] ?? $defaultSourceLabel ?? self::sourceLabelFor($normalized['source']));

        return $normalized;
    }

    private static function sourceLabelFor(string $source): string
    {
        if ($source === 'core') {
            return 'Core';
        }

        if (str_starts_with($source, 'theme:')) {
            return 'Theme: '.Str::headline(Str::after($source, 'theme:'));
        }

        return 'Plugin: '.Str::headline($source);
    }
}
