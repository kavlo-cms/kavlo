<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\User;
use App\Services\DataHubRegistry;
use App\Services\EmbeddableFormRegistry;
use App\Services\FormBuilder;
use App\Services\HookManager;
use App\Services\PageTypeManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PluginInstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $pluginDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->pluginDirectory = base_path('plugins/TestPluginInstall');
        File::deleteDirectory($this->pluginDirectory);
        File::ensureDirectoryExists($this->pluginDirectory.'/database/migrations');

        $this->writePluginManifest();

        File::put($this->pluginDirectory.'/TestPluginInstallPlugin.php', <<<'PHP'
<?php

namespace Plugins\TestPluginInstall;

use App\Contracts\PluginBase;
use App\Plugins\PluginContext;

class TestPluginInstallPlugin implements PluginBase
{
    public function boot(PluginContext $context): void
    {
        $context->addFilter('plugins.test', fn ($value) => $value . '-booted');
        $context->addFilter('blocks.available', fn (array $blocks) => [...$blocks, [
            'type' => 'plugin_test_plugin_install_builder_block',
            'label' => 'Plugin Builder Block',
            'group' => 'components',
            'icon' => 'Plug',
            'fields' => [],
            'defaultData' => [],
        ]]);
        $context->addFilter('page_types', fn (array $types) => [...$types, [
            'type' => 'plugin_test_plugin_install_page_type',
            'label' => 'Plugin Page Type',
            'view' => 'pages.show',
        ]]);
        $context->registerFormBlock([
            'type' => 'plugin_test_plugin_install_block',
            'label' => 'Plugin Block',
            'group' => 'plugin',
            'icon' => 'Plug',
            'defaultData' => [],
        ]);
        $context->registerFormAction([
            'key' => 'plugin_test_plugin_install_action',
            'label' => 'Plugin Action',
            'description' => 'Plugin-provided form action.',
            'fields' => [],
            'handler' => fn () => ['message' => 'Handled by plugin'],
        ]);
        $context->registerEmbeddableForm([
            'key' => 'plugin_test_plugin_install_form',
            'label' => 'Plugin Embeddable Form',
            'preview_html' => '<form></form>',
        ]);
        $context->registerDataHubChannel([
            'key' => 'plugin_test_plugin_install_channel',
            'label' => 'Plugin Channel',
            'type' => 'webhook',
        ]);
        $context->registerDataHubResource([
            'key' => 'plugin_test_plugin_install_resource',
            'label' => 'Plugin Resource',
            'supports' => ['graphql'],
            'fields' => ['name'],
        ]);
        $context->registerMediaUsage(fn () => [[
            'type' => 'plugin',
            'label' => 'Plugin Asset',
            'href' => '/admin/plugins/test-plugin-install',
            'context' => 'Plugin-managed media',
        ]]);

        $storage = $context->storage();
        $http = $context->http()
            ->acceptJson()
            ->withHeaders(['X-Test-Plugin' => $context->slug()]);

        $context->addFilter('plugins.capabilities', fn ($value) => array_merge($value, [
            'private_storage' => $storage->path('test.txt'),
            'public_storage' => $storage->path('asset.txt', 'public'),
            'public_url' => $storage->url('asset.txt'),
            'http_headers' => $http->defaultHeaders(),
            'http_timeout' => $http->defaultTimeout(),
        ]));
    }
}
PHP);

        File::put($this->pluginDirectory.'/database/migrations/2026_04_17_000000_create_plugin_install_test_items_table.php', <<<'PHP'
<?php

use App\Plugins\PluginMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends PluginMigration
{
    public function up(): void
    {
        $this->createTable('items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $this->timestamps($table);
        });
    }

    public function down(): void
    {
        $this->dropTableIfExists('items');
    }
};
PHP);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function writePluginManifest(array $overrides = []): void
    {
        $manifest = array_replace_recursive([
            '$schema' => '../../resources/schemas/plugin.schema.json',
            'name' => 'Test Plugin Install',
            'entrypoint' => 'Plugins\\TestPluginInstall\\TestPluginInstallPlugin',
            'scopes' => ['admin_nav:write', 'hooks:write', 'migrations:write', 'models:read', 'forms:write', 'datahub:write', 'media:read', 'storage:write', 'http:write'],
            'model_namespace' => 'Plugins\\TestPluginInstall\\Models',
            'admin_nav' => [
                [
                    'group' => 'Content',
                    'title' => 'Test Plugin',
                    'href' => '/admin/plugins/test-plugin-install',
                    'icon' => 'plug',
                    'permission' => 'view plugins',
                ],
            ],
        ], $overrides);

        File::put($this->pluginDirectory.'/plugin.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginDirectory);

        parent::tearDown();
    }

    public function test_enabling_plugin_registers_scoped_capabilities_and_runs_plugin_migrations(): void
    {
        $user = $this->adminUser();
        $plugin = Plugin::create([
            'slug' => 'TestPluginInstall',
            'name' => 'Test Plugin Install',
            'is_enabled' => false,
        ]);

        $this->assertFalse(Schema::hasTable('plugin_test_plugin_install_items'));

        $this->actingAs($user)
            ->post(route('admin.plugins.toggle', $plugin))
            ->assertRedirect();

        $this->assertTrue($plugin->fresh()->is_enabled);
        $this->assertTrue(Schema::hasTable('plugin_test_plugin_install_items'));
        $this->assertSame('-booted', app(HookManager::class)->applyFilters('plugins.test', ''));
        $this->assertContains([
            'group' => 'Content',
            'title' => 'Test Plugin',
            'href' => '/admin/plugins/test-plugin-install',
            'icon' => 'plug',
            'permission' => 'view plugins',
        ], app(HookManager::class)->applyFilters('admin.nav', []));
        $this->assertContains('plugin_test_plugin_install_builder_block', array_column(app(HookManager::class)->applyFilters('blocks.available', []), 'type'));
        $this->assertContains('plugin_test_plugin_install_page_type', array_column(PageTypeManager::all(), 'type'));

        $this->assertContains('plugin_test_plugin_install_block', array_column(FormBuilder::availableBlocks(), 'type'));
        $this->assertContains('plugin_test_plugin_install_action', array_column(FormBuilder::publicActions(), 'key'));
        $this->assertContains('plugin_test_plugin_install_form', array_column(EmbeddableFormRegistry::registeredForms(), 'key'));
        $this->assertContains('plugin_test_plugin_install_channel', array_column(app(DataHubRegistry::class)->channels(), 'key'));
        $this->assertContains('plugin_test_plugin_install_resource', array_column(app(DataHubRegistry::class)->resources(), 'key'));
        $this->assertContains([
            'type' => 'plugin',
            'label' => 'Plugin Asset',
            'href' => '/admin/plugins/test-plugin-install',
            'context' => 'Plugin-managed media',
        ], app(HookManager::class)->applyFilters('media.usage.references', [], new Media));

        $capabilities = app(HookManager::class)->applyFilters('plugins.capabilities', []);
        $this->assertSame(storage_path('app/plugins/TestPluginInstall/test.txt'), $capabilities['private_storage']);
        $this->assertSame(storage_path('app/public/plugins/TestPluginInstall/asset.txt'), $capabilities['public_storage']);
        $this->assertStringEndsWith('/storage/plugins/TestPluginInstall/asset.txt', $capabilities['public_url']);
        $this->assertSame('cms-plugin/TestPluginInstall', $capabilities['http_headers']['User-Agent']);
        $this->assertSame('application/json', $capabilities['http_headers']['Accept']);
        $this->assertSame('TestPluginInstall', $capabilities['http_headers']['X-Test-Plugin']);
        $this->assertSame(10, $capabilities['http_timeout']);
    }

    public function test_enabling_plugin_rejects_plugin_migrations_that_do_not_use_the_base_class(): void
    {
        File::put($this->pluginDirectory.'/database/migrations/2026_04_17_000001_create_bad_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
    }
};
PHP);

        $user = $this->adminUser();
        $plugin = Plugin::create([
            'slug' => 'TestPluginInstall',
            'name' => 'Test Plugin Install',
            'is_enabled' => false,
        ]);

        $this->actingAs($user)
            ->post(route('admin.plugins.toggle', $plugin))
            ->assertSessionHasErrors('plugin');

        $this->assertFalse($plugin->fresh()->is_enabled);
    }

    public function test_enabling_plugin_does_not_run_any_migrations_when_a_later_path_is_invalid(): void
    {
        File::ensureDirectoryExists($this->pluginDirectory.'/database/extra-migrations');
        $this->writePluginManifest([
            'migrations' => [
                'database/migrations',
                'database/extra-migrations',
            ],
        ]);

        File::put($this->pluginDirectory.'/database/extra-migrations/2026_04_17_000001_create_bad_table.php', <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
    }
};
PHP);

        $user = $this->adminUser();
        $plugin = Plugin::create([
            'slug' => 'TestPluginInstall',
            'name' => 'Test Plugin Install',
            'is_enabled' => false,
        ]);

        $this->actingAs($user)
            ->post(route('admin.plugins.toggle', $plugin))
            ->assertSessionHasErrors('plugin');

        $this->assertFalse($plugin->fresh()->is_enabled);
        $this->assertFalse(Schema::hasTable('plugin_test_plugin_install_items'));
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
