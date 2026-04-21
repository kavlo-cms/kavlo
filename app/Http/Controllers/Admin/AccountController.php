<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\ApiKeyManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        protected ApiKeyManager $apiKeys,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Index', [
            'user' => $user->only('id', 'name', 'email', 'email_verified_at', 'created_at'),
            'apiKeys' => $user->apiKeys()->get()->map(fn (ApiKey $apiKey) => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
                'abilities' => $apiKey->abilities ?? [],
                'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                'last_used_ip' => $apiKey->last_used_ip,
                'expires_at' => $apiKey->expires_at?->toIso8601String(),
                'revoked_at' => $apiKey->revoked_at?->toIso8601String(),
                'created_at' => $apiKey->created_at?->toIso8601String(),
                'status' => $apiKey->revoked_at ? 'revoked' : ($apiKey->isExpired() ? 'expired' : 'active'),
            ]),
            'apiKeyAbilities' => $this->apiKeys->abilities(),
            'generatedApiKey' => $request->session()->get('generated_api_key'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }

    public function storeApiKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in($this->apiKeys->abilityKeys())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        [$apiKey, $plainTextToken] = $this->apiKeys->issue(
            $request->user(),
            $validated['name'],
            $validated['abilities'],
            isset($validated['expires_at']) ? CarbonImmutable::parse($validated['expires_at']) : null,
        );

        return back()
            ->with('success', 'API key created.')
            ->with('generated_api_key', [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'token' => $plainTextToken,
            ]);
    }

    public function rotateApiKey(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 404);
        abort_if($apiKey->revoked_at !== null, 422, 'This API key has already been revoked.');

        [$replacement, $plainTextToken] = $this->apiKeys->rotate($apiKey);

        return back()
            ->with('success', 'API key rotated.')
            ->with('generated_api_key', [
                'id' => $replacement->id,
                'name' => $replacement->name,
                'token' => $plainTextToken,
            ]);
    }

    public function destroyApiKey(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 404);

        if ($apiKey->isActive()) {
            $this->apiKeys->revoke($apiKey);
        }

        return back()->with('success', 'API key revoked.');
    }
}
