<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): Response
    {
        $query = $request->string('q')->trim()->value();

        return Inertia::render('Search/Index', [
            'query' => $query,
            'results' => $search->adminResults($query),
        ]);
    }
}
