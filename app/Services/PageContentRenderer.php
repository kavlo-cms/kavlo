<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Blade;

class PageContentRenderer
{
    public function __construct(
        protected ContentRouteRegistry $routes,
        protected SiteLocaleManager $locales,
    ) {}

    public function render(Page $page): string
    {
        $template = trim((string) ($page->content ?? ''));

        if ($template === '') {
            return '';
        }

        return Blade::render($template, $this->context($page), deleteCachedView: true);
    }

    public function publicContext(): array
    {
        return [
            'variables' => [
                [
                    'label' => 'Site name',
                    'token' => "{{ \$site['name'] }}",
                    'description' => 'Configured site name from settings.',
                ],
                [
                    'label' => 'Site tagline',
                    'token' => "{{ \$site['tagline'] }}",
                    'description' => 'Configured site tagline from settings.',
                ],
                [
                    'label' => 'Current page title',
                    'token' => "{{ \$page['title'] }}",
                    'description' => 'The current page title.',
                ],
                [
                    'label' => 'Current page path',
                    'token' => "{{ \$page['path'] }}",
                    'description' => 'The current page path, starting with /.',
                ],
                [
                    'label' => 'Current page metadata',
                    'token' => "{{ \$page['metadata']['key'] ?? '' }}",
                    'description' => 'Read a custom metadata value.',
                ],
                [
                    'label' => 'Render a form',
                    'token' => "{!! kavlo_form('contact') !!}",
                    'description' => 'Render the saved form or registered form by slug/key.',
                ],
            ],
            'snippets' => [
                [
                    'label' => 'Loop published pages',
                    'description' => 'Render a list of all published pages.',
                    'snippet' => <<<'BLADE'
@foreach ($pages as $entry)
    <a href="{{ $entry['path'] }}">{{ $entry['title'] }}</a>
@endforeach
BLADE,
                ],
                [
                    'label' => 'Homepage conditional',
                    'description' => 'Show markup only when the current page is the homepage.',
                    'snippet' => <<<'BLADE'
@if ($page['isHomepage'])
    <p>Welcome to the homepage.</p>
@endif
BLADE,
                ],
                [
                    'label' => 'Loop forms',
                    'description' => 'Render links to all forms with their submission path.',
                    'snippet' => <<<'BLADE'
@foreach ($forms as $form)
    <a href="{{ $form['submissionPath'] }}">{{ $form['name'] }}</a>
@endforeach
BLADE,
                ],
                [
                    'label' => 'Render a specific form',
                    'description' => 'Embed the actual rendered form markup inside content mode.',
                    'snippet' => <<<'BLADE'
{!! kavlo_form('contact') !!}
BLADE,
                ],
            ],
        ];
    }

    public function context(Page $page): array
    {
        return [
            'site' => [
                'name' => Setting::get('site_name', config('app.name')),
                'tagline' => Setting::get('site_tagline', ''),
                'url' => url($this->locales->pathForLocale('/', $this->locales->currentLocale())),
            ],
            'page' => $this->pageContext($page),
            'pages' => $this->pagesContext(),
            'menus' => $this->menusContext(),
            'forms' => $this->formsContext(),
        ];
    }

    protected function pageContext(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $page->localizedPath($this->locales->currentLocale()),
            'type' => $page->type,
            'isHomepage' => (bool) $page->is_homepage,
            'isPublished' => (bool) $page->is_published,
            'metaTitle' => $page->meta_title,
            'metaDescription' => $page->meta_description,
            'metadata' => $page->metadata ?? [],
        ];
    }

    protected function pagesContext(): array
    {
        $locale = $this->locales->currentLocale();

        return Page::query()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('title')
            ->get()
            ->map(fn (Page $page) => $this->routes->pagePayload($page, $locale))
            ->filter(fn (array $page) => $page['isPublished'])
            ->values()
            ->all();
    }

    protected function menusContext(): array
    {
        return Menu::query()
            ->with([
                'items' => fn ($query) => $query->whereNull('parent_id')->orderBy('order'),
                'items.children' => fn ($query) => $query->orderBy('order'),
                'items.children.children' => fn ($query) => $query->orderBy('order'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Menu $menu) => $this->routes->menuPayload($menu))
            ->all();
    }

    protected function formsContext(): array
    {
        return Form::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Form $form) => $this->routes->formPayload($form))
            ->all();
    }
}
