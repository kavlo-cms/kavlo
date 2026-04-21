<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Revision;
use App\Models\User;
use App\Services\SystemHealthService;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DashboardController extends Controller
{
    public function index(SystemHealthService $health): Response
    {
        $stats = [
            'total_pages' => Page::count(),
            'published_pages' => Page::where('is_published', true)->count(),
            'draft_pages' => Page::where('is_published', false)->count(),
            'media_files' => Media::count(),
            'menus' => Menu::count(),
            'total_users' => User::count(),
            'total_redirects' => Redirect::count(),
            'active_redirects' => Redirect::where('is_active', true)->count(),
        ];

        $recentRevisions = Revision::with(['page:id,title,slug', 'user:id,name'])
            ->latest()
            ->limit(10)
            ->get(['id', 'page_id', 'user_id', 'label', 'created_at']);

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentRevisions' => $recentRevisions,
            'systemHealth' => $health->report(),
        ]);
    }
}
