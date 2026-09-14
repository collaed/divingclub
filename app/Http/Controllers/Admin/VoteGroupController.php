<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\MemberStatus;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteGroup;
use App\Models\VoteOption;
use App\Models\VoteToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoteGroupController extends Controller
{
    public function index(): View
    {
        $groups = VoteGroup::withCount(['votes', 'tokens'])->orderByDesc('created_at')->get();

        return view('admin.vote-groups.index', compact('groups'));
    }

    public function create(): View
    {
        return view('admin.vote-groups.form', ['group' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after_or_equal:opens_at',
            'questions' => 'required|array|min:1',
            'questions.*.title' => 'required|string|max:255',
            'questions.*.description' => 'nullable|string|max:5000',
            'questions.*.mode' => 'required|in:simple,election',
            'questions.*.num_positions' => 'nullable|integer|min:1|max:20',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*' => 'required|string|max:255',
        ]);

        $group = VoteGroup::create([
            'title' => $v['title'],
            'description' => $v['description'],
            'opens_at' => $v['opens_at'],
            'closes_at' => $v['closes_at'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($v['questions'] as $q) {
            $vote = Vote::create([
                'vote_group_id' => $group->id,
                'title' => $q['title'],
                'description' => $q['description'] ?? null,
                'mode' => $q['mode'],
                'allow_multiple' => $q['mode'] === 'election',
                'allow_change' => $q['mode'] === 'simple',
                'num_positions' => $q['num_positions'] ?? 1,
                'min_vote_pct' => 50,
                'is_public' => false,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($q['options'] as $i => $label) {
                if (trim($label) === '') {
                    continue;
                }
                VoteOption::create(['vote_id' => $vote->id, 'label' => trim($label), 'sort_order' => $i]);
            }
        }

        return redirect()->route('admin.vote-groups.show', $group)->with('success', __('Vote group created with :count question(s).', ['count' => count($v['questions'])]));
    }

    public function show(VoteGroup $voteGroup): View
    {
        $voteGroup->load(['votes.options.ballots', 'tokens']);

        return view('admin.vote-groups.show', ['group' => $voteGroup]);
    }

    public function generateTokens(VoteGroup $voteGroup): RedirectResponse
    {
        $inactiveIds = MemberStatus::inactiveIds();
        $users = User::whereNotNull('email_verified_at')
            ->when($inactiveIds->isNotEmpty(), fn ($q) => $q->whereNotIn('status_id', $inactiveIds->all()))
            ->get();

        $created = 0;
        foreach ($users as $user) {
            if (! $voteGroup->tokens()->where('user_id', $user->id)->exists()) {
                VoteToken::create([
                    'vote_group_id' => $voteGroup->id,
                    'user_id' => $user->id,
                    'token' => Str::random(128),
                ]);
                $created++;
            }
        }

        return back()->with('success', __(':count tokens generated.', ['count' => $created]));
    }

    public function sendTokens(VoteGroup $voteGroup): RedirectResponse
    {
        $tokens = $voteGroup->tokens()->with('user')->get();
        $sent = 0;

        foreach ($tokens as $voteToken) {
            $user = $voteToken->user;
            if (! $user || ! $user->primary_email) {
                continue;
            }

            $url = route('vote-group.show', $voteToken->token);

            $status = (config('app.staging_mode') && ! config('app.staging_use_smtp')) ? 'staging_captured' : 'queued';

            EmailLog::create([
                'user_id' => $user->id,
                'to_email' => $user->primary_email,
                'from_name' => config('mail.from.name'),
                'from_email' => config('mail.from.address'),
                'subject' => __('Your voting link: :title', ['title' => $voteGroup->title]),
                'body' => view('emails.vote-token', ['vote' => $voteGroup, 'url' => $url, 'user' => $user])->render(),
                'template_slug' => 'vote-group-'.$voteGroup->id,
                'status' => $status,
                'direction' => 'out',
                'authorized' => true,
                'created_at' => now(),
            ]);
            $sent++;
        }

        return back()->with('success', __(':count voting links queued for sending.', ['count' => $sent]));
    }

    public function open(VoteGroup $voteGroup): RedirectResponse
    {
        $voteGroup->update(['status' => 'open']);
        $voteGroup->votes()->update(['status' => 'open']);

        return back()->with('success', __('Vote group opened.'));
    }

    public function close(VoteGroup $voteGroup): RedirectResponse
    {
        $voteGroup->update(['status' => 'closed']);
        $voteGroup->votes()->update(['status' => 'closed']);

        return back()->with('success', __('Vote group closed.'));
    }
}
