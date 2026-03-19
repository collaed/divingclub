<?php

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $providers = ['google', 'microsoft', 'facebook', 'x', 'amazon'];

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, $this->providers), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, $this->providers), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', __('Authentication failed. Please try again.'));
        }

        // 1. Existing social link — just update tokens and log in
        $social = UserSocialAccount::where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($social) {
            $social->update(['token' => $socialUser->token, 'refresh_token' => $socialUser->refreshToken]);
            Auth::login($social->user, true);

            return redirect()->intended(route('profile.show'));
        }

        // 2. Email matches existing account — require confirmation (anti-takeover)
        $emailRecord = UserEmail::where('email', $socialUser->getEmail())->first();

        if ($emailRecord) {
            session([
                'pending_social_link' => [
                    'provider' => $provider,
                    'provider_user_id' => $socialUser->getId(),
                    'email' => $socialUser->getEmail(),
                    'token' => encrypt($socialUser->token),
                    'refresh_token' => encrypt($socialUser->refreshToken ?? ''),
                    'user_id' => $emailRecord->user_id,
                ],
            ]);

            return redirect()->route('login')->with('warning',
                __('A :provider account with this email exists. Please log in with your password to confirm the link.', ['provider' => ucfirst($provider)])
            );
        }

        // 3. New user — create account
        $user = DB::transaction(function () use ($provider, $socialUser) {
            $memberRole = Role::where('slug', 'member')->first();

            $user = User::create([
                'primary_email' => $socialUser->getEmail(),
                'role_id' => $memberRole->id,
                'email_verified_at' => now(),
            ]);

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $socialUser->getEmail(),
                'is_primary' => true,
                'is_verified' => true,
                'label' => $provider,
            ]);

            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);

            $name = $socialUser->getName() ?? '';
            $parts = explode(' ', $name, 2);
            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);

            return $user;
        });

        Auth::login($user, true);

        return redirect()->route('profile.show')->with('success', __('Welcome! Please complete your profile.'));
    }

    /** After password login, confirm and apply a pending social link. */
    public function confirmLink(Request $request)
    {
        $pending = session('pending_social_link');

        if (! $pending || $pending['user_id'] !== auth()->id()) {
            return redirect()->route('profile.show');
        }

        DB::transaction(function () use ($pending) {
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
    public function dismissLink()
    {
        session()->forget('pending_social_link');

        return redirect()->route('profile.show');
    }
}
