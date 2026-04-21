<?php

namespace App\Services;

use App\Support\JsonSchemaValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PluginConfigManifest
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $schema = null;

    public function __construct(
        private readonly JsonSchemaValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function decodeFromPath(string $path): ?array
    {
        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadFromPath(string $path, bool $swallowInvalid = false, ?string $pluginSlug = null): ?array
    {
        $manifest = $this->decodeFromPath($path);
        $pluginSlug ??= basename(dirname($path));

        if (! is_array($manifest)) {
            if ($swallowInvalid) {
                Log::warning('Invalid plugin manifest ignored.', [
                    'plugin' => $pluginSlug,
                    'path' => $path,
                    'error' => 'Manifest is not valid JSON.',
                ]);

                return null;
            }

            throw new RuntimeException("Plugin [{$pluginSlug}] has an invalid plugin.json file.");
        }

        $errors = $this->validate($manifest);

        if ($errors === []) {
            return $manifest;
        }

        if ($swallowInvalid) {
            Log::warning('Invalid plugin manifest ignored.', [
                'plugin' => $pluginSlug,
                'path' => $path,
                'errors' => $errors,
            ]);

            return null;
        }

        throw new RuntimeException(
            "Plugin [{$pluginSlug}] has an invalid plugin.json manifest: {$errors[0]}"
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    public function validate(array $manifest): array
    {
        return $this->validator->validate($manifest, $this->schema());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $schemaPath = resource_path('schemas/plugin.schema.json');
        $decoded = json_decode(File::get($schemaPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Unable to decode plugin schema [{$schemaPath}].");
        }

        return $this->schema = $decoded;
    }
}
