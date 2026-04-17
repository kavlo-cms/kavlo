<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::allCached();

        return Inertia::render('Settings/Email', [
            'settings' => [
                'mail_mailer'       => $settings['mail_mailer']       ?? '',
                'mail_host'         => $settings['mail_host']         ?? '',
                'mail_port'         => $settings['mail_port']         ?? '',
                'mail_username'     => $settings['mail_username']     ?? '',
                'mail_encryption'   => $settings['mail_encryption']   ?? '',
                'mail_from_address' => $settings['mail_from_address'] ?? '',
                'mail_from_name'    => $settings['mail_from_name']    ?? '',
            ],
            'hasPassword' => !empty($settings['mail_password']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_mailer'       => ['nullable', 'string', 'max:50'],
            'mail_host'         => ['nullable', 'string', 'max:255'],
            'mail_port'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username'     => ['nullable', 'string', 'max:255'],
            'mail_password'     => ['nullable', 'string', 'min:1'],
            'mail_encryption'   => ['nullable', 'string', 'in:tls,ssl,none,'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name'    => ['nullable', 'string', 'max:255'],
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

    public function testSend(Request $request): JsonResponse
    {
        try {
            $siteName  = Setting::get('site_name', config('app.name'));
            $recipient = Setting::get('admin_email', config('app.url'));

            Mail::raw("This is a test email sent from {$siteName}.", function ($message) use ($siteName, $recipient) {
                $message->to($recipient)
                        ->subject("Test email from {$siteName}");
            });

            return response()->json(['success' => true, 'message' => 'Test email sent.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
