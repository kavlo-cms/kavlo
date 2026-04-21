<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(SystemHealthService $health): JsonResponse
    {
        $report = $health->report();

        return response()->json(
            $report,
            $report['status'] === 'fail' ? 503 : 200,
        );
    }
}
