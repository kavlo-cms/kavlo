<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Theme;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

class KavloSetupService
{
    /**
     * @return array{pages: int, forms: int, menus: int, redirects: int}
     */
    public function install(User $admin): array
    {
        Artisan::call('db:seed', [
            '--class' => RolesAndPermissionsSeeder::class,
            '--force' => true,
        ]);

        Theme::discover();
        app(PluginManager::class)->discover();

        $theme = Theme::query()
            ->where('slug', Theme::DEFAULT_THEME_SLUG)
            ->first() ?? Theme::query()->orderBy('name')->first();

        if ($theme && ! $theme->is_active) {
            $theme->activate();
        }

        $homePage = $this->upsertPage('home', [
            'title' => 'Home',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<section class="space-y-6">
    <h1>Welcome to Kavlo</h1>
    <p>Your site is installed and ready for content, themes, forms, and plugins.</p>
    <p><a href="/about">Learn more about this site</a> or <a href="/contact">get in touch</a>.</p>
</section>
BLADE,
            'is_homepage' => true,
            'is_published' => true,
            'author_id' => $admin->id,
        ]);

        $aboutPage = $this->upsertPage('about', [
            'title' => 'About',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<section class="space-y-6">
    <h1>About</h1>
    <p>This is a starter page created by <strong>kavlo:install</strong>.</p>
    <p>Edit or replace it from the page editor.</p>
</section>
BLADE,
            'is_published' => true,
            'author_id' => $admin->id,
        ]);

        $contactForm = $this->upsertContactForm($admin);

        $contactPage = $this->upsertPage('contact', [
            'title' => 'Contact',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<section class="space-y-6">
    <h1>Contact</h1>
    <p>Use the form below to send us a message.</p>
    {!! kavlo_form('contact') !!}
</section>
BLADE,
            'is_published' => true,
            'author_id' => $admin->id,
        ]);

        Setting::setMany([
            'site_name' => 'Kavlo Site',
            'site_tagline' => 'A Kavlo-powered website',
            'admin_email' => $admin->email,
            'homepage_id' => $homePage->id,
        ]);

        $menu = Menu::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main Navigation'],
        );

        $this->replaceMenuItems($menu, [
            ['label' => 'Home', 'page_id' => $homePage->id],
            ['label' => 'About', 'page_id' => $aboutPage->id],
            ['label' => 'Contact', 'page_id' => $contactPage->id],
        ]);

        Redirect::query()->updateOrCreate(
            ['from_url' => '/start'],
            [
                'to_url' => '/',
                'type' => 301,
                'is_active' => true,
            ],
        );

        app(ContentRouteRegistry::class)->forget();

