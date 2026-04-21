<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PageAnalytics;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly PageAnalytics $analytics) {}

    public function index(): Response
    {
        return Inertia::render('Analytics/Index', $this->analytics->report());
    }
}
