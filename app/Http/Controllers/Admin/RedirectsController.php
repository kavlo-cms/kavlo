<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RedirectsController extends Controller
{
    public function index(): Response
    {
        $redirects = Redirect::orderByDesc('updated_at')->get();

        $pages = Page::select('id', 'title', 'slug')->orderBy('title')->get();

        return Inertia::render('Redirects/Index', [
            'redirects' => $redirects,
            'pages' => $pages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_url' => ['required', 'string', 'max:2048', 'unique:redirects,from_url'],
            'to_url' => ['required', 'string', 'max:2048'],
            'type' => ['required', 'in:301,302'],
            'is_active' => ['boolean'],
        ]);

        $validated['from_url'] = Redirect::normalizePath($validated['from_url']);

        Redirect::create($validated);

        return back()->with('success', 'Redirect created.');
    }

    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        $validated = $request->validate([
            'from_url' => ['required', 'string', 'max:2048', 'unique:redirects,from_url,'.$redirect->id],
            'to_url' => ['required', 'string', 'max:2048'],
            'type' => ['required', 'in:301,302'],
            'is_active' => ['boolean'],
        ]);

        $redirect->flushCache();

        $validated['from_url'] = Redirect::normalizePath($validated['from_url']);

        $redirect->update($validated);
        $redirect->flushCache();

        return back()->with('success', 'Redirect updated.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->flushCache();
        $redirect->delete();

        return back()->with('success', 'Redirect deleted.');
    }

    public function toggle(Redirect $redirect): RedirectResponse
    {
        $redirect->flushCache();
        $redirect->update(['is_active' => ! $redirect->is_active]);
        $redirect->flushCache();

        return back()->with('success', $redirect->is_active ? 'Redirect enabled.' : 'Redirect disabled.');
    }
}
