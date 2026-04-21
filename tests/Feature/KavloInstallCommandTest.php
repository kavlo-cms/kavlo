<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KavloInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_kavlo_install_seeds_admin_and_starter_content(): void
    {
        $this->artisan('kavlo:install', [
            '--admin-name' => 'Kavlo Admin',
            '--admin-email' => 'owner@example.com',
            '--admin-password' => 'secret-pass-123',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'name' => 'Kavlo Admin',
        ]);
        $this->assertTrue(User::query()->where('email', 'owner@example.com')->firstOrFail()->hasRole('super-admin'));
        $this->assertDatabaseHas('pages', ['slug' => 'home', 'is_homepage' => true]);
        $this->assertDatabaseHas('pages', ['slug' => 'about']);
        $this->assertDatabaseHas('pages', ['slug' => 'contact']);
        $this->assertDatabaseHas('forms', ['slug' => 'contact']);
        $this->assertDatabaseHas('menus', ['slug' => 'main']);
        $this->assertDatabaseHas('redirects', ['from_url' => '/start', 'to_url' => '/']);
        $this->assertSame('Kavlo Site', Setting::get('site_name'));
        $this->assertSame('owner@example.com', Setting::get('admin_email'));

        $this->assertSame(3, Menu::query()->where('slug', 'main')->firstOrFail()->items()->count());
        $this->assertSame('contact', Form::query()->firstOrFail()->slug);
    }

    public function test_kavlo_demo_seeds_showcase_content(): void
    {
        $this->artisan('kavlo:install', [
            '--admin-name' => 'Demo Admin',
            '--admin-email' => 'demo@example.com',
            '--admin-password' => 'secret-pass-123',
        ])->assertSuccessful();

        $this->artisan('kavlo:demo')->assertSuccessful();

        $this->assertDatabaseHas('pages', ['slug' => 'features']);
        $this->assertDatabaseHas('pages', ['slug' => 'thank-you']);
        $this->assertDatabaseHas('redirects', ['from_url' => '/company', 'to_url' => '/about']);
        $this->assertDatabaseHas('email_templates', ['slug' => 'contact-notification']);

        $menu = Menu::query()->where('slug', 'main')->firstOrFail();
        $pageIds = $menu->items()->pluck('page_id')->all();

        $this->assertContains(Page::query()->where('slug', 'features')->value('id'), $pageIds);
        $this->assertContains(Page::query()->where('slug', 'thank-you')->value('id'), $pageIds);
        $this->assertSame('contact-notification', EmailTemplate::query()->firstOrFail()->slug);
        $this->assertSame(5, $menu->items()->count());
        $this->assertSame(2, Redirect::query()->count());
    }
}
