<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function create(): RedirectResponse|View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        // "email" is really a login identifier: primary email, a verified
        // secondary email, or a legacy username (may contain spaces).
        $identifier = trim((string) $request->input('email'));
        $throttleKey = Str::transliterate(Str::lower($identifier).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        if (Auth::attempt(['email' => $identifier, 'password' => $request->password], $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            // redirect()->intended() also clears the stored URL. Guard against a
            // poisoned target (the landing page's /photos/browse prefetch can be
            // recorded as "intended" for a guest) landing a fresh login on a
            // non-page endpoint.
            $target = redirect()->intended(route('profile.show'));
            if (str_contains($target->getTargetUrl(), '/photos/browse')) {
                return redirect()->route('profile.show');
            }

            return $target;
        }

        RateLimiter::hit($throttleKey, 600); // 10 minute decay

        return back()->withErrors(['email' => __('Invalid credentials.')])->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
