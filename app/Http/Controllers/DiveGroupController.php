<?php

namespace App\Http\Controllers;

use App\Models\DiveGroup;
use App\Models\DiveGroupMember;
use App\Models\DiveGroupRule;
use App\Models\Event;
use Illuminate\Http\Request;

class DiveGroupController extends Controller
{
    public function index(Event $event)
    {
        $event->load(['diveGroups.members.user.certificationLevels', 'registrations.user.certificationLevels']);
        $rules = DiveGroupRule::active()->orderBy('scope')->orderBy('min_leader_rank')->get();

        // Participants not yet assigned to any group
        $assignedIds = $event->diveGroups->flatMap(fn($g) => $g->members->pluck('user_id'))->toArray();
        $unassigned = $event->registrations->where('status', 'confirmed')
            ->filter(fn($r) => !in_array($r->user_id, $assignedIds));

        return view('events.dive-groups', compact('event', 'rules', 'unassigned'));
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($this->canManage($event), 403);

        $request->validate([
            'name' => 'nullable|string|max:100',
            'dive_mode' => 'required|in:supervised,autonomous,training,certification',
            'planned_depth' => 'nullable|integer|min:1|max:300',
            'notes' => 'nullable|string|max:500',
        ]);

        $group = DiveGroup::create([
            'event_id' => $event->id,
            'name' => $request->name ?: __('Group') . ' ' . ($event->diveGroups()->count() + 1),
            'dive_mode' => $request->dive_mode,
            'planned_depth' => $request->planned_depth,
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', __('Dive group created.'));
    }

    public function addMember(Request $request, DiveGroup $group)
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

    public function removeMember(DiveGroupMember $member)
    {
        abort_unless($this->canManage($member->group->event), 403);
        $member->delete();
        return back()->with('success', __('Member removed from group.'));
    }

    public function destroy(DiveGroup $group)
    {
        abort_unless($this->canManage($group->event), 403);
        $group->delete();
        return back()->with('success', __('Dive group deleted.'));
    }

    /**
     * Validate all groups for an event against rules. Returns JSON.
     */
    public function validate_groups(Event $event)
    {
        $event->load(['diveGroups.members.user.certificationLevels']);
        $rules = DiveGroupRule::active()->get();
        $violations = [];

        foreach ($event->diveGroups as $group) {
            $groupViolations = $this->checkGroup($group, $rules);
            if ($groupViolations) {
                $violations[$group->name ?? 'Group ' . $group->id] = $groupViolations;
            }
        }

        return response()->json(['valid' => empty($violations), 'violations' => $violations]);
    }

    private function checkGroup(DiveGroup $group, $rules): array
    {
        $violations = [];
        $members = $group->members;

        if ($members->isEmpty()) {
            return [__('Group is empty.')];
        }

        // Find leader
        $leaderMember = $members->firstWhere('role', 'leader');
        if (!$leaderMember) {
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
                if ($rule->dive_mode !== $group->dive_mode) return false;
                if (!$rule->matchesDiver($diverRank)) return false;
                // Prefer federation-specific rules
                return $rule->scope === 'global' || $rule->scope === $diverFed;
            })->sortByDesc(fn($r) => $r->scope !== 'global' ? 1 : 0);

            $rule = $applicable->first();
            if (!$rule) continue; // No rule applies — allowed

            // Check leader qualification
            if (!$rule->leaderSatisfied($leaderRank, $leaderCategory)) {
                $violations[] = __(':diver requires a leader with at least rank :rank (:cat) — current leader: :leader', [
                    'diver' => $dm->user->name,
                    'rank' => $rule->min_leader_rank,
                    'cat' => $rule->leader_category,
                    'leader' => $leaderMember->user->name . ' (rank ' . $leaderRank . ')',
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

    private function getHighestCert($user): ?object
    {
        return $user->certificationLevels
            ->where('category', '!=', 'specialty')
            ->sortByDesc('rank')
            ->first();
    }

    private function canManage(Event $event): bool
    {
        $user = auth()->user();
        return $user->isBureau() || $event->instructor_id === $user->id || in_array($user->id, $event->assistant_ids ?? []);
    }
}
