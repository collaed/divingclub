<?php

/**
 * Dive group (palanquée) management with Trello-style board UI.
 *
 * Manages dive group composition for events: manual CRUD, drag-drop member
 * assignment, rule validation against FFESSM/CMAS federation rules, and
 * auto-proposal of groups for the fiche de sécurité (safety sheet).
 *
 * @author  ClubCEP.eu
 *
 * @see     \App\Services\DiveGroupProposalService  — auto-proposal algorithm
 * @see     \App\Models\DiveGroupRule               — federation rule definitions
 * @see     resources/views/events/dive-groups.blade.php
 */

namespace App\Http\Controllers;

use App\Models\DiveGroup;
use App\Models\DiveGroupMember;
use App\Models\DiveGroupRule;
use App\Models\Event;
use App\Models\User;
use App\Services\DiveGroupProposalService;
use App\Services\Homogeneity\DiveContext;
use App\Services\Homogeneity\HomogeneityAssessmentService;
use App\Services\SwapSuggestionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiveGroupController extends Controller
{
    // ─── Board View & CRUD ─────────────────────────────────────

    public function index(Event $event): View
    {
        abort_unless($this->canView($event), 403);
        $event->load(['diveGroups.members.user.certificationLevels.federation', 'diveGroups.members.user.detail', 'registrations.user.certificationLevels.federation', 'registrations.user.detail']);
        $rules = DiveGroupRule::active()->orderBy('scope')->orderBy('min_leader_rank')->get();

        // Participants not yet assigned to any group
        $assignedIds = $event->diveGroups->flatMap(fn ($g) => $g->members->pluck('user_id'))->toArray();
        $unassigned = $event->registrations->where('status', 'confirmed')
            ->filter(fn ($r) => ! in_array($r->user_id, $assignedIds));

        // Stale detection: groups may be invalid if registrations changed after last group edit
        $groupsStale = false;
        if ($event->diveGroups->count()) {
            $lastGroupEdit = $event->diveGroups->max('updated_at');
            $confirmedRegs = $event->registrations->where('status', 'confirmed');
            $lastRegChange = $confirmedRegs->max('updated_at');
            // Also check for cancelled registrations whose user is still in a group
            $cancelledInGroup = $event->registrations->where('status', 'cancelled')
                ->whereIn('user_id', $assignedIds)->count();
            $groupsStale = ($lastRegChange && $lastRegChange > $lastGroupEdit) || $cancelledInGroup > 0 || $unassigned->count() > 0;
        }

        return view('events.dive-groups', compact('event', 'rules', 'unassigned', 'groupsStale'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canManage($event), 403);

        $request->validate([
            'name' => 'nullable|string|max:100',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'planned_depth' => 'nullable|integer|min:1|max:300',
            'planned_duration' => 'nullable|integer|min:1|max:300',
            'gas_mix' => 'nullable|in:'.implode(',', array_keys(DiveGroup::GAS_MIXES)),
            'line_number' => 'nullable|integer|min:1|max:4',
            'planned_entry_time' => 'nullable|date_format:H:i',
            'planned_exit_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'purpose' => 'nullable|string|max:50',
        ]);

        $group = DiveGroup::create([
            'event_id' => $event->id,
            'name' => $request->name ?: __('Group').' '.($event->diveGroups()->count() + 1),
            'dive_mode' => $request->dive_mode,
            'planned_depth' => $request->planned_depth,
            'planned_duration' => $request->planned_duration,
            'gas_mix' => $request->gas_mix ?? 'air',
            'line_number' => $request->line_number,
            'planned_entry_time' => $request->planned_entry_time,
            'planned_exit_time' => $request->planned_exit_time,
            'purpose' => $request->purpose,
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', __('Dive group created.'));
    }

    public function addMember(Request $request, DiveGroup $group): RedirectResponse
    {
        abort_unless($this->canManage($group->event), 403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:leader,diver',
        ]);

        // Prevent duplicate
        if ($group->members()->where('user_id', $request->user_id)->exists()) {
            return back()->with('error', __('Already in this group.'));
        }

        DiveGroupMember::create([
            'dive_group_id' => $group->id,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        return back()->with('success', __('Member added to group.'));
    }

    public function removeMember(DiveGroupMember $member): RedirectResponse
    {
        abort_unless($this->canManage($member->group->event), 403);
        $member->delete();

        return back()->with('success', __('Member removed from group.'));
    }

    public function toggleLeader(DiveGroupMember $member): RedirectResponse
    {
        abort_unless($this->canManage($member->group->event), 403);
        $member->update(['role' => $member->role === 'leader' ? 'diver' : 'leader']);

        return back();
    }

    public function destroy(DiveGroup $group): RedirectResponse
    {
        abort_unless($this->canManage($group->event), 403);
        $group->delete();

        return back()->with('success', __('Dive group deleted.'));
    }

    // ─── Rule Validation ──────────────────────────────────────

    /**
     * Validate all groups for an event against rules. Returns JSON.
     */
    public function validateGroups(Event $event): JsonResponse
    {
        $event->load(['diveGroups.members.user.certificationLevels', 'diveGroups.members.user.detail', 'diveSite']);
        $rules = DiveGroupRule::active()->get();
        $violations = [];
        $homogeneity = [];
        $assessor = new HomogeneityAssessmentService;

        foreach ($event->diveGroups as $group) {
            $groupKey = $group->name ?? 'Group '.$group->id;

            $groupViolations = $this->checkGroup($group, $rules);
            if ($groupViolations) {
                $violations[$groupKey] = $groupViolations;
            }

            // Homogeneity assessment
            $diverProfiles = $group->members->map(fn ($m) => $this->buildDiverProfile($m->user))->toArray();
            $ctx = new DiveContext(
                plannedDepth: $group->planned_depth ?? $event->diveSite?->max_depth ?? 20,
                waterTempCelsius: $event->diveSite?->water_temperature ?? 15.0,
            );
            $result = $assessor->assess($diverProfiles, $ctx);
            $homogeneity[$groupKey] = [
                'score' => $result->score,
                'status' => $result->status->value,
                'factors' => array_map(fn ($f) => [
                    'type' => $f->type->value,
                    'impact' => $f->scoreImpact,
                    'label' => $f->label,
                    'detail' => $f->detail,
                ], $result->factors),
                'recommendations' => $result->recommendations,
            ];
        }

        return response()->json([
            'valid' => empty($violations),
            'violations' => $violations,
            'homogeneity' => $homogeneity,
        ]);
    }

    /** Build a diver profile array for the homogeneity service. */
    private function buildDiverProfile(User $user): array
    {
        $detail = $user->detail;
        $cert = $this->getHighestCert($user);

        return [
            'name' => $user->name,
            'airConsumption' => (float) ($detail?->air_consumption ?? 0.5),
            'easeLevel' => (float) ($detail?->ease_level ?? 0.5),
            'primaryIntent' => $detail?->primary_intent ?? 'exploration',
            'isPhotographer' => (bool) ($detail?->is_photographer ?? false),
            'certRank' => $cert?->rank ?? 0,
            'totalDives' => (int) ($detail?->total_dives ?? $detail?->dive_count ?? 50),
            'lastDiveWeeksAgo' => $detail?->last_dive_date
                ? (int) now()->diffInWeeks($detail->last_dive_date)
                : 12,
            'age' => $detail?->date_of_birth?->age ?? 30,
            'isFragile' => ($detail?->date_of_birth?->age ?? 30) >= 65 || ($detail?->date_of_birth?->age ?? 30) < 16,
        ];
    }

    // ─── Auto-Proposal (Fiche de Sécurité) ───────────────────

    /**
     * Auto-propose dive groups based on federation rules (fiche de sécurité).
     * Returns JSON with proposed groups for preview before applying.
     */
    public function propose(Request $request, Event $event): JsonResponse
    {
        abort_unless($this->canManage($event), 403);

        $maxDepth = $request->input('max_depth', $event->diveSite?->max_depth ?? 20);
        $proposal = app(DiveGroupProposalService::class)->propose($event, (int) $maxDepth);

        return response()->json($proposal);
    }

    /**
     * Apply a proposed group configuration: clears existing groups and creates
     * new ones from the proposal. Saves the configuration so it can be reused
     * as a starting point if registrations change.
     */
    public function applyProposal(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canManage($event), 403);

        $request->validate([
            'groups' => 'required|array',
            'groups.*.name' => 'required|string|max:100',
            'groups.*.dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'groups.*.planned_depth' => 'nullable|integer|min:1',
            'groups.*.leader_id' => 'required|exists:users,id',
            'groups.*.member_ids' => 'array',
            'groups.*.member_ids.*' => 'exists:users,id',
        ]);

        // Clear existing groups for this event
        $event->diveGroups()->delete();

        foreach ($request->groups as $g) {
            $group = DiveGroup::create([
                'event_id' => $event->id,
                'name' => $g['name'],
                'dive_mode' => $g['dive_mode'],
                'planned_depth' => $g['planned_depth'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Add leader
            DiveGroupMember::create([
                'dive_group_id' => $group->id,
                'user_id' => $g['leader_id'],
                'role' => 'leader',
            ]);

            // Add divers
            foreach ($g['member_ids'] ?? [] as $uid) {
                DiveGroupMember::create([
                    'dive_group_id' => $group->id,
                    'user_id' => $uid,
                    'role' => 'diver',
                ]);
            }
        }

        return back()->with('success', __('Dive groups applied from proposal.'));
    }

    // ─── Rule Checking Engine ──────────────────────────────────

    /**
     * Suggest member swaps between groups to improve homogeneity scores.
     */
    public function suggestSwaps(Event $event): JsonResponse
    {
        abort_unless($this->canManage($event), 403);

        return response()->json(app(SwapSuggestionService::class)->suggest($event));
    }

    // Validates leader qualification, depth limits, and group size against
    // active DiveGroupRules. Federation-specific rules take priority over global.

    private function checkGroup(DiveGroup $group, $rules): array
    {
        $violations = [];
        $members = $group->members;

        if ($members->isEmpty()) {
            return [__('Group is empty.')];
        }

        // Find leader
        $leaderMember = $members->firstWhere('role', 'leader');
        if (! $leaderMember) {
            $violations[] = __('No group leader assigned.');

            return $violations;
        }

        $leaderCert = $this->getHighestCert($leaderMember->user);
        $leaderRank = $leaderCert?->rank ?? 0;
        $leaderCategory = $leaderCert?->category;

        // Check group size
        if ($members->count() > 4) {
            $violations[] = __('Group exceeds maximum size of 4.');
        }

        // Check each diver against applicable rules
        $diverMembers = $members->where('role', 'diver');
        foreach ($diverMembers as $dm) {
            $diverCert = $this->getHighestCert($dm->user);
            $diverRank = $diverCert?->rank ?? 0;
            $diverFed = $diverCert?->federation?->acronym;

            // Find applicable rules (federation-specific first, then global)
            $applicable = $rules->filter(function ($rule) use ($diverRank, $group, $diverFed) {
                if ($rule->dive_mode !== $group->dive_mode) {
                    return false;
                }
                if (! $rule->matchesDiver($diverRank)) {
                    return false;
                }

                // Prefer federation-specific rules
                return $rule->scope === 'global' || $rule->scope === $diverFed;
            })->sortByDesc(fn ($r) => $r->scope !== 'global' ? 1 : 0);

            $rule = $applicable->first();
            if (! $rule) {
                continue;
            } // No rule applies — allowed

            // Check leader qualification
            if (! $rule->leaderSatisfied($leaderRank, $leaderCategory)) {
                $violations[] = __(':diver requires a leader with at least rank :rank (:cat) — current leader: :leader', [
                    'diver' => $dm->user->name,
                    'rank' => $rule->min_leader_rank,
                    'cat' => $rule->leader_category,
                    'leader' => $leaderMember->user->name.' (rank '.$leaderRank.')',
                ]);
            }

            // Check depth
            if ($rule->max_depth && $group->planned_depth && $group->planned_depth > $rule->max_depth) {
                $violations[] = __(':diver max depth :max m (planned: :planned m)', [
                    'diver' => $dm->user->name,
                    'max' => $rule->max_depth,
                    'planned' => $group->planned_depth,
                ]);
            }

            // Check group size from rule
            if ($rule->max_group_size && $members->count() > $rule->max_group_size) {
                $violations[] = __(':rule limits group to :max members', [
                    'rule' => $rule->name,
                    'max' => $rule->max_group_size,
                ]);
            }
        }

        return array_unique($violations);
    }

    // ─── PDF Export (Fiche de Sécurité) ────────────────────────

    /** Generate a printable fiche de sécurité PDF for the event's dive groups. */
    public function printFiche(Event $event)
    {
        abort_unless($this->canView($event), 403);
        $event->load(['diveGroups.members.user.certificationLevels.federation', 'diveGroups.members.user.detail', 'diveSite', 'registrations']);

        $pdf = Pdf::loadView('events.fiche-securite-pdf', compact('event'))
            ->setPaper('a4', 'landscape');

        $filename = 'fiche-securite-'.$event->event_date->format('Y-m-d').'-'.\Str::slug($event->title).'.pdf';

        return $pdf->download($filename);
    }

    // ─── Authorization ────────────────────────────────────────

    private function getHighestCert($user): ?object
    {
        return $user->certificationLevels
            ->where('category', '!=', 'specialty')
            ->sortByDesc('rank')
            ->first();
    }

    /** Bureau, event instructor, or event assistants can manage groups. */
    private function canManage(Event $event): bool
    {
        $user = auth()->user();

        return $user->isBureau() || $event->instructor_id === $user->id || in_array($user->id, $event->assistant_ids ?? []);
    }

    private function canView(Event $event): bool
    {
        $user = auth()->user();
        if ($this->canManage($event)) {
            return true;
        }

        // Instructors can always view dive groups
        return $user->hasAnyRole(['instructor']);
    }
}
