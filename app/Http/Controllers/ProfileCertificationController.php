<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileCertificationController extends Controller
{
    /**
     * Resolve who the action applies to: self by default, or — for bureau —
     * whoever's profile the "Diving" tab's target_user_id hidden field names.
     * Every action always applied to auth()->user() regardless of which
     * profile the form was submitted from, silently editing the wrong
     * member's certifications; this restores the same target-resolution +
     * authorization ProfileController::updateDiving() already uses.
     */
    private function resolveTarget(Request $request): User
    {
        $viewer = auth()->user();
        $target = $request->filled('target_user_id') ? User::findOrFail($request->target_user_id) : $viewer;

        abort_if($target->id !== $viewer->id && ! $viewer->isBureau(), 403);

        return $target;
    }

    public function add(Request $request): RedirectResponse
    {
        $request->validate(['certification_level_id' => 'required|exists:certification_levels,id', 'obtained_date' => 'nullable|date']);
        $target = $this->resolveTarget($request);
        $target->certificationLevels()->syncWithoutDetaching([
            $request->certification_level_id => ['obtained_date' => $request->obtained_date, 'display_priority' => 0],
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification added.'));
    }

    public function update(Request $request, int $certLevel): RedirectResponse
    {
        $request->validate(['obtained_date' => 'nullable|date']);
        $target = $this->resolveTarget($request);
        $target->certificationLevels()->updateExistingPivot($certLevel, [
            'obtained_date' => $request->obtained_date,
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification updated.'));
    }

    public function setPrimary(Request $request, int $certLevel): RedirectResponse
    {
        $target = $this->resolveTarget($request);
        $target->certificationLevels()->newPivotQuery()->update(['is_primary' => false]);
        $target->certificationLevels()->updateExistingPivot($certLevel, [
            'is_primary' => true,
            'display_priority' => DB::raw('display_priority + 1'),
        ]);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Primary certification updated.'));
    }

    public function remove(Request $request, int $certLevel): RedirectResponse
    {
        $target = $this->resolveTarget($request);
        $target->certificationLevels()->detach($certLevel);

        return back()->withInput(['tab' => 'diving'])->with('success', __('Certification removed.'));
    }
}
