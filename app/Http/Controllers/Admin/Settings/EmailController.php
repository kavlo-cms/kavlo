<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Mail\KavloTemplateMail;
use App\Models\Setting;
use App\Services\EmailTemplateBuilder;
use App\Services\EmailTemplateRenderer;
use App\Services\KavloMailDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function __construct(
        protected EmailTemplateBuilder $templates,
        protected EmailTemplateRenderer $renderer,
        protected KavloMailDelivery $mailDelivery,
    ) {}

    public function index(): Response
    {
        $settings = Setting::allCached();

        return Inertia::render('Settings/Email', [
            'settings' => [
                'mail_mailer' => $settings['mail_mailer'] ?? '',
                'mail_host' => $settings['mail_host'] ?? '',
                'mail_port' => $settings['mail_port'] ?? '',
                'mail_username' => $settings['mail_username'] ?? '',
                'mail_encryption' => $settings['mail_encryption'] ?? '',
                'mail_from_address' => $settings['mail_from_address'] ?? '',
                'mail_from_name' => $settings['mail_from_name'] ?? '',
                'mail_test_template_id' => $settings['mail_test_template_id'] ?? '',
            ],
            'hasPassword' => ! empty($settings['mail_password']),
            'availableTemplates' => $this->templates->templateOptionsFor(EmailTemplateBuilder::TEST_EMAIL_CONTEXT),
            'delivery' => $this->mailDelivery->status(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_mailer' => ['nullable', 'string', 'max:50'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'min:1'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,none,'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'mail_test_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
        ]);

        // Never overwrite the stored password with the masked placeholder
        if (($validated['mail_password'] ?? '') === '••••••••') {
            unset($validated['mail_password']);
        }

        // Skip saving empty password (user cleared the field without typing a new one)
        if (array_key_exists('mail_password', $validated) && $validated['mail_password'] === '') {
            unset($validated['mail_password']);
        }

        Setting::setMany($validated);

        return back()->with('success', 'Email settings saved.');
    }

    public function testSend(Request $request): RedirectResponse
    {
        try {
            $siteName = Setting::get('site_name', config('app.name'));
            $recipient = Setting::get('admin_email', config('mail.from.address'));
            $template = $this->templates->findTemplateForContext(
                Setting::get('mail_test_template_id'),
                EmailTemplateBuilder::TEST_EMAIL_CONTEXT,
            );

            if ($template) {
                $this->mailDelivery->queue(
                    (string) $recipient,
                    new KavloTemplateMail($this->renderer->render(
                        $template,
                        $this->templates->testEmailData((string) $recipient),
                    )),
                );
            } else {
                $this->mailDelivery->queuePlainText(
                    (string) $recipient,
                    "Test email from {$siteName}",
                    "This is a test email sent from {$siteName}.",
                );
            }

            return back()->with('success', $this->mailDelivery->isAsync()
                ? 'Test email queued for delivery.'
                : 'Test email sent.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
