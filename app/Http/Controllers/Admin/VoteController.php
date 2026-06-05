<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteOption;
use App\Models\VoteToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoteController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $votes = Vote::withCount(['tokens', 'ballots'])->orderByDesc('created_at')->get();

        return view('admin.votes.index', compact('votes'));
    }

    public function create(): RedirectResponse|View
    {
        return view('admin.votes.form', ['vote' => new Vote]);
    }

    public function store(Request $request): RedirectResponse|View
    {
        $request->merge(['options' => array_values(array_filter($request->input('options', []), fn ($v): bool => trim($v) !== ''))]);

        $v = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'mode' => 'required|in:simple,election',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'num_positions' => 'nullable|integer|min:1|max:20',
            'min_vote_pct' => 'nullable|integer|min:0|max:100',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string|max:255',
        ]);

        $vote = Vote::create([
            'title' => $v['title'],
            'description' => $v['description'],
            'mode' => $v['mode'],
            'allow_multiple' => $request->boolean('allow_multiple'),
            'allow_change' => $request->boolean('allow_change'),
            'is_public' => $request->boolean('is_public'),
            'num_positions' => $v['num_positions'] ?? 1,
            'min_vote_pct' => $v['min_vote_pct'] ?? 50,
            'opens_at' => $v['opens_at'],
            'closes_at' => $v['closes_at'],
            'created_by' => auth()->id(),
        ]);

        foreach ($v['options'] as $i => $label) {
            VoteOption::create(['vote_id' => $vote->id, 'label' => $label, 'sort_order' => $i]);
        }

        return redirect()->route('admin.votes.show', $vote)->with('success', __('Vote created.'));
    }

    public function show(Vote $vote): RedirectResponse|View
    {
        $vote->load(['options.ballots', 'tokens']);
        $results = $vote->options->map(fn ($o): array => ['label' => $o->label, 'count' => $o->ballots->count()]);

        return view('admin.votes.show', compact('vote', 'results'));
    }

    public function generateTokens(Vote $vote): RedirectResponse
    {
        $users = User::whereNotNull('email_verified_at')->get();
        $created = 0;

        foreach ($users as $user) {
            if (! $vote->tokens()->where('user_id', $user->id)->exists()) {
                VoteToken::create([
                    'vote_id' => $vote->id,
                    'user_id' => $user->id,
                    'token' => Str::random(128),
                ]);
                $created++;
            }
        }

        return back()->with('success', __(':count tokens generated.', ['count' => $created]));
    }

    public function open(Vote $vote): RedirectResponse
    {
        $vote->update(['status' => 'open']);

        return back()->with('success', __('Vote opened.'));
    }

    public function close(Vote $vote): RedirectResponse
    {
        $vote->update(['status' => 'closed']);

        return back()->with('success', __('Vote closed.'));
    }

    public function cancel(Vote $vote): RedirectResponse
    {
        $vote->update(['status' => 'cancelled']);

        return back()->with('success', __('Vote cancelled.'));
    }
}
