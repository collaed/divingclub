<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FamilyInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-service "family" grouping: a shared family_id on member_details that
 * lets members act for one another on 3 specific screens (event-registration
 * quick-pick, medical-cert upload, dues preview) — see User::canActFor().
 * Adults joining must mutually confirm; a minor can only be added by someone
 * who already holds a GuardianLink to them (reuses the existing admin-vetted
 * guardian system, never a new parallel authority).
 */
class FamilyController extends Controller
{
    public function propose(Request $request): RedirectResponse
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        if ($actor->isMinor()) {
            return back()->with('error', __('A minor cannot start or add to a family.'));
        }

        $v = $request->validate(['member_id' => 'required|integer|exists:users,id']);
        $candidate = User::findOrFail($v['member_id']);

        if ($candidate->is($actor)) {
            return back()->with('error', __('You cannot add yourself.'));
        }

        $actorFamilyId = $actor->detail?->family_id;
        $candidateFamilyId = $candidate->detail?->family_id;

        if ($actorFamilyId !== null && $candidateFamilyId === $actorFamilyId) {
            return back()->with('error', __('Already part of your family.'));
        }

        if ($candidateFamilyId !== null && $actorFamilyId !== null && $candidateFamilyId !== $actorFamilyId) {
            return back()->with('error', __('That member already belongs to a different family — remove them from it first.'));
        }

        if ($candidate->isMinor()) {
            if (! $actor->minors->contains($candidate)) {
                return back()->with('error', __('You need an existing guardian link to this child (see Minors & Consent) before adding them to your family.'));
            }

            $familyId = $actorFamilyId ?? $actor->id;
            $actor->detail?->update(['family_id' => $familyId]);
            $candidate->detail?->update(['family_id' => $familyId]);

            return back()->with('success', __(':name added to your family.', ['name' => $candidate->name]));
        }

        if (FamilyInvite::where('status', 'pending')
            ->where(fn ($q) => $q->where(['requester_id' => $actor->id, 'invitee_id' => $candidate->id])
                ->orWhere(['requester_id' => $candidate->id, 'invitee_id' => $actor->id]))
            ->exists()) {
            return back()->with('error', __('An invite with :name is already pending.', ['name' => $candidate->name]));
        }

        FamilyInvite::create(['requester_id' => $actor->id, 'invitee_id' => $candidate->id, 'status' => 'pending']);

        return back()->with('success', __('Invite sent to :name — waiting for them to confirm.', ['name' => $candidate->name]));
    }

    public function confirm(FamilyInvite $invite): RedirectResponse
    {
        abort_unless(auth()->id() === $invite->invitee_id, 403);

        if ($invite->status !== 'pending') {
            return back()->with('error', __('This invite is no longer pending.'));
        }

        $requester = $invite->requester;
        $invitee = $invite->invitee;
        $reqFamilyId = $requester->detail?->family_id;
        $invFamilyId = $invitee->detail?->family_id;

        if ($reqFamilyId !== null && $invFamilyId !== null && $reqFamilyId !== $invFamilyId) {
            return back()->with('error', __('One of you already belongs to a different family — this can no longer be confirmed.'));
        }

        $familyId = $reqFamilyId ?? $invFamilyId ?? $requester->id;
        $requester->detail?->update(['family_id' => $familyId]);
        $invitee->detail?->update(['family_id' => $familyId]);
        $invite->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        return back()->with('success', __('You are now family with :name.', ['name' => $requester->name]));
    }

    public function decline(FamilyInvite $invite): RedirectResponse
    {
        abort_unless(auth()->id() === $invite->invitee_id, 403);

        $invite->update(['status' => 'declined']);

        return back()->with('success', __('Invite declined.'));
    }

    public function leave(): RedirectResponse
    {
        auth()->user()->detail?->update(['family_id' => null]);

        return back()->with('success', __('You have left your family group.'));
    }
}
