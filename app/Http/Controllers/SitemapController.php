<?php

namespace App\Http\Controllers;

use App\Services\SitemapRegistry;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(SitemapRegistry $registry): Response
    {
        return response()->view('core::sitemap', [
            'urls' => $registry->getAllUrls(),
        ])->header('Content-Type', 'text/xml');
    }
}
