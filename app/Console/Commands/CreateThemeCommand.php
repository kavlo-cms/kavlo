<?php

namespace App\Console\Commands;

use App\Models\Theme;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

#[Signature('make:theme {name}')]
#[Description('Creates a new theme')]
class CreateThemeCommand extends Command implements PromptsForMissingInput
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        // 2. Now that we definitely have a name, calculate the slug and path.
        $slug = Str::slug($name);
        $path = base_path("themes/{$slug}");

        // 3. Safety check: Ensure slug isn't empty (in case of weird input)
        if (empty($slug)) {
            $this->error('Invalid theme name provided.');

            return;
        }

        if (File::exists($path)) {
            $this->error("Theme '{$name}' already exists.");

            return;
        }

        // 4. Create the folders (Fixed plurals)
        $dirs = ['blocks', 'views/layouts', 'assets/css', 'assets/js'];
        foreach ($dirs as $dir) {
            File::makeDirectory("{$path}/{$dir}", 0o755, true);
        }

        $packageJson = [
            'name' => "@themes/{$slug}",
            'version' => '1.0.0',
            'private' => true,
            'type' => 'module',
            'main' => './assets/js/app.ts',
            'dependencies' => new \stdClass,
        ];

        File::put(
            "{$path}/package.json",
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        File::put("{$path}/theme.json", json_encode([
            '$schema' => '../../resources/schemas/theme.schema.json',
            'name' => $name,
            'slug' => $slug,
        ], JSON_PRETTY_PRINT));

        File::put("{$path}/views/layouts/app.blade.php", "<html><body>@yield('content')</body></html>");

        Theme::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'path' => $path,
                'is_active' => false,
            ]
        );

        $this->info("Theme '{$name}' created successfully at {$path}");
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn () => text(
                label: 'What is the name of your new theme?',
                placeholder: 'E.g. Midnight Blue',
                validate: fn (string $value) => match (true) {
                    strlen($value) < 3 => 'The name must be at least 3 characters.',
                    default => null
                }
            ),
        ];
    }
}
