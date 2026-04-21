<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PluginsController extends Controller
{
    public function __construct(private readonly PluginManager $manager) {}

    public function index(): Response
    {
        $plugins = $this->manager->discover();

        return Inertia::render('Plugins/Index', [
            'plugins' => $plugins,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'archive' => 'required|file|max:51200',
        ]);

        try {
            $plugin = $this->manager->installFromArchive($validated['archive']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['archive' => $exception->getMessage()]);
        }

        return back()->with('success', "\"{$plugin->name}\" uploaded. Activate it to run its installer.");
    }

    public function toggle(Plugin $plugin): RedirectResponse
    {
        if ($plugin->is_enabled) {
            $this->manager->disable($plugin);

            return back()->with('success', "\"{$plugin->name}\" disabled.");
        }

        try {
            $this->manager->enable($plugin);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['plugin' => $exception->getMessage()]);
        }

        return back()->with('success', "\"{$plugin->name}\" enabled and migrations applied.");
    }
}
