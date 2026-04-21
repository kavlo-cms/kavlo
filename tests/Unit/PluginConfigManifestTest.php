<?php

namespace Tests\Unit;

use App\Services\PluginConfigManifest;
use Tests\TestCase;

class PluginConfigManifestTest extends TestCase
{
    public function test_it_accepts_the_example_plugin_manifest(): void
    {
        $manifest = app(PluginConfigManifest::class)->decodeFromPath(base_path('plugins/example-seo/plugin.json'));

        $this->assertIsArray($manifest);
        $this->assertSame([], app(PluginConfigManifest::class)->validate($manifest));
    }

    public function test_it_rejects_invalid_scope_shapes(): void
    {
        $errors = app(PluginConfigManifest::class)->validate([
            'name' => 'Broken Plugin',
            'entrypoint' => 'Plugins\\Broken\\BrokenPlugin',
            'scopes' => ['hooks'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('$.scopes[0] does not match the required pattern.', $errors);
    }
}
