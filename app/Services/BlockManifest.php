<?php

namespace App\Services;

use App\Support\JsonSchemaValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BlockManifest
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
     * @return array<string, mixed>
     */
    public function loadFromPath(string $path): array
    {
        $manifest = $this->decodeFromPath($path);

        if (! is_array($manifest)) {
            return [];
        }

        $errors = $this->validate($manifest);

        if ($errors === []) {
            return $manifest;
        }

        Log::warning('Invalid block manifest ignored.', [
            'path' => $path,
            'errors' => $errors,
        ]);

        return [];
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

        $schemaPath = resource_path('schemas/block.schema.json');
        $decoded = json_decode(File::get($schemaPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Unable to decode block schema [{$schemaPath}].");
        }

        return $this->schema = $decoded;
    }
}
