<?php

namespace App\Services;

use App\Support\JsonSchemaValidator;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupManifest
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $schema = null;

    public function __construct(
        private readonly JsonSchemaValidator $validator,
    ) {}

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

        $schemaPath = resource_path('schemas/backup.schema.json');
        $decoded = json_decode(File::get($schemaPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Unable to decode backup schema [{$schemaPath}].");
        }

        return $this->schema = $decoded;
    }
}
