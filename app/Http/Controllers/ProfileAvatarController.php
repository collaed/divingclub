<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProfileAvatarController extends Controller
{
    public function upload(Request $request, ?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->can('manage members')) {
            abort(403);
        }

        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);

        // Resize to max 400x400 and save as JPEG
        $filename = 'avatar_'.$target->id.'.jpg';
        $path = 'avatars/'.$filename;

        Image::decode($request->file('avatar')->getContent())
            ->scaleDown(400, 400)
            ->toJpeg(85)
            ->save(Storage::disk('public')->path($path));

        $old = $target->detail?->avatar_path;
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        $target->detail()->updateOrCreate(['user_id' => $target->id], ['avatar_path' => $path]);

        return back()->with('success', __('Photo updated.'));
    }

    public function delete(?User $user = null): RedirectResponse
    {
        $viewer = auth()->user();
        $target = $user ?? $viewer;
        if ($target->id !== $viewer->id && ! $viewer->can('manage members')) {
            abort(403);
        }

        if ($target->detail?->avatar_path) {
            Storage::disk('public')->delete($target->detail->avatar_path);
            $target->detail->update(['avatar_path' => null]);
        }

        return back()->with('success', __('Photo removed.'));
    }
}
