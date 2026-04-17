<?php

namespace App\Services;

use App\Facades\Hook;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BlockManager
{
    public static function getAvailableBlocks(string $theme): array
    {
        $paths = Hook::applyFilters('blocks.paths', [
            base_path("themes/{$theme}/blocks"),
            base_path('blocks'),
        ]);

        $blocks = [];
        $seen   = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            foreach (File::directories($path) as $dir) {
                $type = basename($dir);

                if (isset($seen[$type])) {
                    continue;
                }

                $seen[$type] = true;

                $schema   = [];
                $jsonPath = "{$dir}/block.json";

                if (File::exists($jsonPath)) {
                    $schema = json_decode(File::get($jsonPath), true) ?? [];
                }

                $blocks[] = array_merge([
                    'type'  => $type,
                    'label' => Str::title(str_replace(['-', '_'], ' ', $type)),
                ], $schema);
            }
        }

        return Hook::applyFilters('blocks.available', $blocks);
    }
}
