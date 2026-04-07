<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileEmailController extends Controller
{
    public function add(Request $request)
    {
        $user = auth()->user();

        if ($user->emails()->count() >= 5) {
            return back()->with('error', __('Maximum of 5 email addresses allowed.'));
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:user_emails,email',
            'label' => 'nullable|string|max:50',
        ]);

        UserEmail::create([
            'user_id' => $user->id,
            'email' => $validated['email'],
            'is_primary' => false,
            'is_verified' => false,
            'label' => $validated['label'],
            'verification_token' => Str::random(64),
            'verification_sent_at' => now(),
        ]);

        return back()->with('success', __('Email added. Please verify it.'));
    }

    public function setPrimary(UserEmail $email)
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->can('manage members')) {
            abort(403);
        }
        if (! $email->is_verified) {
            return back()->with('error', __('Only verified emails can be set as primary.'));
        }

        DB::transaction(function () use ($email) {
            UserEmail::where('user_id', $email->user_id)->update(['is_primary' => false]);
            $email->update(['is_primary' => true]);
            User::where('id', $email->user_id)->update(['primary_email' => $email->email]);
        });

        return back()->with('success', __('Primary email updated.'));
    }

    public function delete(UserEmail $email)
    {
        $user = auth()->user();
        if ($email->user_id !== $user->id && ! $user->can('manage members')) {
            abort(403);
        }
        if ($email->is_primary) {
            return back()->with('error', __('Cannot delete primary email. Set another as primary first.'));
        }

        $email->delete();

        return back()->with('success', __('Email removed.'));
    }
}
