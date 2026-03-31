<?php

/**
 * EU Login (ECAS) authentication via CAS protocol.
 *
 * EU Login is the European Commission's authentication service.
 * In simple CAS mode, no client registration or API keys are needed.
 * The user is redirected to EU Login, authenticates, and a ticket
 * is returned and validated server-side.
 *
 * @author ClubCEP.eu
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserSocialAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EuLoginController extends Controller
{
    private function initCas(): void
    {
        if (! \phpCAS::isInitialized()) {
            \phpCAS::client(
                CAS_VERSION_3_0,
                'ecas.ec.europa.eu',
                443,
                '/cas',
                config('app.url')
            );
            // EU Login requires laxValidate for external (non-Commission) users
            \phpCAS::setServerServiceValidateURL('https://ecas.ec.europa.eu/cas/laxValidate');
            \phpCAS::setNoCasServerValidation();
        }
    }

    public function redirect()
    {
        $this->initCas();
        \phpCAS::setFixedServiceURL(route('auth.eulogin.callback'));
        \phpCAS::forceAuthentication();
    }

    public function callback()
    {
        $this->initCas();
        \phpCAS::setFixedServiceURL(route('auth.eulogin.callback'));
        \phpCAS::forceAuthentication();

        $username = \phpCAS::getUser();
        $attributes = \phpCAS::getAttributes();

        $email = $attributes['email'] ?? $attributes['mail'] ?? ($username.'@ec.europa.eu');
        $firstName = $attributes['givenName'] ?? $attributes['firstname'] ?? '';
        $lastName = $attributes['sn'] ?? $attributes['lastname'] ?? '';

        // 1. Existing social link
        $social = UserSocialAccount::where('provider', 'eulogin')
            ->where('provider_user_id', $username)
            ->first();

        if ($social) {
            Auth::login($social->user, true);

            return redirect()->intended(route('profile.show'));
        }

        // 2. Email matches existing account — auto-link (EU Login is trusted)
        $user = User::where('primary_email', $email)->first();

        if ($user) {
            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'eulogin',
                'provider_user_id' => $username,
                'email' => $email,
            ]);
            Auth::login($user, true);

            return redirect()->intended(route('profile.show'));
        }

        // 3. New user
        $user = DB::transaction(function () use ($username, $email, $firstName, $lastName) {
            $user = User::create([
                'primary_email' => $email,
                'role_id' => Role::where('slug', 'member')->value('id'),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('member');

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $email,
                'is_primary' => true,
                'is_verified' => true,
                'label' => 'eulogin',
            ]);

            UserSocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'eulogin',
                'provider_user_id' => $username,
                'email' => $email,
            ]);

            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            return $user;
        });

        Auth::login($user, true);

        return redirect()->route('profile.show')->with('success', __('Welcome! Please complete your profile.'));
    }
}
