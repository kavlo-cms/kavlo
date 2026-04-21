<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(): Response
    {
        $isDown = app()->isDownForMaintenance();
        $data = [];

        if ($isDown && file_exists(storage_path('framework/down'))) {
            $raw = file_get_contents(storage_path('framework/down'));
            $data = json_decode($raw, true) ?? [];
        }

        return Inertia::render('Maintenance/Index', [
            'isDown' => $isDown,
            'message' => $data['message'] ?? '',
            'retry' => $data['retry'] ?? 60,
            'secret' => $data['secret'] ?? '',
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:255',
            'secret' => 'nullable|string|max:64',
            'retry' => 'nullable|integer|min:1|max:3600',
        ]);

        $args = ['--render' => 'errors::503'];
        if (! empty($validated['message'])) {
            $args['--message'] = $validated['message'];
        }
        if (! empty($validated['secret'])) {
            $args['--secret'] = $validated['secret'];
        }
        if (! empty($validated['retry'])) {
            $args['--retry'] = $validated['retry'];
        }

        Artisan::call('down', $args);

        return back()->with('success', 'Maintenance mode enabled.');
    }

    public function disable(): RedirectResponse
    {
        Artisan::call('up');

        return back()->with('success', 'Site is back online.');
    }
}
