<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\KavloSetupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class KavloInstallCommand extends Command
{
    protected $signature = 'kavlo:install
        {--admin-name= : Initial super-admin name}
        {--admin-email= : Initial super-admin email}
        {--admin-password= : Initial super-admin password}
        {--with-demo : Also seed optional demo content}
        {--force : Run in production without confirmation}';

    protected $description = 'Install Kavlo, run first-time setup, and seed starter content';

    public function handle(KavloSetupService $setup): int
    {
        if ($this->getLaravel()->isProduction() && ! $this->option('force') && ! $this->confirm('This will modify the production database. Continue?')) {
            return self::FAILURE;
        }

        if (blank(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->components->info('Application key generated.');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', [
            '--class' => RolesAndPermissionsSeeder::class,
            '--force' => true,
        ]);
        $this->components->info('Database migrations completed.');

        $admin = $this->createOrUpdateAdminUser();
        $summary = $setup->install($admin);

        $this->components->info("Starter content ready: {$summary['pages']} pages, {$summary['forms']} form, {$summary['menus']} menu, {$summary['redirects']} redirect.");

        if ($this->option('with-demo') || (! app()->runningUnitTests() && $this->input->isInteractive() && $this->confirm('Seed optional demo/showcase content as well?', false))) {
            $demo = $setup->installDemoContent($admin);
            $this->components->info("Demo content ready: {$demo['pages']} pages, {$demo['email_templates']} email template, {$demo['redirects']} redirect.");
        }

        return self::SUCCESS;
    }

    private function createOrUpdateAdminUser(): User
    {
        $name = $this->option('admin-name')
            ?: ($this->input->isInteractive() ? $this->ask('Admin name', 'Administrator') : 'Administrator');

        $email = $this->option('admin-email')
            ?: ($this->input->isInteractive() ? $this->ask('Admin email', 'admin@example.com') : 'admin@example.com');

        $password = $this->option('admin-password');
        $generatedPassword = false;

        if (! $password && $this->input->isInteractive()) {
            $password = $this->secret('Admin password (leave blank to generate one)');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;

        if (! $user->exists) {
            if (! $password) {
                $password = Str::password(20);
                $generatedPassword = true;
            }

            $user->password = $password;
            $user->email_verified_at = now();
        } elseif ($password) {
            $user->password = $password;
        }

        $user->save();
        $user->syncRoles(['super-admin']);

        if ($generatedPassword) {
            $this->warn("Generated admin password for {$email}: {$password}");
        } else {
            $this->line("Admin user ready: {$email}");
        }

        return $user;
    }
}
