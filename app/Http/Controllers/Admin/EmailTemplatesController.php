<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\BuilderBlockPayload;
use App\Services\EmailTemplateBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplatesController extends Controller
{
    public function __construct(
        protected BuilderBlockPayload $blockPayload,
        protected EmailTemplateBuilder $builder,
    ) {}

    public function index(): Response
    {
        $contexts = collect($this->builder->publicContexts())->keyBy('key');

        return Inertia::render('EmailTemplates/Index', [
            'templates' => EmailTemplate::query()
                ->orderBy('name')
                ->get()
                ->map(fn (EmailTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'context_key' => $template->context_key,
                    'context_label' => $contexts[$template->context_key]['label'] ?? $template->context_key,
                    'subject' => $template->subject,
                    'updated_at' => $template->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('EmailTemplates/Edit', [
            'template' => null,
            'availableBlocks' => $this->builder->availableBlocks(),
            'availableContexts' => $this->builder->publicContexts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = EmailTemplate::create($this->validateTemplate($request));

        return redirect()
            ->route('admin.email-templates.edit', $template)
            ->with('success', 'Email template created.');
    }

    public function edit(EmailTemplate $emailTemplate): Response
    {
        return Inertia::render('EmailTemplates/Edit', [
            'template' => [
                ...$emailTemplate->toArray(),
                'blocks' => $emailTemplate->editorBlocks(),
            ],
            'availableBlocks' => $this->builder->availableBlocks(),
            'availableContexts' => $this->builder->publicContexts(),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->update($this->validateTemplate($request, $emailTemplate));

        return back()->with('success', 'Email template updated.');
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->delete();

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', 'Email template deleted.');
    }

    protected function validateTemplate(Request $request, ?EmailTemplate $emailTemplate = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('email_templates', 'slug')->ignore($emailTemplate?->id)],
            'description' => ['nullable', 'string'],
            'context_key' => ['required', 'string', Rule::in($this->builder->contextKeys())],
            'subject' => ['required', 'string', 'max:255'],
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'string'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.data' => ['nullable', 'array'],
            'blocks.*.order' => ['nullable', 'integer'],
        ]);

        $schemaErrors = $this->blockPayload->validateStructure($validated['blocks'] ?? []);

        if ($schemaErrors !== []) {
            throw ValidationException::withMessages([
                'blocks' => $schemaErrors[0],
            ]);
        }

        $blockErrors = $this->builder->validateBlocks($validated['blocks'] ?? []);

        if ($blockErrors !== []) {
            throw ValidationException::withMessages([
                'blocks' => $blockErrors[0],
            ]);
        }

        $validated['blocks'] = $this->builder->normalizeBlocks($validated['blocks'] ?? []);

        return $validated;
    }
}
