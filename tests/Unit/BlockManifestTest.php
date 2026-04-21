<?php

namespace Tests\Unit;

use App\Facades\Hook;
use App\Services\BlockManager;
use App\Services\BlockManifest;
use App\Services\HookManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BlockManifestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HookManager::class, new HookManager);
        Facade::clearResolvedInstance(HookManager::class);
    }

    public function test_it_accepts_the_built_in_heading_block_manifest(): void
    {
        $manifest = app(BlockManifest::class)->decodeFromPath(base_path('blocks/heading/block.json'));

        $this->assertIsArray($manifest);
        $this->assertSame([], app(BlockManifest::class)->validate($manifest));
    }

    public function test_invalid_block_manifest_falls_back_to_inferred_defaults(): void
    {
        $basePath = base_path('storage/framework/testing/blocks-schema');
        $blockPath = $basePath.'/broken-block';

        File::deleteDirectory($basePath);
        File::makeDirectory($blockPath, 0755, true);
        File::put($blockPath.'/block.json', json_encode([
            'label' => 'Broken Block',
            'group' => 'components',
            'icon' => 'Square',
            'fields' => [
                [
                    'key' => 'cta',
                    'label' => 'CTA',
                    'type' => 'unknown',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Hook::addFilter('blocks.paths', fn () => [$basePath]);

        $blocks = BlockManager::getAvailableBlocks('unused');

        File::deleteDirectory($basePath);

        $this->assertCount(1, $blocks);
        $this->assertSame('broken-block', $blocks[0]['type']);
        $this->assertSame('Broken Block', $blocks[0]['label']);
        $this->assertArrayNotHasKey('fields', $blocks[0]);
    }
}
