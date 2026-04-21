<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Page;
use App\Models\Plugin;
use App\Services\DataHubRegistry;
use App\Services\EmbeddableFormRegistry;
use App\Services\FormBuilder;
use App\Services\HookManager;
use App\Services\PageTypeManager;
use App\Services\PluginManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ExampleSeoPluginFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        File::deleteDirectory(storage_path('app/private/plugins/example-seo'));
        File::deleteDirectory(storage_path('app/public/plugins/example-seo'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/plugins/example-seo'));
        File::deleteDirectory(storage_path('app/public/plugins/example-seo'));

        parent::tearDown();
    }

    public function test_enabling_example_plugin_registers_reference_capabilities(): void
    {
        $plugin = $this->enableExamplePlugin();

        $this->assertTrue($plugin->fresh()->is_enabled);
        $this->assertContains([
            'group' => 'Content',
            'title' => 'SEO',
            'href' => '/admin/plugins',
            'icon' => 'search',
            'permission' => 'view plugins',
        ], app(HookManager::class)->applyFilters('admin.nav', []));

        $pageBlocks = collect(app(HookManager::class)->applyFilters('blocks.available', []))->keyBy('type');
        $this->assertSame('example-seo', $pageBlocks['seo-audit-summary']['source'] ?? null);

        $pageTypes = collect(PageTypeManager::all())->keyBy('type');
        $this->assertSame('SEO Landing Page', $pageTypes['seo-landing-page']['label'] ?? null);
        $this->assertSame('Plugin: Example Seo', $pageTypes['seo-landing-page']['source_label'] ?? null);

        $formBlocks = collect(FormBuilder::availableBlocks())->keyBy('type');
        $this->assertSame('example-seo', $formBlocks['seo-consent']['source'] ?? null);

        $formActions = collect(FormBuilder::publicActions())->keyBy('key');
        $this->assertSame('example-seo', $formActions['example-seo.audit-webhook']['source'] ?? null);

        $registeredForms = collect(EmbeddableFormRegistry::registeredForms())->keyBy('key');
        $this->assertSame('example-seo', $registeredForms['example-seo-lead-capture']['source'] ?? null);
        $this->assertStringContainsString('Plugin-managed CTA block', (string) ($registeredForms['example-seo-lead-capture']['preview_html'] ?? ''));

        $channels = collect(app(DataHubRegistry::class)->channels())->keyBy('key');
        $this->assertSame('example-seo', $channels['example-seo-webhook']['source'] ?? null);

        $resources = collect(app(DataHubRegistry::class)->resources())->keyBy('key');
        $this->assertSame('example-seo', $resources['seo-metadata']['source'] ?? null);

        $references = app(HookManager::class)->applyFilters(
            'media.usage.references',
            [],
            new Media(['file_name' => 'seo-og-banner.jpg']),
        );

        $this->assertContains([
            'type' => 'plugin',
            'label' => 'Example SEO Plugin',
            'href' => '/admin/plugins',
            'context' => 'Referenced by plugin SEO assets',
        ], $references);

        $capabilities = app(HookManager::class)->applyFilters('plugins.example-seo.capabilities', []);
        $this->assertSame('seo-audit-summary', $capabilities['page_builder_block']['type'] ?? null);
        $this->assertSame('seo-landing-page', $capabilities['page_type']['type'] ?? null);
        $this->assertStringEndsWith('/storage/plugins/example-seo/examples/og-image.jpg', $capabilities['storage']['public_example_url'] ?? '');
        $this->assertSame('cms-plugin/example-seo', $capabilities['http']['user_agent'] ?? null);
        $this->assertSame('example-seo', $capabilities['http']['headers']['X-Plugin-Slug'] ?? null);
    }

    public function test_example_plugin_form_action_stores_submission_snapshot(): void
    {
        $this->enableExamplePlugin();

        $form = Form::create([
            'name' => 'SEO Audit Request',
            'slug' => 'seo-audit-request',
            'submission_action' => 'example-seo.audit-webhook',
            'action_config' => [
                'success_message' => 'We will review your site shortly.',
                'endpoint' => '',
            ],
            'blocks' => [
                [
                    'id' => 'email',
                    'type' => 'input',
                    'data' => [
                        'input_type' => 'email',
                        'label' => 'Email',
                        'key' => 'email',
                        'required' => true,
                    ],
                    'order' => 0,
                ],
                [
                    'id' => 'consent',
                    'type' => 'seo-consent',
                    'data' => [
                        'label' => 'SEO follow-up',
                        'key' => 'seo_consent',
                        'required' => false,
                        'options' => [
                            ['label' => 'Yes', 'value' => 'yes'],
                        ],
                    ],
                    'order' => 1,
                ],
                [
                    'id' => 'submit',
                    'type' => 'button',
                    'data' => [
                        'label' => 'Request audit',
                    ],
                    'order' => 2,
                ],
            ],
        ]);

        $this->postJson(route('forms.submit', $form->slug), [
            'email' => 'jane@example.com',
            'seo_consent' => 'yes',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'We will review your site shortly.');

        $files = File::files(storage_path('app/private/plugins/example-seo/submissions'));

        $this->assertCount(1, $files);

        $payload = json_decode(File::get($files[0]->getRealPath()), true);

        $this->assertSame('SEO Audit Request', $payload['form']['name'] ?? null);
        $this->assertSame('seo-audit-request', $payload['form']['slug'] ?? null);
        $this->assertSame('jane@example.com', $payload['submission']['email'] ?? null);
        $this->assertSame('yes', $payload['submission']['seo_consent'] ?? null);
    }

    public function test_example_plugin_registered_form_renders_through_cms_form_helper(): void
    {
        $this->enableExamplePlugin();

        $html = (string) kavlo_form('example-seo-lead-capture');

        $this->assertStringContainsString('example-seo-lead-capture', $html);
        $this->assertStringContainsString('Plugin demo', $html);
        $this->assertStringContainsString('Request an audit', $html);
    }

    public function test_example_plugin_page_block_renders_on_a_live_page(): void
    {
        View::addNamespace('theme', base_path('themes/midnight-blue/views'));

        $this->enableExamplePlugin();

        Page::create([
            'title' => 'SEO campaign',
            'slug' => 'seo-campaign',
            'type' => 'seo-landing-page',
            'editor_mode' => 'builder',
            'is_published' => true,
            'blocks' => [[
                'id' => 'seo-block-1',
                'type' => 'seo-audit-summary',
                'data' => [
                    'label' => 'Technical SEO audit',
                    'summary' => 'Resolve metadata conflicts and improve crawl depth.',
                    'severity' => 'warning',
                    'cta_label' => 'Book a review',
                    'cta_url' => '/contact',
                ],
                'order' => 0,
            ]],
        ]);

        $this->get('/seo-campaign')
            ->assertOk()
            ->assertSee('Technical SEO audit')
            ->assertSee('Resolve metadata conflicts and improve crawl depth.')
            ->assertSee('Book a review')
            ->assertSee('Warning');
    }

    private function enableExamplePlugin(): Plugin
    {
        app(PluginManager::class)->discover();

        /** @var Plugin $plugin */
        $plugin = Plugin::query()->where('slug', 'example-seo')->firstOrFail();
        app(PluginManager::class)->enable($plugin);

        return $plugin;
    }
}
