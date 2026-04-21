<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Models\FormField;
use App\Services\FormBuilder;
use App\Services\HookManager;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class FormBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container;
        $container->instance(HookManager::class, new HookManager);

        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function test_it_converts_legacy_fields_into_builder_blocks()
    {
        $blocks = FormBuilder::legacyFieldsToBlocks([
            new FormField([
                'type' => 'email',
                'label' => 'Email Address',
                'key' => 'email_address',
                'placeholder' => 'you@example.com',
                'required' => true,
            ]),
            new FormField([
                'type' => 'select',
                'label' => 'Reason',
                'key' => 'reason',
                'options' => [
                    ['label' => 'Support', 'value' => 'support'],
                ],
            ]),
        ]);

        $this->assertCount(3, $blocks);
        $this->assertSame('input', $blocks[0]['type']);
        $this->assertSame('email', $blocks[0]['data']['input_type']);
        $this->assertSame('select', $blocks[1]['type']);
        $this->assertSame('button', $blocks[2]['type']);
        $this->assertSame('Submit', $blocks[2]['data']['label']);
    }

    public function test_it_derives_submission_fields_from_builder_blocks()
    {
        $form = new Form([
            'blocks' => [
                [
                    'id' => 'field-1',
                    'type' => 'input',
                    'data' => [
                        'input_type' => 'email',
                        'label' => 'Email',
                        'key' => 'email',
                        'placeholder' => 'you@example.com',
                        'required' => true,
                    ],
                    'order' => 0,
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'data' => ['label' => 'Send'],
                    'order' => 1,
                ],
            ],
        ]);

        $fields = FormBuilder::submissionFields($form);

        $this->assertCount(1, $fields);
        $this->assertSame('email', $fields[0]['type']);
        $this->assertSame('email', $fields[0]['key']);
        $this->assertTrue($fields[0]['required']);
    }

    public function test_it_derives_submission_fields_from_columns_blocks()
    {
        $form = new Form([
            'blocks' => [
                [
                    'id' => 'columns-1',
                    'type' => 'columns',
                    'data' => [
                        'count' => '2',
                        'gap' => 'md',
                        'col_0' => [
                            [
                                'id' => 'field-1',
                                'type' => 'input',
                                'data' => [
                                    'input_type' => 'text',
                                    'label' => 'First Name',
                                    'key' => 'first_name',
                                ],
                                'order' => 0,
                            ],
                        ],
                        'col_1' => [
                            [
                                'id' => 'field-2',
                                'type' => 'input',
                                'data' => [
                                    'input_type' => 'email',
                                    'label' => 'Email',
                                    'key' => 'email',
                                ],
                                'order' => 0,
                            ],
                            [
                                'id' => 'button-1',
                                'type' => 'button',
                                'data' => ['label' => 'Send'],
                                'order' => 1,
                            ],
                        ],
                    ],
                    'order' => 0,
                ],
            ],
        ]);

        $fields = FormBuilder::submissionFields($form);

        $this->assertCount(2, $fields);
        $this->assertSame('first_name', $fields[0]['key']);
        $this->assertSame('email', $fields[1]['key']);
    }

    public function test_it_resolves_action_config_from_legacy_columns()
    {
        $form = new Form([
            'success_message' => 'All set!',
            'redirect_url' => '/thanks',
            'notify_email' => 'team@example.com',
        ]);

        $config = FormBuilder::resolvedActionConfig($form);

        $this->assertSame('All set!', $config['success_message']);
        $this->assertSame('/thanks', $config['redirect_url']);
        $this->assertSame('team@example.com', $config['notify_email']);
    }

    public function test_it_validates_required_form_blocks()
    {
        $fieldOnlyErrors = FormBuilder::validateBlocks([
            [
                'id' => 'field-1',
                'type' => 'input',
                'data' => [
                    'input_type' => 'text',
                    'label' => 'Name',
                    'key' => 'name',
                ],
                'order' => 0,
            ],
        ]);

        $buttonOnlyErrors = FormBuilder::validateBlocks([
            [
                'id' => 'button-1',
                'type' => 'button',
                'data' => ['label' => 'Send'],
                'order' => 0,
            ],
        ]);

        $this->assertContains('Add a button block so the form can be submitted.', $fieldOnlyErrors);
        $this->assertContains('Add at least one field block to the form.', $buttonOnlyErrors);
    }

    public function test_it_accepts_columns_when_they_contain_fields_and_a_button()
    {
        $errors = FormBuilder::validateBlocks([
            [
                'id' => 'columns-1',
                'type' => 'columns',
                'data' => [
                    'count' => '2',
                    'gap' => 'md',
                    'col_0' => [
                        [
                            'id' => 'field-1',
                            'type' => 'input',
                            'data' => [
                                'input_type' => 'text',
                                'label' => 'Name',
                                'key' => 'name',
                            ],
                            'order' => 0,
                        ],
                    ],
                    'col_1' => [
                        [
                            'id' => 'button-1',
                            'type' => 'button',
                            'data' => ['label' => 'Send'],
                            'order' => 0,
                        ],
                    ],
                ],
                'order' => 0,
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_it_exposes_the_core_submission_action()
    {
        $actions = FormBuilder::publicActions();

        $this->assertSame(FormBuilder::DEFAULT_ACTION, $actions[0]['key']);
        $this->assertSame('core', $actions[0]['source']);
        $this->assertArrayHasKey('fields', $actions[0]);
        $this->assertArrayNotHasKey('handler', $actions[0]);
    }
}
