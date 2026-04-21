<?php

namespace Tests\Unit;

use App\Services\ThemeManifest;
use Tests\TestCase;

class ThemeManifestTest extends TestCase
{
    public function test_it_accepts_the_built_in_theme_manifest(): void
    {
        $manifest = app(ThemeManifest::class)->decodeFromPath(base_path('themes/midnight-blue/theme.json'));

        $this->assertIsArray($manifest);
        $this->assertSame([], app(ThemeManifest::class)->validate($manifest));
    }

    public function test_it_rejects_invalid_page_type_entries(): void
    {
        $errors = app(ThemeManifest::class)->validate([
            'name' => 'Broken Theme',
            'slug' => 'broken-theme',
            'pageTypes' => [
                [
                    'type' => 'Bad Type',
                    'label' => 'Broken',
                ],
            ],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('$.pageTypes[0].view is required.', $errors);
        $this->assertContains('$.pageTypes[0].type does not match the required pattern.', $errors);
    }
}
