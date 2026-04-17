<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\FormBuilder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FormSubmissionsController extends Controller
{
    public function index(Form $form): Response
    {
        $submissions = $form->submissions()
            ->latest()
            ->paginate(20);

        return Inertia::render('Forms/Submissions', [
            'form' => array_merge($form->toArray(), ['fields' => $form->submissionFields()]),
            'submissions' => $submissions,
        ]);
    }

    public function destroy(Form $form, FormSubmission $submission): RedirectResponse
    {
        $submission->delete();

        return back()->with('success', 'Submission deleted.');
    }

    public function export(Form $form): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fields = $form->submissionFields();

        $filename = 'submissions-'.$form->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($form, $fields) {
            $handle = fopen('php://output', 'w');

            $headers = array_merge(
                ['submitted_at'],
                array_map(fn (array $field) => $field['label'], $fields),
            );
            fputcsv($handle, $headers);

            $form->submissions()->orderBy('created_at')->chunk(200, function ($submissions) use ($handle, $fields) {
                foreach ($submissions as $submission) {
                    $row = [$submission->created_at->toDateTimeString()];
                    foreach ($fields as $field) {
                        $row[] = FormBuilder::formatSubmissionValue($submission->data[$field['key']] ?? null);
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