        return [
            'pages' => 3,
            'forms' => $contactForm->exists ? 1 : 0,
            'menus' => 1,
            'redirects' => 1,
        ];
    }

    /**
     * @return array{pages: int, email_templates: int, redirects: int}
     */
    public function installDemoContent(User $author): array
    {
        $featuresPage = $this->upsertPage('features', [
            'title' => 'Features',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<section class="space-y-6">
    <h1>Feature Highlights</h1>
    <ul>
        <li>Builder-backed pages and forms</li>
        <li>Theme and plugin support</li>
        <li>Backups, checkpoints, and health monitoring</li>
        <li>Media, redirects, analytics, and revisions</li>
    </ul>
</section>
BLADE,
            'is_published' => true,
            'author_id' => $author->id,
        ]);

        $thankYouPage = $this->upsertPage('thank-you', [
            'title' => 'Thank You',
            'type' => 'page',
            'editor_mode' => 'content',
            'content' => <<<'BLADE'
<section class="space-y-6">
    <h1>Thank you</h1>
    <p>This is an optional demo confirmation page created by <strong>kavlo:demo</strong>.</p>
</section>
BLADE,
            'is_published' => true,
            'author_id' => $author->id,
        ]);

        EmailTemplate::query()->updateOrCreate(
            ['slug' => 'contact-notification'],
            [
                'name' => 'Contact Notification',
                'description' => 'Starter notification template for the seeded contact form.',
                'context_key' => EmailTemplateBuilder::FORM_NOTIFICATION_CONTEXT,
                'subject' => 'New {{ form.name }} submission',
                'blocks' => [
                    [
                        'id' => 'heading-1',
                        'type' => 'heading',
                        'data' => [
                            'text' => '{{ form.name }}',
                            'level' => 'h2',
                            'align' => 'left',
                        ],
                        'order' => 0,
                    ],
                    [
                        'id' => 'text-1',
                        'type' => 'text',
                        'data' => [
                            'content' => 'From: {{ submission.email }}',
                        ],
                        'order' => 1,
                    ],
                ],
            ],
        );

        Redirect::query()->updateOrCreate(
            ['from_url' => '/company'],
            [
                'to_url' => '/about',
                'type' => 301,
                'is_active' => true,
            ],
        );

        $menu = Menu::query()->where('slug', 'main')->first();

        if ($menu) {
            $this->replaceMenuItems($menu, [
                ['label' => 'Home', 'page_id' => (int) Setting::get('homepage_id')],
                ['label' => 'About', 'page_id' => Page::query()->where('slug', 'about')->value('id')],
                ['label' => 'Features', 'page_id' => $featuresPage->id],
                ['label' => 'Contact', 'page_id' => Page::query()->where('slug', 'contact')->value('id')],
                ['label' => 'Thank You', 'page_id' => $thankYouPage->id],
            ]);
        }

        app(ContentRouteRegistry::class)->forget();

        return [
            'pages' => 2,
            'email_templates' => 1,
            'redirects' => 1,
        ];
    }

    private function upsertContactForm(User $author): Form
    {
        return Form::query()->updateOrCreate(
            ['slug' => 'contact'],
            [
                'name' => 'Contact',
                'description' => 'Starter contact form created during installation.',
                'blocks' => [
                    [
                        'id' => 'contact-email',
                        'type' => 'input',
                        'data' => [
                            'input_type' => 'email',
                            'label' => 'Email',
                            'key' => 'email',
                            'placeholder' => 'you@example.com',
                            'required' => true,
                        ],
                        'order' => 0,
                    ],
                    [
                        'id' => 'contact-message',
                        'type' => 'textarea',
                        'data' => [
                            'label' => 'Message',
                            'key' => 'message',
                            'placeholder' => 'How can we help?',
                            'required' => true,
                        ],
                        'order' => 1,
                    ],
                    [
                        'id' => 'contact-submit',
                        'type' => 'button',
                        'data' => [
                            'label' => 'Send message',
                        ],
                        'order' => 2,
                    ],
                ],
                'submission_action' => FormBuilder::DEFAULT_ACTION,
                'action_config' => [
                    'success_message' => 'Thank you for reaching out.',
                    'notify_email' => $author->email,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertPage(string $slug, array $attributes): Page
    {
        if (($attributes['is_homepage'] ?? false) === true) {
            Page::query()->where('slug', '!=', $slug)->update(['is_homepage' => false]);
        }

        return Page::query()->updateOrCreate(
            ['slug' => $slug],
            Page::sanitizePersistedAttributes(array_merge([
                'type' => 'page',
                'editor_mode' => 'content',
                'content' => '',
                'blocks' => [],
                'metadata' => [],
                'is_homepage' => false,
                'is_published' => true,
            ], $attributes)),
        );
    }

    /**
     * @param  list<array{label: string, page_id: int|null}>  $items
     */
    private function replaceMenuItems(Menu $menu, array $items): void
    {
        MenuItem::query()->where('menu_id', $menu->id)->delete();

        foreach ($items as $order => $item) {
            if (! $item['page_id']) {
                continue;
            }

            MenuItem::query()->create([
                'menu_id' => $menu->id,
                'label' => $item['label'],
                'page_id' => $item['page_id'],
                'url' => null,
                'target' => '_self',
                'parent_id' => null,
                'order' => $order,
            ]);
        }
    }
}
