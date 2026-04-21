<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PublicPageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class CacheController extends Controller
{
    public function __construct(
        private readonly PublicPageCache $pageCache,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Cache/Index', [
            'publicPageCache' => $this->pageCache->status(),
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $type = $request->validate(['type' => 'required|in:application,views,routes,config,page_html,all'])['type'];

        match ($type) {
            'application' => Artisan::call('cache:clear'),
            'views' => Artisan::call('view:clear'),
            'routes' => Artisan::call('route:clear'),
            'config' => Artisan::call('config:clear'),
            'page_html' => $this->pageCache->flush(),
            'all' => (function () {
                Artisan::call('cache:clear');
                Artisan::call('view:clear');
                Artisan::call('route:clear');
                Artisan::call('config:clear');
                $this->pageCache->flush();
            })(),
        };

        $label = match ($type) {
            'application' => 'Application cache',
            'views' => 'View cache',
            'routes' => 'Route cache',
            'config' => 'Config cache',
            'page_html' => 'Public page cache',
            'all' => 'All caches',
        };

        return back()->with('success', "{$label} cleared.");
    }
}
