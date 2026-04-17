<?php

namespace App\Services;

use App\Facades\Hook;
use App\Models\Theme;

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
        $types = collect(self::$coreTypes)->keyBy('type');

        $activeTheme = Theme::where('is_active', true)->value('slug');

        if ($activeTheme) {
            $configPath = base_path("themes/{$activeTheme}/theme.json");

            if (file_exists($configPath)) {
                $config = json_decode(file_get_contents($configPath), true) ?? [];

                foreach ($config['pageTypes'] ?? [] as $themeType) {
                    if (!empty($themeType['type'])) {
                        $types->put($themeType['type'], $themeType);
                    }
                }
            }
        }

        $result = Hook::applyFilters('page_types', $types->values()->all());

        return $result;
    }

    /**
     * Return the Blade view name for a given type, falling back to 'pages.show'.
     */
    public static function viewFor(string $type): string
    {
        $found = collect(self::all())->firstWhere('type', $type);

        return $found['view'] ?? 'pages.show';
    }
}
