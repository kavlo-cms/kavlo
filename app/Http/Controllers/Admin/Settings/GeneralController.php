<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::allCached();

        $pages = Page::select('id', 'title')
            ->orderBy('title')
            ->get();

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'pages'    => $pages,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name'         => ['required', 'string', 'max:255'],
            'site_tagline'      => ['nullable', 'string', 'max:255'],
            'admin_email'       => ['nullable', 'email', 'max:255'],
            'meta_title_format' => ['nullable', 'string', 'max:255'],
            'meta_description'  => ['nullable', 'string', 'max:500'],
            'homepage_id'       => ['nullable', 'integer', 'exists:pages,id'],
            'favicon'           => ['nullable', 'string', 'max:500'],
            'head_scripts'      => ['nullable', 'string'],
            'body_scripts'      => ['nullable', 'string'],
        ]);

        Setting::setMany($validated);

        return back()->with('success', 'Settings saved.');
    }
}
