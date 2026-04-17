<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class CacheController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cache/Index');
    }

    public function clear(Request $request): RedirectResponse
    {
        $type = $request->validate(['type' => 'required|in:application,views,routes,config,all'])['type'];

        match ($type) {
            'application' => Artisan::call('cache:clear'),
            'views'       => Artisan::call('view:clear'),
            'routes'      => Artisan::call('route:clear'),
            'config'      => Artisan::call('config:clear'),
            'all'         => (function () {
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');
                Artisan::call('config:clear');
            })(),
        };

        $label = match ($type) {
            'application' => 'Application cache',
            'views'       => 'View cache',
            'routes'      => 'Route cache',
            'config'      => 'Config cache',
            'all'         => 'All caches',
        };

        return back()->with('success', "{$label} cleared.");
    }
}
