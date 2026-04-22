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

    public function test_it_rejects_invalid_text_color_presets(): void
    {
        $errors = app(ThemeManifest::class)->validate([
            'name' => 'Broken Theme',
            'slug' => 'broken-theme',
            'blockStyles' => [
                'textColorPresets' => [
                    [
                        'label' => 'Broken',
                        'value' => 'sky',
                    ],
                ],
            ],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertContains('$.blockStyles.textColorPresets[0].value does not match the required pattern.', $errors);
    }
}
