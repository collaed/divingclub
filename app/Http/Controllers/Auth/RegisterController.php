<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\MemberDetail;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\PushNotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegisterController extends Controller
{
    public function create(): RedirectResponse|View
    {
        return view('auth.register');
    }

    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Bot check: form submitted too fast
        if (time() - (int) $request->_ts < 3) {
            return back()->withErrors(['email' => __('Please try again.')])->withInput();
        }

        $user = DB::transaction(function () use ($validated) {
            $roleTable = Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
            $memberRoleId = DB::table($roleTable)->where('slug', 'member')->value('id')
                ?? DB::table($roleTable)->where('name', 'member')->value('id')
                ?? 2;

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
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'sex' => $validated['sex'] ?? null,
                'phone_mobile' => $validated['phone_mobile'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'] ?? 'Luxembourg',
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
