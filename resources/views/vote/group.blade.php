<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->title }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #f0f4f8; } .vote-card { max-width: 700px; margin: 30px auto; }</style>
</head>
<body>
<div class="container py-4">
    <div class="vote-card">
        <div class="text-center mb-4">
            <h3>🗳️ {{ $group->title }}</h3>
            @if($group->description)<p class="text-muted">{{ $group->description }}</p>@endif
        </div>

        @if(session('success'))
            <div class="alert alert-success text-center">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @if($token->is_consumed && ! $group->votes->contains(fn($v) => $v->allow_change))
            <div class="alert alert-success text-center mb-4">
                <strong>✅ {{ __('Your vote has already been recorded.') }}</strong><br>
                <small class="text-muted">{{ __('Submitted on') }} {{ $token->consumed_at?->format('d/m/Y H:i') }}</small>
            </div>

            @foreach($group->votes as $vote)
            <div class="card mb-3 opacity-50">
                <div class="card-header fw-bold">
                    {{ $vote->title }}
                    @if($vote->mode === 'election')
                        <span class="badge bg-info float-end">{{ __('Select up to :n', ['n' => $vote->num_positions]) }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($vote->description)
                        <div class="mb-3 text-muted small">{!! $vote->description !!}</div>
                    @endif
                    @foreach($vote->options->sortBy('sort_order') as $option)
                        <div class="form-check mb-2">
                            <input type="{{ $vote->mode === 'election' ? 'checkbox' : 'radio' }}" class="form-check-input" disabled>
                            <label class="form-check-label text-muted">{{ $option->label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        @elseif(! $group->isOpen())
            <div class="alert alert-warning text-center">{{ __('This vote is not currently open.') }}</div>
        @else
            <form method="POST" action="{{ route('vote-group.cast', $token->token) }}">
                @csrf

                @foreach($group->votes as $vote)
                <div class="card mb-3">
                    <div class="card-header fw-bold">
                        {{ $vote->title }}
                        @if($vote->mode === 'election')
                            <span class="badge bg-info float-end">{{ __('Select up to :n', ['n' => $vote->num_positions]) }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($vote->description)
                            <div class="mb-3 text-muted small">{!! $vote->description !!}</div>
                        @endif
                        @if($vote->mode === 'election')
                            @foreach($vote->options->sortBy('sort_order') as $option)
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="votes[{{ $vote->id }}][]" value="{{ $option->id }}" class="form-check-input" id="opt_{{ $option->id }}" data-max="{{ $vote->num_positions }}" data-group="vote_{{ $vote->id }}">
                                    <label class="form-check-label" for="opt_{{ $option->id }}">{{ $option->label }}</label>
                                </div>
                            @endforeach
                        @else
                            @foreach($vote->options->sortBy('sort_order') as $option)
                                <div class="form-check mb-2">
                                    <input type="radio" name="votes[{{ $vote->id }}][]" value="{{ $option->id }}" class="form-check-input" id="opt_{{ $option->id }}" required>
                                    <label class="form-check-label" for="opt_{{ $option->id }}">{{ $option->label }}</label>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                @endforeach

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">{{ __('Submit Vote') }}</button>
                </div>
            </form>
        @endif
    </div>
</div>

<script>
// Limit checkbox selections for election mode
document.querySelectorAll('[data-max]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        const group = this.dataset.group;
        const max = parseInt(this.dataset.max);
        const checked = document.querySelectorAll('[data-group="' + group + '"]:checked');
        if (checked.length > max) {
            this.checked = false;
            alert('{{ __("Maximum :n selections allowed.") }}'.replace(':n', max));
        }
    });
});
</script>
</body>
</html>
