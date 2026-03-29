<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileAvatarController extends Controller
{
    public function upload(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = $request->file('avatar')->store('avatars/'.$target->id, 'public');

        $old = $target->detail?->avatar_path;
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $target->detail()->updateOrCreate(['user_id' => $target->id], ['avatar_path' => $path]);

        return back()->with('success', __('Photo updated.'));
    }

    public function delete(?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        if ($target->detail?->avatar_path) {
            Storage::disk('public')->delete($target->detail->avatar_path);
            $target->detail->update(['avatar_path' => null]);
        }

        return back()->with('success', __('Photo removed.'));
    }
}
