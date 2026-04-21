<?php

namespace App\Console\Commands;

use App\Models\Theme;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('make:block {name?} {theme?}')]
#[Description('Creates a new block (Global or Theme-specific)')]
class CreateBlockCommand extends Command implements PromptsForMissingInput
{
    public function handle()
    {
        $name = Str::slug($this->argument('name'));
        $theme = $this->argument('theme'); // 'global' or the theme slug

        // Define the base path based on whether it is global or theme-specific
        $isGlobal = $theme === 'global';
        $basePath = $isGlobal
            ? base_path("blocks/{$name}")
            : base_path("themes/{$theme}/blocks/{$name}");

        if (File::exists($basePath)) {
            $this->error("Block '{$name}' already exists in ".($isGlobal ? 'global blocks' : "theme '{$theme}'").'!');

            return;
        }

        File::makeDirectory($basePath, 0o755, true);

        // Naming for package.json workspace
        $npmName = $isGlobal ? "@blocks/{$name}" : "@blocks/{$theme}-{$name}";

        $packageJson = [
            'name' => $npmName,
            'version' => '1.0.0',
            'private' => true,
            'type' => 'module',
            'main' => './Editor.vue',
        ];

        File::put(
            "{$basePath}/package.json",
            json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        File::put(
            "{$basePath}/block.json",
            json_encode([
                '$schema' => $isGlobal
                    ? '../../resources/schemas/block.schema.json'
                    : '../../../../resources/schemas/block.schema.json',
                'label' => Str::headline($name),
                'description' => '',
                'group' => 'components',
                'icon' => 'Square',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 1. Create render.blade.php
        File::put("{$basePath}/render.blade.php", "\n<div class='block-{$name}'>\n    {{ \$data['content'] ?? '' }}\n</div>");

        // 2. Create Editor.vue
        File::put("{$basePath}/Editor.vue", $this->getVueTemplate($name));

        $location = $isGlobal ? 'Global Library' : "Theme '{$theme}'";
        $this->info("Block '{$name}' created successfully in {$location}.");

        if (confirm("Run 'npm install' to link the new workspace?", true)) {
            $this->output->write('Linking workspaces...');
            shell_exec('npm install');
            $this->info(' Done!');
        }
    }

    protected function getVueTemplate($name): string
    {
        return <<<VUE
            <script setup lang="ts">
            interface Attributes {
                content: string;
            }
            const props = defineProps<{ modelValue: Attributes }>();
            const emit = defineEmits(['update:modelValue']);
            </script>

            <template>
                <div class="p-4 border border-dashed rounded bg-white">
                    <label class="block text-sm font-bold mb-2 text-gray-700">{$name} Block</label>
                    <input
                        type="text"
                        :value="modelValue.content"
                        @input="e => emit('update:modelValue', { ...modelValue, content: (e.target as HTMLInputElement).value })"
                        class="w-full p-2 border rounded shadow-sm focus:ring focus:ring-opacity-50"
                        placeholder="Enter content..."
                    />
                </div>
            </template>
            VUE;
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        // Get registered themes from DB
        $registeredThemes = Theme::pluck('name', 'slug')->toArray();

        // Get physical theme directories
        $options = collect(File::directories(base_path('themes')))->mapWithKeys(function ($path) use ($registeredThemes) {
            $slug = basename($path);
            $label = isset($registeredThemes[$slug])
                ? "{$registeredThemes[$slug]} ($slug) ✅"
                : "$slug [Not Registered] ⚠️";

            return [$slug => $label];
        })->toArray();

        // Add 'Global' as the first option
        $options = ['global' => '🌎 Global Library (Shared)'] + $options;

        return [
            'name' => fn () => text('What is the name of the block?', placeholder: 'e.g. Hero Section', required: true),
            'theme' => fn () => select(
                label: 'Where should this block be stored?',
                options: $options,
                default: 'global'
            ),
        ];
    }
}
