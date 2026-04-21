<?php

namespace Tests\Feature;

use App\Mail\KavloTemplateMail;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailTemplateFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_email_templates(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->post('/admin/email-templates', [
            'name' => 'Form notification',
            'slug' => 'form-notification',
            'description' => 'Used for inbound contact notifications.',
            'context_key' => 'core.form-notification',
            'subject' => 'New submission for {{ form.name }}',
            'blocks' => [
                [
                    'id' => 'heading-1',
                    'type' => 'heading',
                    'data' => [
                        'text' => 'New submission',
                        'level' => 'h2',
                        'align' => 'left',
                    ],
                    'order' => 0,
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'data' => [
                        'content' => 'Email: {{ submission.email }}',
                    ],
                    'order' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('email_templates', [
            'slug' => 'form-notification',
            'context_key' => 'core.form-notification',
        ]);
    }

    public function test_admin_cannot_create_email_template_with_invalid_nested_blocks(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)->post('/admin/email-templates', [
            'name' => 'Form notification',
            'slug' => 'form-notification',
            'description' => 'Used for inbound contact notifications.',
            'context_key' => 'core.form-notification',
            'subject' => 'New submission for {{ form.name }}',
            'blocks' => [
                [
                    'id' => 'columns-1',
                    'type' => 'columns',
                    'data' => [
                        'count' => '2',
                        'col_0' => [
                            [
                                'type' => 'text',
                                'data' => ['content' => 'Broken'],
                            ],
                        ],
                    ],
                    'order' => 0,
                ],
            ],
        ])->assertSessionHasErrors('blocks');
    }

    public function test_test_email_uses_selected_email_template(): void
    {
        Mail::fake();

        $user = $this->adminUser();
        $template = EmailTemplate::create([
            'name' => 'Test email',
            'slug' => 'test-email',
            'description' => null,
            'context_key' => 'core.test-email',
            'subject' => 'Preview from {{ site.name }}',
            'blocks' => [
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'data' => [
                        'content' => 'Hello {{ recipient.email }}',
                    ],
                    'order' => 0,
                ],
            ],
        ]);

        Setting::setMany([
            'site_name' => 'CMS Demo',
            'admin_email' => 'admin@example.com',
            'mail_test_template_id' => $template->id,
        ]);

        $this->actingAsConfirmed($user)
            ->post('/admin/settings/email/test')
            ->assertRedirect();

        Mail::assertQueued(KavloTemplateMail::class, function (KavloTemplateMail $mail) {
            return ($mail->rendered['subject'] ?? null) === 'Preview from CMS Demo'
                && str_contains((string) ($mail->rendered['html'] ?? ''), 'Hello admin@example.com');
        });
    }

    public function test_form_notification_can_use_email_template(): void
    {
        Mail::fake();

        $template = EmailTemplate::create([
            'name' => 'Contact notification',
            'slug' => 'contact-notification',
            'description' => null,
            'context_key' => 'core.form-notification',
            'subject' => 'New {{ form.name }} submission',
            'blocks' => [
                [
                    'id' => 'heading-1',
                    'type' => 'heading',
                    'data' => [
                        'text' => '{{ form.name }}',
                        'level' => 'h2',
                        'align' => 'left',
                    ],
                    'order' => 0,
                ],
                [
                    'id' => 'text-1',
                    'type' => 'text',
                    'data' => [
                        'content' => 'From: {{ submission.email }}',
                    ],
                    'order' => 1,
                ],
            ],
        ]);

        $form = Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'submission_action' => 'core.store-submission',
            'action_config' => [
                'success_message' => 'Thanks!',
                'notify_email' => 'team@example.com',
                'email_template_id' => $template->id,
            ],
            'blocks' => [
                [
                    'id' => 'field-1',
                    'type' => 'input',
                    'data' => [
                        'input_type' => 'email',
                        'label' => 'Email',
                        'key' => 'email',
                        'required' => true,
                    ],
                    'order' => 0,
                ],
                [
                    'id' => 'button-1',
                    'type' => 'button',
                    'data' => [
                        'label' => 'Send',
                    ],
                    'order' => 1,
                ],
            ],
        ]);

        $response = $this->postJson("/forms/{$form->slug}/submit", [
            'email' => 'jane@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseCount('form_submissions', 1);

        Mail::assertQueued(KavloTemplateMail::class, function (KavloTemplateMail $mail) {
            return ($mail->rendered['subject'] ?? null) === 'New Contact submission'
                && str_contains((string) ($mail->rendered['html'] ?? ''), 'From: jane@example.com');
        });
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
