<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $query = $request->string('q')->trim()->value();

        return view('core::search.index', [
            'query' => $query,
            'results' => $search->publicResults($query),
        ]);
    }
}
