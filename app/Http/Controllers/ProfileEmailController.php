<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ThemeSetting;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProfileEmailController extends Controller
{
    public function add(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user->emails()->count() >= 5) {
            return back()->with('error', __('Maximum of 5 email addresses allowed.'))->withInput(['tab' => 'info']);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:user_emails,email',
            'label' => 'nullable|string|max:50',
        ]);

        $email = UserEmail::create([
            'user_id' => $user->id,
            'email' => $validated['email'],
            'is_primary' => false,
            'is_verified' => false,
            'label' => $validated['label'] ?? null,
            'verification_token' => Str::random(64),
            'verification_sent_at' => now(),
        ]);

        $this->sendVerificationMail($email);

        return back()->with('success', __('Email added. A verification link was sent to it — click it before you can set it as primary.'))->withInput(['tab' => 'info']);
    }

    /** Re-send the verification link, e.g. if the first one was lost or expired the user's patience. */
    public function resend(UserEmail $email): RedirectResponse
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->can('manage members')) {
            abort(403);
        }
        if ($email->is_verified) {
            return back()->with('error', __('This email is already verified.'))->withInput(['tab' => 'info']);
        }

        $email->update(['verification_token' => Str::random(64), 'verification_sent_at' => now()]);
        $this->sendVerificationMail($email);

        return back()->with('success', __('Verification link re-sent.'))->withInput(['tab' => 'info']);
    }

    /** Confirm a secondary email via its mailed link — no login required, the token is the proof. */
    public function verify(string $token): RedirectResponse
    {
        $email = UserEmail::where('verification_token', $token)->first();
        if (! $email) {
            return redirect()->route('login')->with('error', __('This verification link is invalid or has already been used.'));
        }

        $email->update(['is_verified' => true, 'verification_token' => null]);

        return auth()->check()
            ? redirect()->route('profile.show', ['tab' => 'info'])->with('success', __(':email is now verified — you can set it as primary.', ['email' => $email->email]))
            : redirect()->route('login')->with('success', __(':email is now verified. Log in to set it as your primary address.', ['email' => $email->email]));
    }

    private function sendVerificationMail(UserEmail $email): void
    {
        $club = ThemeSetting::get('club_full_name', config('app.name'));
        $link = route('profile.email.verify', $email->verification_token);

        Mail::raw(
            __("Confirm this email address for your :club account by opening this link:\n:link", ['club' => $club, 'link' => $link]),
            fn ($mail) => $mail->to($email->email)->subject(__(':club — confirm your email address', ['club' => $club]))
        );
    }

    public function setPrimary(UserEmail $email): RedirectResponse
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->can('manage members')) {
            abort(403);
        }
        if (! $email->is_verified) {
            return back()->with('error', __('Only verified emails can be set as primary.'))->withInput(['tab' => 'info']);
        }

        DB::transaction(function () use ($email): void {
            UserEmail::where('user_id', $email->user_id)->update(['is_primary' => false]);
            $email->update(['is_primary' => true]);
            User::where('id', $email->user_id)->update(['primary_email' => $email->email]);
        });

        return back()->with('success', __('Primary email updated.'))->withInput(['tab' => 'info']);
    }

    public function delete(UserEmail $email): RedirectResponse
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->can('manage members')) {
            abort(403);
        }
        if ($email->is_primary) {
            return back()->with('error', __('Cannot delete primary email. Set another as primary first.'))->withInput(['tab' => 'info']);
        }

        $email->delete();

        return back()->with('success', __('Email removed.'))->withInput(['tab' => 'info']);
    }

    public function toggleReceiveMail(UserEmail $email): RedirectResponse
    {
        abort_unless(auth()->id() === $email->user_id, 403);

        // Prevent disabling all — at least one must receive mail
        if ($email->receive_mail) {
            $othersReceiving = $email->user->emails()->where('id', '!=', $email->id)->where('receive_mail', true)->count();
            if ($othersReceiving === 0) {
                return back()->with('error', __('At least one email address must receive club communications.'))->withInput(['tab' => 'info']);
            }
        }

        $email->update(['receive_mail' => ! $email->receive_mail]);

        return back()->with('success', $email->receive_mail
            ? __(':email will receive club emails.', ['email' => $email->email])
            : __(':email will NOT receive club emails (login only).', ['email' => $email->email])
        )->withInput(['tab' => 'info']);
    }
}
