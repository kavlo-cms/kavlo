<?php

use App\Services\SystemHealthService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schedule;

Schedule::command('pages:process-scheduled')->everyMinute();
Schedule::call(fn () => App::make(SystemHealthService::class)->markSchedulerHeartbeat())->name('system-health:heartbeat')->everyMinute();
