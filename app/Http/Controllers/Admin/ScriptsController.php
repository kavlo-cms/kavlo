<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteScript;
use App\Services\KavloStorage;
use App\Services\PublicPageCache;
use App\Services\ScriptManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScriptsController extends Controller
{
    public function __construct(
        private readonly ScriptManager $scripts,
        private readonly KavloStorage $storage,
        private readonly PublicPageCache $pageCache,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Scripts/Index', [
            'scripts' => $this->scripts->all()->map(fn (SiteScript $script) => [
                'id' => $script->id,
                'name' => $script->name,
                'placement' => $script->placement,
                'source_type' => $script->source_type,
                'source_url' => $script->source_url,
                'file_path' => $script->file_path,
                'file_url' => $script->file_path ? $this->storage->publicUrl($script->file_path) : null,
                'file_name' => $script->file_path ? basename($script->file_path) : null,
                'inline_content' => $script->inline_content,
                'load_strategy' => $script->load_strategy,
                'sort_order' => $script->sort_order,
                'is_enabled' => $script->is_enabled,
                'notes' => $script->notes,
                'updated_at' => $script->updated_at?->toISOString(),
            ])->values(),
            'placementOptions' => $this->scripts->placementOptions(),
            'sourceTypeOptions' => $this->scripts->sourceTypeOptions(),
            'loadStrategyOptions' => $this->scripts->loadStrategyOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $script = new SiteScript;
        $this->persist($request, $script);

        return redirect()->route('admin.scripts.index')
            ->with('success', 'Script created.');
    }

    public function update(Request $request, SiteScript $siteScript): RedirectResponse
    {
        $this->persist($request, $siteScript);

        return redirect()->route('admin.scripts.index')
            ->with('success', 'Script updated.');
    }

    public function destroy(SiteScript $siteScript): RedirectResponse
    {
        if ($siteScript->file_path) {
            $this->storage->publicDisk()->delete($siteScript->file_path);
        }

        $siteScript->delete();
        $this->scripts->forget();
        $this->pageCache->flush();

        return redirect()->route('admin.scripts.index')
            ->with('success', 'Script deleted.');
    }

    private function persist(Request $request, SiteScript $siteScript): void
    {
        $validated = $this->validateScript($request, $siteScript);
        $previousFilePath = $siteScript->file_path;

        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($validated['source_type'] === 'upload') {
            if ($request->hasFile('file')) {
                $validated['file_path'] = $this->storeUpload($request->file('file'), $validated['name']);

                if ($previousFilePath && $previousFilePath !== $validated['file_path']) {
                    $this->storage->publicDisk()->delete($previousFilePath);
                }
            } else {
                $validated['file_path'] = $previousFilePath;
            }

            $validated['source_url'] = null;
            $validated['inline_content'] = null;
        } elseif ($validated['source_type'] === 'url') {
            $validated['file_path'] = null;
            $validated['inline_content'] = null;

            if ($previousFilePath) {
                $this->storage->publicDisk()->delete($previousFilePath);
            }
        } else {
            $validated['file_path'] = null;
            $validated['source_url'] = null;

            if ($previousFilePath) {
                $this->storage->publicDisk()->delete($previousFilePath);
            }
        }

        unset($validated['file']);

        $siteScript->fill($validated)->save();
        $this->scripts->forget();
        $this->pageCache->flush();
    }

    private function validateScript(Request $request, SiteScript $siteScript): array
    {
        $placements = collect($this->scripts->placementOptions())->pluck('value')->all();
        $sourceTypes = collect($this->scripts->sourceTypeOptions())->pluck('value')->all();
        $loadStrategies = collect($this->scripts->loadStrategyOptions())->pluck('value')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'placement' => ['required', 'string', Rule::in($placements)],
            'source_type' => ['required', 'string', Rule::in($sourceTypes)],
            'source_url' => [
                'nullable',
                'string',
                'max:2048',
                'url',
                Rule::requiredIf(fn () => $request->input('source_type') === 'url'),
            ],
            'inline_content' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $request->input('source_type') === 'inline'),
            ],
            'file' => [
                'nullable',
                'file',
                'extensions:js,mjs',
                'max:2048',
                Rule::requiredIf(function () use ($request, $siteScript) {
                    return $request->input('source_type') === 'upload'
                        && (! $siteScript->exists || $siteScript->source_type !== 'upload' || blank($siteScript->file_path));
                }),
            ],
            'load_strategy' => ['required', 'string', Rule::in($loadStrategies)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_enabled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function storeUpload(UploadedFile $file, string $name): string
    {
        $baseName = Str::slug($name) ?: 'script';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'js');
        $fileName = $baseName.'-'.Str::random(8).'.'.$extension;

        return $file->storeAs('scripts', $fileName, $this->storage->publicDiskName());
    }
}
