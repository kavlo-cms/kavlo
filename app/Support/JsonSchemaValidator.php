<?php

namespace App\Support;

class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    public function validate(mixed $data, array $schema): array
    {
        $errors = [];

        $this->validateNode($data, $schema, '$', $errors);

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $errors
     */
    private function validateNode(mixed $data, array $schema, string $path, array &$errors): void
    {
        if (array_key_exists('type', $schema)) {
            $types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];

            if (! $this->matchesAnyType($data, $types)) {
                $errors[] = sprintf(
                    '%s must be of type %s, %s given.',
                    $path,
                    implode('|', $types),
                    $this->describeType($data),
                );

                return;
            }
        }

        if (array_key_exists('enum', $schema) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = sprintf(
                '%s must be one of [%s].',
                $path,
                implode(', ', array_map(
                    static fn (mixed $value): string => is_string($value) ? $value : json_encode($value),
                    $schema['enum'],
                )),
            );
        }

        if (is_string($data)) {
            if (isset($schema['minLength']) && mb_strlen($data) < (int) $schema['minLength']) {
                $errors[] = sprintf('%s must be at least %d characters.', $path, (int) $schema['minLength']);
            }

            if (isset($schema['maxLength']) && mb_strlen($data) > (int) $schema['maxLength']) {
                $errors[] = sprintf('%s must be at most %d characters.', $path, (int) $schema['maxLength']);
            }

            if (isset($schema['pattern']) && ! preg_match($this->pattern((string) $schema['pattern']), $data)) {
                $errors[] = sprintf('%s does not match the required pattern.', $path);
            }
        }

        if (! is_array($data)) {
            return;
        }

        if ($this->isObject($data)) {
            $this->validateObject($data, $schema, $path, $errors);

            return;
        }

        $this->validateArray($data, $schema, $path, $errors);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $errors
     */
    private function validateObject(array $data, array $schema, string $path, array &$errors): void
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($required as $property) {
            if (! array_key_exists((string) $property, $data)) {
                $errors[] = sprintf('%s.%s is required.', $path, $property);
            }
        }

        foreach ($properties as $property => $propertySchema) {
            if (! array_key_exists($property, $data) || ! is_array($propertySchema)) {
                continue;
            }

            $this->validateNode($data[$property], $propertySchema, $path.'.'.$property, $errors);
        }

        $additionalProperties = $schema['additionalProperties'] ?? true;

        if ($additionalProperties === false) {
            foreach (array_keys($data) as $property) {
                if (! array_key_exists($property, $properties)) {
                    $errors[] = sprintf('%s.%s is not allowed.', $path, $property);
                }
            }

            return;
        }

        if (! is_array($additionalProperties)) {
            return;
        }

        foreach ($data as $property => $value) {
            if (array_key_exists($property, $properties)) {
                continue;
            }

            $this->validateNode($value, $additionalProperties, $path.'.'.$property, $errors);
        }
    }

    /**
     * @param  array<int, mixed>  $data
     * @param  array<string, mixed>  $schema
     * @param  array<int, string>  $errors
     */
    private function validateArray(array $data, array $schema, string $path, array &$errors): void
    {
        if (isset($schema['minItems']) && count($data) < (int) $schema['minItems']) {
            $errors[] = sprintf('%s must contain at least %d items.', $path, (int) $schema['minItems']);
        }

        if (isset($schema['maxItems']) && count($data) > (int) $schema['maxItems']) {
            $errors[] = sprintf('%s must contain at most %d items.', $path, (int) $schema['maxItems']);
        }

        if (! is_array($schema['items'] ?? null)) {
            return;
        }

        foreach ($data as $index => $value) {
            $this->validateNode($value, $schema['items'], sprintf('%s[%d]', $path, $index), $errors);
        }
    }

    /**
     * @param  array<int, mixed>  $types
     */
    private function matchesAnyType(mixed $data, array $types): bool
    {
        foreach ($types as $type) {
            if ($this->matchesType($data, (string) $type)) {
                return true;
            }
        }

        return false;
    }

    private function matchesType(mixed $data, string $type): bool
    {
        return match ($type) {
            'array' => is_array($data) && array_is_list($data),
            'boolean' => is_bool($data),
            'integer' => is_int($data),
            'null' => is_null($data),
            'number' => is_int($data) || is_float($data),
            'object' => $this->isObject($data),
            'string' => is_string($data),
            default => true,
        };
    }

    private function isObject(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    private function describeType(mixed $data): string
    {
        if (is_array($data)) {
            return $this->isObject($data) ? 'object' : 'array';
        }

        return gettype($data);
    }

    private function pattern(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~u';
    }
}
