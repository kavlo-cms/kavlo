<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemHealthService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemHealthFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_health_endpoint_exposes_system_report(): void
    {
        Cache::forever(SystemHealthService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->toIso8601String());

        $this->get(route('health'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checked_at',
                'summary' => ['ok', 'warning', 'fail'],
                'checks' => [
                    ['key', 'label', 'status', 'message', 'meta'],
                ],
            ])
            ->assertJsonPath('checks.0.key', 'database');
    }

    public function test_public_health_endpoint_warns_when_scheduler_has_not_pinged(): void
    {
        Cache::forget(SystemHealthService::SCHEDULER_HEARTBEAT_CACHE_KEY);

        $response = $this->get(route('health'));

        $response->assertOk();
        $response->assertJsonPath('status', 'warning');
        $response->assertJsonFragment([
            'key' => 'scheduler',
            'status' => 'warning',
        ]);
    }

    public function test_admin_dashboard_includes_system_health_report(): void
    {
        Cache::forever(SystemHealthService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->toIso8601String());

        $this->actingAs($this->adminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert) => $assert
                ->component('Dashboard/Index')
                ->has('systemHealth.checks')
                ->where('systemHealth.checks.0.key', 'database')
            );
    }

    public function test_public_health_endpoint_warns_when_queue_has_failed_jobs(): void
    {
        Cache::forever(SystemHealthService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->toIso8601String());
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database.after_commit', true);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => 'mail',
            'payload' => '{}',
            'exception' => 'Queue failed',
            'failed_at' => now(),
        ]);

        $this->get(route('health'))
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'queue',
                'status' => 'warning',
            ])
            ->assertJsonPath('checks.3.meta.failed_jobs', 1);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('admin', 'web'));

        return $user;
    }
}
