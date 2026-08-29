<?php

declare(strict_types=1);

/**
 * Social (OAuth) authentication: redirect, callback, and pending link confirmation.
 *
 * When a social login email matches an existing account, the link is NOT
 * auto-applied. Instead, a pending link is stored in the session and the
 * existing account owner must confirm it after logging in. This prevents
 * account takeover via email spoofing from untrusted OAuth providers.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserSocialAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    protected array $providers = ['google', 'microsoft', 'facebook', 'x'];

    public function redirect(string $provider): Response
    {
        abort_unless(in_array($provider, $this->providers), 404);

        // Store the originating domain so we can redirect back after OAuth callback
        $referer = request()->headers->get('referer', '');
        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);
        if ($scheme && $host) {
            session(['oauth_origin' => $scheme.'://'.$host]);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse|View
    {
        abort_unless(in_array($provider, $this->providers), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect($this->originUrl('/login'))->with('error', __('Authentication failed. Please try again.'));
        }

        // 1. Existing social link — just update tokens and log in
        $social = UserSocialAccount::where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($social) {
            $social->update(['token' => $socialUser->token ?? null, 'refresh_token' => $socialUser->refreshToken ?? null]);
            Auth::login($social->user, true);

            return redirect($this->originUrl('/profile'));
        }

        // 2. Email matches existing account — require confirmation (anti-takeover)
        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect($this->originUrl('/login'))->with('error', __('No email returned by :provider. Please use another login method.', ['provider' => ucfirst($provider)]));
        }

        $emailRecord = UserEmail::where('email', $email)->first();
        $existingUser = $emailRecord?->user ?? User::where('primary_email', $email)->first();

        if ($existingUser) {
            session([
                'pending_social_link' => [
                    'provider' => $provider,
                    'provider_user_id' => $socialUser->getId(),
                    'email' => $email,
                    'token' => encrypt($socialUser->token ?? null),
                    'refresh_token' => encrypt($socialUser->refreshToken ?? null ?? ''),
                    'user_id' => $existingUser->id,
                ],
            ]);

            return redirect($this->originUrl('/login'))->with('warning',
                __('A :provider account with this email exists. Please log in with your password to confirm the link.', ['provider' => ucfirst($provider)])
            );
        }

        // 3. No email match — ask user if they have an existing account or are new
        session([
            'pending_social_new' => [
                'provider' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'email' => $email,
                'name' => $socialUser->getName() ?? '',
                'token' => encrypt($socialUser->token ?? null),
                'refresh_token' => encrypt($socialUser->refreshToken ?? null ?? ''),
            ],
        ]);

        return redirect($this->originUrl('/auth/social/choose'));
    }

    /**
     * Build an absolute URL on the originating domain (stored in session during redirect).
     * Falls back to APP_URL if no origin was stored.
     */
    private function originUrl(string $path): string
    {
        $origin = session()->pull('oauth_origin', config('app.url'));

        return rtrim($origin, '/').$path;
    }

    /** Show the "link existing or register new" choice page. */
    public function choose(): RedirectResponse|View
    {
        $pending = session('pending_social_new');
        if (! $pending) {
            return redirect()->route('login');
        }

        return view('auth.social-choose', [
            'provider' => ucfirst($pending['provider']),
            'email' => $pending['email'],
            'name' => $pending['name'],
        ]);
    }

    /** User chose "I have an existing account" — log in to link. */
    public function linkExisting(Request $request): RedirectResponse
    {
        $pending = session('pending_social_new');
        if (! $pending) {
            return redirect()->route('login');
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($request->only('email', 'password'), true)) {
            return back()->withErrors(['email' => __('Invalid credentials.')])->withInput();
        }

        // Link the social account to the authenticated user
        DB::transaction(function () use ($pending): void {
            UserSocialAccount::create([
                'user_id' => auth()->id(),
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
                'email' => $pending['email'],
                'token' => decrypt($pending['token']),
                'refresh_token' => decrypt($pending['refresh_token']),
            ]);
        });

        session()->forget('pending_social_new');

        return redirect()->route('profile.show')->with('success',
            __(':provider account linked to your existing account.', ['provider' => ucfirst($pending['provider'])])
        );
    }

    /** User chose "I'm new" — create account. */
    public function createNew(): RedirectResponse
    {
        $pending = session('pending_social_new');
        if (! $pending) {
            return redirect()->route('login');
        }

        $user = DB::transaction(function () use ($pending) {
            $memberRole = Role::where('slug', 'member')->first();

            $user = User::create([
                'primary_email' => $pending['email'],
                'role_id' => $memberRole?->id,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('member');

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $pending['email'],
                'is_primary' => true,
                'is_verified' => true,
                'label' => $pending['provider'],
            ]);

            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
                'email' => $pending['email'],
                'token' => decrypt($pending['token']),
                'refresh_token' => decrypt($pending['refresh_token']),
            ]);

            $parts = explode(' ', $pending['name'], 2);
            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);

            return $user;
        });

        session()->forget('pending_social_new');
        Auth::login($user, true);

        return redirect()->route('profile.show')->with('success', __('Welcome! Please complete your profile.'));
    }

    /** After password login, confirm and apply a pending social link. */
    public function confirmLink(Request $request): RedirectResponse
    {
        $pending = session('pending_social_link');

        if (! $pending || $pending['user_id'] !== auth()->id()) {
            return redirect()->route('profile.show');
        }

        DB::transaction(function () use ($pending): void {
            UserSocialAccount::create([
                'user_id' => $pending['user_id'],
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
                'email' => $pending['email'],
                'token' => decrypt($pending['token']),
                'refresh_token' => decrypt($pending['refresh_token']),
            ]);

            AuditLog::create([
                'user_id' => $pending['user_id'],
                'action' => 'sso_linked',
                'model_type' => UserSocialAccount::class,
                'model_id' => $pending['user_id'],
                'new_values' => ['provider' => $pending['provider'], 'email' => $pending['email']],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        session()->forget('pending_social_link');

        return redirect()->route('profile.show')->with('success',
            __(':provider account linked successfully.', ['provider' => ucfirst($pending['provider'])])
        );
    }

    /** Dismiss a pending social link without applying it. */
    public function dismissLink(): RedirectResponse
    {
        session()->forget('pending_social_link');

        return redirect()->route('profile.show');
    }
}
