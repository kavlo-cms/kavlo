<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
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
                'id' => $a->id,
                'log_name' => $a->log_name,
                'description' => $a->description,
                'subject_type' => $a->subject_type ? class_basename($a->subject_type) : null,
                'subject_id' => $a->subject_id,
                'subject_label' => Arr::get($a->properties, 'subject_label'),
                'target' => Arr::get($a->properties, 'target'),
                'route_name' => Arr::get($a->properties, 'route_name'),
                'changed_fields' => $this->changedFields($a),
                'causer' => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
                'created_at' => $a->created_at,
            ]);

        return Inertia::render('Activity/Index', [
            'log' => $log,
        ]);
    }

    private function changedFields(Activity $activity): array
    {
        $manualFields = Arr::get($activity->properties, 'changed_fields');

        if (is_array($manualFields) && $manualFields !== []) {
            return array_slice(array_values(array_map('strval', $manualFields)), 0, 6);
        }

        $attributes = Arr::get($activity->properties, 'attributes', []);
        $old = Arr::get($activity->properties, 'old', []);

        return array_slice(array_values(array_unique([
            ...array_keys(is_array($attributes) ? $attributes : []),
            ...array_keys(is_array($old) ? $old : []),
        ])), 0, 6);
    }
}
