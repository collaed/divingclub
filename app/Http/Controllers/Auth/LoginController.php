<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check lockout
        $recentFails = DB::table('failed_login_attempts')
            ->where('email', $request->email)
            ->where('attempted_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentFails >= 5) {
            return back()->withErrors(['email' => __('Account locked. Try again in 15 minutes.')]);
        }

        if (Auth::attempt(['primary_email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Clear failed attempts
            DB::table('failed_login_attempts')->where('email', $request->email)->delete();

            return redirect()->intended(route('profile.show'));
        }

        // Record failed attempt
        DB::table('failed_login_attempts')->insert([
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'attempted_at' => now(),
        ]);

        return back()->withErrors(['email' => __('Invalid credentials.')]);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
