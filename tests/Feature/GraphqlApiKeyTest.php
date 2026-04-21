<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\ApiKeyManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GraphqlApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_can_create_and_revoke_api_keys(): void
    {
        $user = $this->adminUser();

        $this->actingAsConfirmed($user)
            ->post('/admin/account/api-keys', [
                'name' => 'DataHub',
                'abilities' => ['graphql.read', 'graphql.preview'],
                'expires_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('api_keys', [
            'user_id' => $user->id,
            'name' => 'DataHub',
            'revoked_at' => null,
        ]);

        $this->assertDatabaseMissing('api_keys', [
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $apiKeyId = $user->apiKeys()->value('id');

        $this->actingAsConfirmed($user)
            ->delete("/admin/account/api-keys/{$apiKeyId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('api_keys', [
            'id' => $apiKeyId,
            'revoked_at' => null,
        ]);
    }

    public function test_graphql_api_keys_require_preview_scope_for_preview_queries(): void
    {
        Page::create([
            'title' => 'Draft Product Landing',
            'slug' => 'draft-product-landing',
            'type' => 'page',
            'is_published' => false,
        ]);

        $user = $this->adminUser();
        $apiKeys = app(ApiKeyManager::class);

        [, $readOnlyToken] = $apiKeys->issue($user, 'Read only', ['graphql.read']);
        [, $previewToken] = $apiKeys->issue($user, 'Preview', ['graphql.preview']);

        $query = <<<'GRAPHQL'
            query PreviewPage {
              page(path: "/draft-product-landing", preview: true) {
                title
              }
            }
        GRAPHQL;

        $readOnlyResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$readOnlyToken,
        ])->postJson('/graphql', [
            'query' => $query,
        ]);

        $readOnlyResponse->assertOk();
        $readOnlyResponse->assertJsonPath('data.page', null);

        $previewResponse = $this->withHeaders([
            'X-API-Key' => $previewToken,
        ])->postJson('/graphql', [
            'query' => $query,
        ]);

        $previewResponse->assertOk();
        $previewResponse->assertJsonPath('data.page.title', 'Draft Product Landing');

        $this->assertDatabaseMissing('api_keys', [
            'user_id' => $user->id,
            'last_used_at' => null,
        ]);

        $this->assertDatabaseMissing('api_keys', [
            'user_id' => $user->id,
            'last_used_ip' => null,
        ]);
    }

    public function test_graphql_rejects_invalid_api_keys(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->postJson('/graphql', [
            'query' => '{ routes { path } }',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('errors.0.message', 'Invalid API key.');
    }

    public function test_expired_api_keys_are_rejected(): void
    {
        $user = $this->adminUser();

        [, $expiredToken] = app(ApiKeyManager::class)->issue(
            $user,
            'Expired token',
            ['graphql.read'],
            CarbonImmutable::now()->subMinute(),
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$expiredToken,
        ])->postJson('/graphql', [
            'query' => '{ routes { path } }',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('errors.0.message', 'Invalid API key.');
    }

    public function test_account_page_can_rotate_api_keys(): void
    {
        $user = $this->adminUser();
        [$apiKey] = app(ApiKeyManager::class)->issue($user, 'DataHub', ['graphql.read']);

        $this->actingAsConfirmed($user)
            ->post("/admin/account/api-keys/{$apiKey->id}/rotate")
            ->assertRedirect();

        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
        ]);

        $this->assertNotNull($apiKey->fresh()->revoked_at);
        $this->assertSame(2, $user->fresh()->apiKeys()->count());
        $this->assertSame(1, $user->fresh()->apiKeys()->whereNull('revoked_at')->count());
    }

    public function test_graphql_requests_are_rate_limited_per_api_key(): void
    {
        config()->set('cms.api_keys.graphql_rate_limit_per_minute', 2);

        $user = $this->adminUser();
        [, $token] = app(ApiKeyManager::class)->issue($user, 'Rate limit token', ['graphql.read']);

        $headers = [
            'Authorization' => 'Bearer '.$token,
        ];

        $payload = [
            'query' => '{ routes { path } }',
        ];

        $this->withHeaders($headers)->postJson('/graphql', $payload)->assertOk();
        $this->withHeaders($headers)->postJson('/graphql', $payload)->assertOk();
        $this->withHeaders($headers)->postJson('/graphql', $payload)->assertStatus(429);
    }

    protected function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    protected function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession([
            'auth.password_confirmed_at' => time(),
        ]);
    }
}
