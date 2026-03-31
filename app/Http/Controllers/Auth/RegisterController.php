<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\MemberDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\PushNotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(RegisterUserRequest $request)
    {
        $validated = $request->validated();

        // Bot check: form submitted too fast
        if (time() - (int) $request->_ts < 3) {
            return back()->withErrors(['email' => __('Please try again.')])->withInput();
        }

        $user = DB::transaction(function () use ($validated) {
            $memberRoleId = Role::where('slug', 'member')->value('id');

            $user = User::create([
                'primary_email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => $memberRoleId,
            ]);

            $user->assignRole('member');

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

        app(PushNotificationService::class)->sendToBureau(
            __('New Member'),
            $validated['first_name'].' '.$validated['last_name'],
            '/admin/members'
        );

        return redirect()->route('verification.notice');
    }
}
