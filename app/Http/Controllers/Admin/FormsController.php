<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\ContentRouteRegistry;
use App\Services\FormBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FormsController extends Controller
{
    public function index(): Response
    {
        $forms = Form::withCount(['submissions'])
            ->orderBy('name')
            ->get()
            ->map(function (Form $form) {
                return array_merge($form->toArray(), [
                    'fields_count' => FormBuilder::fieldCount($form),
                    'form_submissions_count' => $form->submissions_count,
                ]);
            });

        return Inertia::render('Forms/Index', [
            'forms' => $forms,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Forms/Edit', [
            'form' => null,
            'availableBlocks' => FormBuilder::availableBlocks(),
            'availableActions' => FormBuilder::publicActions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actionKeys = collect(FormBuilder::publicActions())->pluck('key')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:forms,slug'],
            'description' => ['nullable', 'string'],
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'string'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.data' => ['nullable', 'array'],
            'blocks.*.order' => ['nullable', 'integer'],
            'submission_action' => ['nullable', 'string', Rule::in($actionKeys)],
            'action_config' => ['nullable', 'array'],
        ]);

        $blockErrors = FormBuilder::validateBlocks($validated['blocks'] ?? []);

        if ($blockErrors !== []) {
            return back()->withErrors(['blocks' => $blockErrors[0]])->withInput();
        }

        $validated['blocks'] = FormBuilder::normalizeBlocks($validated['blocks'] ?? []);
        $validated['submission_action'] = $validated['submission_action'] ?? FormBuilder::DEFAULT_ACTION;

        if ($validated['submission_action'] === FormBuilder::DEFAULT_ACTION) {
            $validated['success_message'] = $validated['action_config']['success_message'] ?? null;
            $validated['redirect_url'] = $validated['action_config']['redirect_url'] ?? null;
            $validated['notify_email'] = $validated['action_config']['notify_email'] ?? null;
        }

        $form = Form::create($validated);
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.forms.edit', $form)->with('success', 'Form created.');
    }

    public function edit(Form $form): Response
    {
        $form->load(['fields' => fn ($q) => $q->orderBy('sort_order')])->loadCount('submissions');

        return Inertia::render('Forms/Edit', [
            'form' => array_merge($form->toArray(), [
                'blocks' => $form->editorBlocks(),
                'submission_action' => $form->resolvedSubmissionAction(),
                'action_config' => $form->resolvedActionConfig(),
                'form_submissions_count' => $form->submissions_count,
            ]),
            'availableBlocks' => FormBuilder::availableBlocks(),
            'availableActions' => FormBuilder::publicActions(),
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $actionKeys = collect(FormBuilder::publicActions())->pluck('key')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('forms', 'slug')->ignore($form->id)],
            'description' => ['nullable', 'string'],
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'string'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.data' => ['nullable', 'array'],
            'blocks.*.order' => ['nullable', 'integer'],
            'submission_action' => ['nullable', 'string', Rule::in($actionKeys)],
            'action_config' => ['nullable', 'array'],
        ]);

        $blockErrors = FormBuilder::validateBlocks($validated['blocks'] ?? []);

        if ($blockErrors !== []) {
            return back()->withErrors(['blocks' => $blockErrors[0]])->withInput();
        }

        $validated['blocks'] = FormBuilder::normalizeBlocks($validated['blocks'] ?? []);
        $validated['submission_action'] = $validated['submission_action'] ?? FormBuilder::DEFAULT_ACTION;

        if ($validated['submission_action'] === FormBuilder::DEFAULT_ACTION) {
            $validated['success_message'] = $validated['action_config']['success_message'] ?? null;
            $validated['redirect_url'] = $validated['action_config']['redirect_url'] ?? null;
            $validated['notify_email'] = $validated['action_config']['notify_email'] ?? null;
        }

        $form->update($validated);
        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Form updated.');
    }

    public function destroy(Form $form): RedirectResponse
    {
        $form->delete();
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.forms.index')->with('success', 'Form deleted.');
    }
}
