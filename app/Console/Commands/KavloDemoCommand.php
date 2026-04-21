<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\KavloSetupService;
use Illuminate\Console\Command;

class KavloDemoCommand extends Command
{
    protected $signature = 'kavlo:demo {--author-email= : Existing user email to own demo content}';

    protected $description = 'Seed optional Kavlo demo/showcase content';

    public function handle(KavloSetupService $setup): int
    {
        $author = $this->resolveAuthor();

        if (! $author) {
            $this->error('No suitable author account exists. Run kavlo:install first or provide --author-email.');

            return self::FAILURE;
        }

        $summary = $setup->installDemoContent($author);

        $this->components->info("Demo content ready: {$summary['pages']} pages, {$summary['email_templates']} email template, {$summary['redirects']} redirect.");

        return self::SUCCESS;
    }

    private function resolveAuthor(): ?User
    {
        $email = $this->option('author-email');

        if (is_string($email) && $email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return User::role('super-admin')->first()
            ?? User::role('admin')->first()
            ?? User::query()->orderBy('id')->first();
    }
}
