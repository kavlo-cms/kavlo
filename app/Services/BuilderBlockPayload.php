<?php

namespace App\Services;

use App\Support\JsonSchemaValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BuilderBlockPayload
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $schema = null;

    public function __construct(
        private readonly JsonSchemaValidator $validator,
    ) {}

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, string>
     */
    public function validateStructure(array $blocks): array
    {
        return $this->validateStructureAt($blocks, '$');
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  list<string>  $allowedTypes
     * @return array<int, string>
     */
    public function validateAllowedTypes(array $blocks, array $allowedTypes): array
    {
        $errors = [];

        foreach ($this->flatten($this->normalizeBlocks($blocks)) as $index => $block) {
            $type = trim((string) ($block['type'] ?? ''));

            if ($type === '' || ! in_array($type, $allowedTypes, true)) {
                $errors[] = 'Block '.($index + 1)." uses unsupported type [{$type}].";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function normalizeBlocks(array $blocks): array
    {
        return array_values(array_map(function (mixed $block, int $index) {
            $payload = is_array($block) ? $block : [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            foreach (array_keys($data) as $key) {
                if (($key === 'children' || preg_match('/^col_\d+$/', $key) === 1) && is_array($data[$key])) {
                    $data[$key] = $this->normalizeBlocks($data[$key]);
                }
            }

            return [
                'id' => (string) ($payload['id'] ?? Str::uuid()),
                'type' => (string) ($payload['type'] ?? ''),
                'data' => $data,
                'order' => isset($payload['order']) ? (int) $payload['order'] : $index,
            ];
        }, $blocks, array_keys($blocks)));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $schemaPath = resource_path('schemas/builder-blocks.schema.json');
        $decoded = json_decode(File::get($schemaPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Unable to decode builder block schema [{$schemaPath}].");
        }

        return $this->schema = $decoded;
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, string>
     */
    private function validateStructureAt(array $blocks, string $path): array
    {
        $errors = array_map(
            fn (string $error) => preg_replace('/^\$/', $path, $error) ?? $error,
            $this->validator->validate($blocks, $this->schema()),
        );

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $data = $block['data'] ?? null;

            if (! is_array($data)) {
                continue;
            }

            foreach ($data as $key => $value) {
                if ($key !== 'children' && preg_match('/^col_\d+$/', (string) $key) !== 1) {
                    continue;
                }

                if (! is_array($value)) {
                    $errors[] = "{$path}[{$index}].data.{$key} must be an array of blocks.";

                    continue;
                }

                $errors = [
                    ...$errors,
                    ...$this->validateStructureAt($value, "{$path}[{$index}].data.{$key}"),
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function flatten(array $blocks): array
    {
        $flat = [];

        foreach ($blocks as $block) {
            $flat[] = $block;

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            foreach ($data as $key => $children) {
                if (($key === 'children' || preg_match('/^col_\d+$/', (string) $key) === 1) && is_array($children)) {
                    array_push($flat, ...$this->flatten($this->normalizeBlocks($children)));
                }
            }
        }

        return $flat;
    }
}
