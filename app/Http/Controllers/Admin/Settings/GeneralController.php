<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Setting;
use App\Models\SiteLanguage;
use App\Services\ContentRouteRegistry;
use App\Services\SiteLocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function __construct(
        private readonly SiteLocaleManager $locales,
    ) {}

    public function index(): Response
    {
        $settings = Setting::allCached();

        $pages = Page::select('id', 'title')
            ->orderBy('title')
            ->get();

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'pages' => $pages,
            'languages' => $this->locales->allLanguages()->map(fn (SiteLanguage $language) => [
                'code' => $language->code,
                'name' => $language->name,
                'is_active' => (bool) $language->is_active,
                'is_default' => (bool) $language->is_default,
            ])->values()->all(),
            'defaultLocale' => $this->locales->defaultLocale(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['nullable', 'email', 'max:255'],
            'meta_title_format' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'homepage_id' => ['nullable', 'integer', 'exists:pages,id'],
            'favicon' => ['nullable', 'string', 'max:500'],
            'default_locale' => ['required', 'string', 'max:16'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*.code' => ['required', 'string', 'max:16', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/i'],
            'languages.*.name' => ['required', 'string', 'max:255'],
            'languages.*.is_active' => ['boolean'],
        ]);

        $languages = collect($validated['languages'])
            ->map(fn (array $language) => [
                'code' => $this->locales->normalizeLocale($language['code']),
                'name' => trim((string) $language['name']),
                'is_active' => (bool) ($language['is_active'] ?? true),
            ])
            ->values();

        if ($languages->pluck('code')->contains(null) || $languages->pluck('code')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'languages' => 'Language codes must be unique.',
            ]);
        }

        $defaultLocale = $this->locales->normalizeLocale($validated['default_locale']);
        $defaultLanguage = $languages->firstWhere('code', $defaultLocale);

        if (! $defaultLanguage) {
            throw ValidationException::withMessages([
                'default_locale' => 'Choose a default language from the configured list.',
            ]);
        }

        if (! $defaultLanguage['is_active']) {
            throw ValidationException::withMessages([
                'default_locale' => 'The default language must remain active.',
            ]);
        }

        $removedCodes = SiteLanguage::query()
            ->pluck('code')
            ->diff($languages->pluck('code'));

        if ($removedCodes->isNotEmpty() && PageTranslation::query()->whereIn('locale', $removedCodes)->exists()) {
            throw ValidationException::withMessages([
                'languages' => 'Remove page translations before deleting a language.',
            ]);
        }

        DB::transaction(function () use ($validated, $languages, $defaultLocale) {
            Setting::setMany(collect($validated)
                ->except(['languages', 'default_locale'])
                ->all());

            SiteLanguage::query()
                ->whereNotIn('code', $languages->pluck('code'))
                ->delete();

            foreach ($languages as $language) {
                SiteLanguage::query()->updateOrCreate(
                    ['code' => $language['code']],
                    [
                        'name' => $language['name'],
                        'is_active' => $language['is_active'],
                        'is_default' => $language['code'] === $defaultLocale,
                    ],
                );
            }

            SiteLanguage::query()
                ->where('code', '!=', $defaultLocale)
                ->update(['is_default' => false]);
        });

        $this->locales->flush();
        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Settings saved.');
    }
}
