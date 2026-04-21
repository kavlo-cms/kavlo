<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyManager
{
    public function abilities(): array
    {
        return [
            [
                'key' => 'graphql.read',
                'label' => 'GraphQL read',
                'description' => 'Allow read access to the DataHub GraphQL endpoint.',
            ],
            [
                'key' => 'graphql.preview',
                'label' => 'GraphQL preview',
                'description' => 'Allow preview access to draft and unpublished content in GraphQL.',
            ],
        ];
    }

    public function abilityKeys(): array
    {
        return array_column($this->abilities(), 'key');
    }

    public function normalizeAbilities(array $abilities): array
    {
        $normalized = array_values(array_unique(array_intersect(
            array_map(static fn ($ability) => (string) $ability, $abilities),
            $this->abilityKeys(),
        )));

        if (in_array('graphql.preview', $normalized, true) && ! in_array('graphql.read', $normalized, true)) {
            $normalized[] = 'graphql.read';
        }

        sort($normalized);

        return $normalized;
    }

    public function issue(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt = null): array
    {
        $plainTextToken = 'cms_'.Str::random(40);
        $apiKey = $user->apiKeys()->create([
            'name' => $name,
            'key_prefix' => substr($plainTextToken, 0, 16),
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities' => $this->normalizeAbilities($abilities),
            'expires_at' => $expiresAt,
        ]);

        return [$apiKey, $plainTextToken];
    }

    public function rotate(ApiKey $apiKey): array
    {
        [$replacement, $plainTextToken] = $this->issue(
            $apiKey->user,
            $apiKey->name,
            $apiKey->abilities ?? [],
            $apiKey->expires_at,
        );

        $this->revoke($apiKey);

        return [$replacement, $plainTextToken];
    }

    public function findFromToken(?string $token): ?ApiKey
    {
        $token = trim((string) $token);

        if ($token === '') {
            return null;
        }

        return ApiKey::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function markUsed(ApiKey $apiKey, Request $request): void
    {
        $apiKey->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();
    }

    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->forceFill([
            'revoked_at' => now(),
        ])->saveQuietly();
    }
}
