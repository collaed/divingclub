<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserSocialAccount;
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

        $socialUser = Socialite::driver($provider)->user();

        $user = DB::transaction(function () use ($provider, $socialUser) {
            // 1. Check existing social account link
            $social = UserSocialAccount::where('provider', $provider)
                ->where('provider_user_id', $socialUser->getId())
                ->first();

            if ($social) {
                $social->update(['token' => $socialUser->token, 'refresh_token' => $socialUser->refreshToken]);
                return $social->user;
            }

            // 2. Check user_emails for matching email
            $emailRecord = UserEmail::where('email', $socialUser->getEmail())->first();

            if ($emailRecord) {
                UserSocialAccount::create([
                    'user_id' => $emailRecord->user_id,
                    'provider' => $provider,
                    'provider_user_id' => $socialUser->getId(),
                    'email' => $socialUser->getEmail(),
                    'token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                ]);

                AuditLog::create([
                    'user_id' => $emailRecord->user_id,
                    'action' => 'sso_linked',
                    'model_type' => UserSocialAccount::class,
                    'model_id' => $emailRecord->user_id,
                    'new_values' => ['provider' => $provider, 'email' => $socialUser->getEmail()],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                return $emailRecord->user;
            }

            // 3. Create new user
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

        // Redirect new users (no detail filled) to profile completion
        if (!$user->detail || !$user->detail->last_name) {
            return redirect()->route('profile.show')->with('success', __('Welcome! Please complete your profile.'));
        }

        return redirect()->intended(route('profile.show'));
    }
}
