<?php

namespace App\Http\Controllers;

use App\Models\VoteBallot;
use App\Models\VoteToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VotePublicController extends Controller
{
    public function show(string $token): RedirectResponse|View
    {
        $voteToken = VoteToken::where('token', $token)->with(['vote.options', 'user'])->firstOrFail();
        $vote = $voteToken->vote;

        if (! $vote->isOpen()) {
            return view('vote.closed', compact('vote'));
        }

        $tokenHash = hash('sha256', $token);
        $currentBallots = VoteBallot::where('vote_id', $vote->id)
            ->where('token_hash', $tokenHash)->pluck('vote_option_id')->toArray();

        return view('vote.show', compact('vote', 'voteToken', 'currentBallots'));
    }

    public function cast(Request $request, string $token): RedirectResponse|View
    {
        $voteToken = VoteToken::where('token', $token)->with('vote.options')->firstOrFail();
        $vote = $voteToken->vote;

        if (! $vote->isOpen()) {
            return back()->with('error', __('This vote is no longer open.'));
        }

        $tokenHash = hash('sha256', $token);

        // Election mode: anonymous, irreversible, multi-position
        if ($vote->mode === 'election') {
            if ($voteToken->is_consumed) {
                return back()->with('error', __('You have already voted. Election votes cannot be changed.'));
            }

            $maxSelections = $vote->num_positions ?? 1;

            if ($maxSelections > 1) {
                $request->validate([
                    'option_ids' => 'required|array|min:1|max:'.$maxSelections,
                    'option_ids.*' => 'exists:vote_options,id',
                ]);
                $selectedIds = $request->option_ids;
            } else {
                $request->validate(['option_id' => 'required|exists:vote_options,id']);
                $selectedIds = [$request->option_id];
            }

            DB::transaction(function () use ($vote, $voteToken, $selectedIds) {
                foreach ($selectedIds as $optId) {
                    VoteBallot::create(['vote_id' => $vote->id, 'vote_option_id' => $optId, 'token_hash' => null]);
                }
                $voteToken->update(['is_consumed' => true, 'consumed_at' => now()]);
            });

            return view('vote.thankyou', compact('vote'));
        }

        // Simple/public mode: changeable, optionally multi-select
        if (! $vote->allow_change) {
            $existing = VoteBallot::where('vote_id', $vote->id)->where('token_hash', $tokenHash)->exists();
            if ($existing) {
                return back()->with('error', __('You have already voted and this vote does not allow changes.'));
            }
        }

        if ($vote->allow_multiple) {
            $request->validate(['option_ids' => 'required|array|min:1', 'option_ids.*' => 'exists:vote_options,id']);
            DB::transaction(function () use ($vote, $tokenHash, $request) {
                VoteBallot::where('vote_id', $vote->id)->where('token_hash', $tokenHash)->delete();
                foreach ($request->option_ids as $optId) {
                    VoteBallot::create(['vote_id' => $vote->id, 'vote_option_id' => $optId, 'token_hash' => $tokenHash]);
                }
            });
        } else {
            $request->validate(['option_id' => 'required|exists:vote_options,id']);
            VoteBallot::updateOrCreate(
                ['vote_id' => $vote->id, 'token_hash' => $tokenHash],
                ['vote_option_id' => $request->option_id]
            );
        }

        return back()->with('success', $vote->allow_change
            ? __('Your vote has been recorded. You can change it until the vote closes.')
            : __('Your vote has been recorded.'));
    }
}
