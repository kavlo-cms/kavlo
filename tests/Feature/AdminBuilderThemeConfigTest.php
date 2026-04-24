<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Menu;
use App\Models\Theme;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBuilderThemeConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Theme::query()->create([
            'name' => 'Midnight Blue',
            'slug' => Theme::DEFAULT_THEME_SLUG,
            'path' => base_path('themes/'.Theme::DEFAULT_THEME_SLUG),
            'is_active' => true,
            'version' => '1.0.0',
        ]);
    }

    public function test_form_builder_uses_the_active_theme_config(): void
    {
        $admin = $this->adminUser();
        $form = Form::query()->create([
            'name' => 'Contact',
            'slug' => 'contact',
            'description' => 'Lead capture form.',
            'blocks' => [],
            'submission_action' => 'core.store-submission',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.forms.edit', $form))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Forms/Edit')
                ->where('themeConfig.slug', Theme::DEFAULT_THEME_SLUG)
                ->where('themeConfig.canvas.class', 'bg-slate-950 text-slate-200')
                ->where('themeConfig.blockStyles.textColorPresets.0.label', 'Moonlight')
                ->where('themeConfig.blockStyles.textColorPresets.0.value', '#e2e8f0')
            );
    }

    public function test_menu_builder_uses_the_active_theme_config(): void
    {
        $admin = $this->adminUser();
        $menu = Menu::query()->create([
            'name' => 'Main Navigation',
            'slug' => 'main-navigation',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.menus.edit', $menu))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Menus/Edit')
                ->where('themeConfig.slug', Theme::DEFAULT_THEME_SLUG)
                ->where('themeConfig.canvas.class', 'bg-slate-950 text-slate-200')
            );
    }

    private function adminUser(): User
    {
        /** @var Role $role */
        $role = Role::findByName('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
