<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:user_emails,email|unique:users,primary_email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'website' => 'size:0',       // honeypot — must be empty
            '_ts' => 'required|integer',  // timestamp — must be >3s ago
        ]);

        // Bot check: form submitted too fast
        if (time() - (int) $request->_ts < 3) {
            return back()->withErrors(['email' => __('Please try again.')])->withInput();
        }

        $user = DB::transaction(function () use ($validated) {
            $memberRole = Role::where('slug', 'member')->first();

            $user = User::create([
                'primary_email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => $memberRole->id,
            ]);

            UserEmail::create([
                'user_id' => $user->id,
                'email' => $validated['email'],
                'is_primary' => true,
                'is_verified' => false,
            ]);

            MemberDetail::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
