<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VoteBallot;
use App\Models\VoteToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VoteGroupPublicController extends Controller
{
    public function show(string $token): View
    {
        $voteToken = VoteToken::where('token', $token)->whereNotNull('vote_group_id')->firstOrFail();
        $group = $voteToken->voteGroup;
        $group->load('votes.options');

        return view('vote.group', ['group' => $group, 'token' => $voteToken]);
    }

    public function cast(Request $request, string $token): RedirectResponse
    {
        $voteToken = VoteToken::where('token', $token)->whereNotNull('vote_group_id')->firstOrFail();
        $group = $voteToken->voteGroup;

        if (! $group->isOpen()) {
            return back()->withErrors(__('This vote is not currently open.'));
        }

        $group->load('votes.options');
        $tokenHash = hash('sha256', $voteToken->token);

        $request->validate([
            'votes' => 'required|array',
        ]);

        foreach ($group->votes as $vote) {
            $selectedIds = $request->input("votes.{$vote->id}", []);
            if (! is_array($selectedIds)) {
                $selectedIds = [$selectedIds];
            }
            $selectedIds = array_filter($selectedIds);

            if (empty($selectedIds)) {
                continue;
            }

            // Validate selections belong to this vote
            $validOptionIds = $vote->options->pluck('id')->toArray();
            $selectedIds = array_intersect($selectedIds, $validOptionIds);

            // Enforce max selections for elections
            if ($vote->mode === 'election' && count($selectedIds) > $vote->num_positions) {
                return back()->withErrors(__('Too many selections for ":title".', ['title' => $vote->title]));
            }

            // Remove previous ballots if allow_change
            if ($vote->allow_change) {
                VoteBallot::where('vote_id', $vote->id)->where('token_hash', $tokenHash)->delete();
            } elseif (VoteBallot::where('vote_id', $vote->id)->where('token_hash', $tokenHash)->exists()) {
                continue; // Already voted and no change allowed
            }

            foreach ($selectedIds as $optionId) {
                VoteBallot::create([
                    'vote_id' => $vote->id,
                    'vote_option_id' => $optionId,
                    'token_hash' => $tokenHash,
                ]);
            }
        }

        $voteToken->update(['is_consumed' => true, 'consumed_at' => now()]);

        return back()->with('success', __('Your vote has been recorded. Thank you!'));
    }
}
