<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function index(): Response
    {
        $log = Activity::with('causer:id,name')
            ->latest()
            ->paginate(50)
            ->through(fn (Activity $a) => [
                'id'          => $a->id,
                'log_name'    => $a->log_name,
                'description' => $a->description,
                'subject_type' => $a->subject_type ? class_basename($a->subject_type) : null,
                'subject_id'  => $a->subject_id,
                'causer'      => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
                'properties'  => $a->properties,
                'created_at'  => $a->created_at,
            ]);

        return Inertia::render('Activity/Index', [
            'log' => $log,
        ]);
    }
}
