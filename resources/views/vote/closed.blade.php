<x-layout :title="__('Vote Closed')">
    <div class="row justify-content-center"><div class="col-lg-8 py-5">
        <h4 class="text-center">{{ $vote->title }}</h4>
        <p class="text-muted text-center">{{ __('This vote is no longer open.') }}</p>

        @if($vote->status === 'closed' && ($vote->is_public || auth()->check()))
            <div class="card dc-card mt-4">
                <div class="card-body">
                    <h6>{{ __('Results') }}</h6>
                    @php $totalBallots = $vote->ballots()->count(); @endphp
                    @foreach($vote->options()->withCount('ballots')->orderByDesc('ballots_count')->get() as $option)
                        @php $pct = $totalBallots > 0 ? round($option->ballots_count / $totalBallots * 100) : 0; @endphp
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>{{ $option->label }}</span>
                                <span>{{ $option->ballots_count }} ({{ $pct }}%)</span>
                            </div>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar" style="width:{{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                    <p class="text-muted small mt-2">{{ __(':count votes cast', ['count' => $totalBallots]) }}</p>
                </div>
            </div>
        @endif
    </div></div>
</x-layout>
