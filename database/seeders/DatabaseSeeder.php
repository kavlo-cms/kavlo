<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        Theme::discover();

        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')],
        );

        $user->assignRole('super-admin');

        $theme = Theme::where('slug', Theme::DEFAULT_THEME_SLUG)->first()
            ?? Theme::orderBy('name')->first();

        if ($theme && ! $theme->is_active) {
            $theme->activate();
        }
    }
}
