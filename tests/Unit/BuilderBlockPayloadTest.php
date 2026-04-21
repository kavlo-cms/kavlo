<?php

namespace Tests\Unit;

use App\Services\BuilderBlockPayload;
use Tests\TestCase;

class BuilderBlockPayloadTest extends TestCase
{
    public function test_it_accepts_nested_builder_blocks(): void
    {
        $errors = app(BuilderBlockPayload::class)->validateStructure([
            [
                'id' => 'section-1',
                'type' => 'section',
                'data' => [
                    'children' => [
                        [
                            'id' => 'heading-1',
                            'type' => 'heading',
                            'data' => [],
                            'order' => 0,
                        ],
                    ],
                ],
                'order' => 0,
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_it_rejects_malformed_nested_blocks(): void
    {
        $errors = app(BuilderBlockPayload::class)->validateStructure([
            [
                'id' => 'columns-1',
                'type' => 'columns',
                'data' => [
                    'col_0' => [
                        [
                            'type' => 'heading',
                            'data' => [],
                        ],
                    ],
                ],
                'order' => 0,
            ],
        ]);

        $this->assertContains('$[0].data.col_0[0].id is required.', $errors);
    }
}
