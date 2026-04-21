<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ThemesController extends Controller
{
    public function index(): Response
    {
        Theme::discover();

        $themes = Theme::orderBy('name')->get()->map(function (Theme $theme) {
            $config = $theme->getConfig();

            return [
                'id' => $theme->id,
                'name' => $theme->name,
                'slug' => $theme->slug,
                'version' => $theme->version,
                'is_active' => $theme->is_active,
                'description' => $config['description'] ?? null,
                'author' => $config['author'] ?? null,
                'preview' => $config['preview'] ?? null,
            ];
        });

        return Inertia::render('Settings/Themes', [
            'themes' => $themes,
        ]);
    }

    public function activate(Theme $theme): RedirectResponse
    {
        $theme->activate();

        return back()->with('success', "\"{$theme->name}\" is now the active theme.");
    }
}
