<?php

namespace App\Plugins;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

abstract class PluginMigration extends Migration
{
    private ?string $pluginSlug = null;

    final protected function table(string $name): string
    {
        $normalized = Str::snake(trim($name));

        if ($normalized === '' || preg_match('/^[a-z0-9_]+$/', $normalized) !== 1) {
            throw new RuntimeException('Plugin migration table names must contain only letters, numbers, and underscores.');
        }

        return $this->tablePrefix().$normalized;
    }

    final protected function createTable(string $name, \Closure $callback): void
    {
        Schema::create($this->table($name), $callback);
    }

    final protected function updateTable(string $name, \Closure $callback): void
    {
        Schema::table($this->table($name), $callback);
    }

    final protected function dropTableIfExists(string $name): void
    {
        Schema::dropIfExists($this->table($name));
    }

    final protected function renameTable(string $from, string $to): void
    {
        Schema::rename($this->table($from), $this->table($to));
    }

    final protected function timestamps(Blueprint $table): void
    {
        $table->timestamps();
    }

    final protected function tablePrefix(): string
    {
        return 'plugin_'.Str::snake($this->pluginSlug()).'_';
    }

    final protected function pluginSlug(): string
    {
        if ($this->pluginSlug !== null) {
            return $this->pluginSlug;
        }

        $path = (new \ReflectionClass($this))->getFileName();

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to resolve the plugin migration path.');
        }

        $directory = dirname($path);

        while ($directory !== dirname($directory)) {
            if (is_file($directory.'/plugin.json')) {
                return $this->pluginSlug = basename($directory);
            }

            $directory = dirname($directory);
        }

        throw new RuntimeException('Plugin migrations must live inside a plugin directory.');
    }
}
