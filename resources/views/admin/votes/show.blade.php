<x-layout :title="$vote->title">
    <h4 class="mb-2">{{ $vote->title }}</h4>
    <p class="text-muted">{{ ucfirst($vote->mode) }} · <span class="badge bg-{{ $vote->status === 'open' ? 'success' : 'secondary' }}">{{ ucfirst($vote->status) }}</span></p>

    @if($vote->description) <p>{{ $vote->description }}</p> @endif

    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card dc-card mb-3">
                <div class="card-header">{{ __('Results') }}@if($vote->mode === 'election' && ($vote->num_positions ?? 1) > 1) <small class="text-muted">— {{ $vote->num_positions }} {{ __('positions') }}, {{ $vote->min_vote_pct ?? 50 }}% {{ __('threshold') }}</small>@endif</div>
                <div class="card-body">
                    @php
                        $totalBallots = $results->sum('count');
                        $totalTokensConsumed = $vote->tokens->where('is_consumed', true)->count();
                        $minVotePct = $vote->min_vote_pct ?? 50;
                    @endphp
                    @foreach($results->sortByDesc('count') as $r)
                        @php
                            $pct = $totalBallots ? round($r['count'] / $totalBallots * 100) : 0;
                            $voterPct = $totalTokensConsumed ? round($r['count'] / $totalTokensConsumed * 100) : 0;
                            $elected = $vote->mode === 'election' && $vote->status === 'closed' && $voterPct >= $minVotePct;
                        @endphp
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>{{ $r['label'] }} @if($elected)<span class="badge bg-success">@icon('✓') {{ __('Elected') }}</span>@endif</span>
                                <strong>{{ $r['count'] }} ({{ $voterPct }}%)</strong>
                            </div>
                            <div class="progress" style="height:20px;">
                                <div class="progress-bar {{ $elected ? 'bg-success' : '' }}" style="width:{{ $pct }}%">{{ $pct }}%</div>
                            </div>
                        </div>
                    @endforeach
                    <p class="small text-muted mt-2">{{ __('Total ballots') }}: {{ $totalBallots }} · {{ __('Tokens') }}: {{ $vote->tokens->count() }} ({{ $totalTokensConsumed }} {{ __('consumed') }})</p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dc-card mb-3">
                <div class="card-header">{{ __('Actions') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.votes.generate-tokens', $vote) }}" class="mb-2">@csrf <button class="btn btn-sm btn-primary w-100">{{ __('Generate Tokens for All Members') }}</button></form>
                    @if($vote->status === 'draft')
                        <form method="POST" action="{{ route('admin.votes.open', $vote) }}" class="mb-2">@csrf <button class="btn btn-sm btn-success w-100">{{ __('Open Vote') }}</button></form>
                    @endif
                    @if($vote->status === 'open')
                        <form method="POST" action="{{ route('admin.votes.close', $vote) }}" class="mb-2">@csrf <button class="btn btn-sm btn-warning w-100">{{ __('Close Vote') }}</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.votes.cancel', $vote) }}">@csrf <button class="btn btn-sm btn-outline-danger w-100">{{ __('Cancel Vote') }}</button></form>
                </div>
            </div>
        </div>
    </div>
</x-layout>
