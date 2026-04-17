<?php

namespace App\Http\Controllers\Admin;

use App\Services\BlockManager;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class BlocksController extends Controller
{
    public function index(): Response
    {
        $activeTheme = Cache::get('active_theme_slug')
            ?? \App\Models\Theme::where('is_active', true)->value('slug')
            ?? '';

        $blocks = BlockManager::getAvailableBlocks($activeTheme);

        // Attach source label if not set in block.json
        $blocks = array_map(function (array $block) use ($activeTheme) {
            if (empty($block['source'])) {
                $block['source'] = 'core';
            }
            return $block;
        }, $blocks);

        // Group by 'group' key, preserving order: text → layout → media → components → other
        $groupOrder = ['text', 'layout', 'media', 'components'];
        $grouped = [];

        foreach ($blocks as $block) {
            $group = $block['group'] ?? 'other';
            $grouped[$group][] = $block;
        }

        uksort($grouped, function ($a, $b) use ($groupOrder) {
            $ai = array_search($a, $groupOrder);
            $bi = array_search($b, $groupOrder);
            $ai = $ai === false ? 99 : $ai;
            $bi = $bi === false ? 99 : $bi;
            return $ai <=> $bi;
        });

        return Inertia::render('Blocks/Index', [
            'grouped'      => $grouped,
            'activeTheme'  => $activeTheme,
            'totalBlocks'  => count($blocks),
        ]);
    }
}
