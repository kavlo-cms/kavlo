<?php

namespace App\Services;

use App\Models\Plugin;
use Illuminate\Support\Facades\File;

class PluginManager
{
    /**
     * Check whether a plugin is currently enabled.
     */
    public function isEnabled(string $slug): bool
    {
        try {
            return \App\Models\Plugin::where('slug', $slug)->value('is_enabled') ?? false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Discover plugins on disk, sync to DB, and boot enabled ones.
     */
    public function bootPlugins(): void
    {
        $pluginsPath = base_path('plugins');
        if (!File::exists($pluginsPath)) {
            return;
        }

        // Read which slugs are enabled from DB — skip entirely if DB isn't ready yet
        try {
            $enabledSlugs = \App\Models\Plugin::where('is_enabled', true)->pluck('slug')->flip()->toArray();
        } catch (\Throwable) {
            return;
        }

        foreach (File::directories($pluginsPath) as $directory) {
            $configPath = $directory . '/plugin.json';
            if (!File::exists($configPath)) {
                continue;
            }

            $config = json_decode(File::get($configPath), true) ?? [];
            $slug   = basename($directory);

            if (isset($enabledSlugs[$slug]) && isset($config['provider'])) {
                app()->register($config['provider']);
            }
        }
    }

    /**
     * Scan plugins dir and upsert into DB without booting — used by the admin UI.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Plugin>
     */
    public function discover(): \Illuminate\Database\Eloquent\Collection
    {
        $pluginsPath = base_path('plugins');

        if (!File::exists($pluginsPath)) {
            return Plugin::orderBy('name')->get();
        }

        $foundSlugs = [];

        foreach (File::directories($pluginsPath) as $directory) {
            $configPath = $directory . '/plugin.json';
            if (!File::exists($configPath)) {
                continue;
            }

            $config = json_decode(File::get($configPath), true) ?? [];
            $slug   = basename($directory);
            $foundSlugs[] = $slug;

            Plugin::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $config['name']        ?? ucfirst($slug),
                    'version'     => $config['version']     ?? null,
                    'description' => $config['description'] ?? null,
                    'author'      => $config['author']      ?? null,
                ]
            );
        }

        // Remove DB records for plugins no longer on disk
        Plugin::whereNotIn('slug', $foundSlugs)->delete();

        return Plugin::orderBy('name')->get();
    }
}
