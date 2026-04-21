<?php

namespace Tests\Feature;

use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_discovery_defaults_to_midnight_blue(): void
    {
        Theme::discover();

        $theme = Theme::where('slug', Theme::DEFAULT_THEME_SLUG)->first();

        $this->assertNotNull($theme);
        $this->assertTrue($theme->is_active);
    }
}
