<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('pages:process-scheduled')->everyMinute();
