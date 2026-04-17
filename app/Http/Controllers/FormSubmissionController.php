<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\FormBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormSubmissionController extends Controller
{
    public function submit(Request $request, Form $form): JsonResponse
    {
        try {
            $validated = $request->validate(FormBuilder::validationRules($form));
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        $data = FormBuilder::collectSubmissionData($form, $request, $validated);
        $result = FormBuilder::runAction($form, $data, $request);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? $form->resolvedActionConfig()['success_message'] ?? 'Thank you for your submission!',
        ]);
    }
}
