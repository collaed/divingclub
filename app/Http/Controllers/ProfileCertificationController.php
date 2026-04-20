<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileCertificationController extends Controller
{
    public function add(Request $request): RedirectResponse
    {
        $request->validate(['certification_level_id' => 'required|exists:certification_levels,id', 'obtained_date' => 'nullable|date']);
        $user = auth()->user();
        $user->certificationLevels()->syncWithoutDetaching([
            $request->certification_level_id => ['obtained_date' => $request->obtained_date, 'display_priority' => 0],
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification added.'));
    }

    public function update(Request $request, int $certLevel): RedirectResponse
    {
        $request->validate(['obtained_date' => 'nullable|date']);
        auth()->user()->certificationLevels()->updateExistingPivot($certLevel, [
            'obtained_date' => $request->obtained_date,
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification updated.'));
    }

    public function setPrimary(int $certLevel): RedirectResponse
    {
        $user = auth()->user();
        $user->certificationLevels()->newPivotQuery()->update(['is_primary' => false]);
        $user->certificationLevels()->updateExistingPivot($certLevel, [
            'is_primary' => true,
            'display_priority' => DB::raw('display_priority + 1'),
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Primary certification updated.'));
    }

    public function remove(int $certLevel): RedirectResponse
    {
        auth()->user()->certificationLevels()->detach($certLevel);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification removed.'));
    }
}
