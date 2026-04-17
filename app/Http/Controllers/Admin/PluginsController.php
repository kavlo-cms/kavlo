<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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

    public function toggle(Plugin $plugin): RedirectResponse
    {
        $plugin->update(['is_enabled' => !$plugin->is_enabled]);

        $state = $plugin->is_enabled ? 'enabled' : 'disabled';

        return back()->with('success', "\"{$plugin->name}\" {$state}. Restart required for changes to take effect.");
    }
}
