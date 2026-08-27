<x-layout :title="$vote->title">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card dc-card">
                <div class="card-header"><h5 class="mb-0">{{ $vote->title }}</h5></div>
                <div class="card-body">
                    @if($vote->description) <p>{{ $vote->description }}</p> @endif
                    <p class="small text-muted">
                        @if($vote->mode === 'election')
                            {{ __('Your vote is anonymous and irreversible.') }}
                            @if(($vote->num_positions ?? 1) > 1)
                                {{ __('Select up to :n candidates.', ['n' => $vote->num_positions]) }}
                            @endif
                        @elseif($vote->allow_change) {{ __('You can change your vote until it closes.') }}
                        @else {{ __('Your vote cannot be changed once submitted.') }}
                        @endif
                        @if($vote->allow_multiple) · {{ __('You may select multiple options.') }} @endif
                    </p>

                    @if($voteToken->is_consumed && ! $vote->allow_change)
                        <div class="alert alert-success">
                            <strong>✅ {{ __('Your vote has already been recorded.') }}</strong><br>
                            <small class="text-muted">{{ __('Submitted on') }} {{ $voteToken->consumed_at?->format('d/m/Y H:i') }}</small>
                        </div>
                        @foreach($vote->options as $opt)
                            <div class="form-check mb-2">
                                <input type="{{ $vote->allow_multiple ? 'checkbox' : 'radio' }}" class="form-check-input" disabled {{ in_array($opt->id, $currentBallots) ? 'checked' : '' }}>
                                <label class="form-check-label text-muted">{{ $opt->label }}</label>
                            </div>
                        @endforeach
                    @else

                    @if($currentBallots && $vote->allow_change)
                        <div class="alert alert-info small">{{ __('You have already voted. Submitting again will update your choices.') }}</div>
                    @endif

                    <form method="POST" action="{{ route('vote.cast', $voteToken->token) }}">
                        @csrf
                        @foreach($vote->options as $opt)
                            <div class="form-check mb-2">
                                @if($vote->allow_multiple || ($vote->mode === 'election' && ($vote->num_positions ?? 1) > 1))
                                    <input type="checkbox" name="option_ids[]" value="{{ $opt->id }}" class="form-check-input" id="opt{{ $opt->id }}" {{ in_array($opt->id, $currentBallots) ? 'checked' : '' }}>
                                @else
                                    <input type="radio" name="option_id" value="{{ $opt->id }}" class="form-check-input" id="opt{{ $opt->id }}" {{ in_array($opt->id, $currentBallots) ? 'checked' : '' }} required>
                                @endif
                                <label class="form-check-label" for="opt{{ $opt->id }}">{{ $opt->label }}</label>
                            </div>
                        @endforeach
                        <button class="btn btn-primary mt-3 w-100">
                            {{ $vote->mode === 'election' ? __('Cast Vote (irreversible)') : ($currentBallots ? __('Update Vote') : __('Submit Vote')) }}
                        </button>
                    </form>
                    @endif

                    {{-- Public results --}}
                    @if($vote->is_public && ($vote->mode !== 'election' || $vote->status === 'closed'))
                        @php $totalVotes = $vote->ballots()->count(); @endphp
                        @if($totalVotes > 0)
                            <hr>
                            <h6>{{ __('Current Results') }}</h6>
                            @foreach($vote->options as $opt)
                                @php $count = $vote->ballots()->where('vote_option_id', $opt->id)->count(); $pct = $totalVotes ? round($count / $totalVotes * 100) : 0; @endphp
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small"><span>{{ $opt->label }}</span><span>{{ $count }} ({{ $pct }}%)</span></div>
                                    <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ $pct }}%"></div></div>
                                </div>
                            @endforeach
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layout>
