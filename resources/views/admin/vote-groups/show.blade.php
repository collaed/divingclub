<x-admin-layout :title="$group->title">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">🗳️ {{ $group->title }}</h4>
        <div class="d-flex gap-2">
            @if($group->status === 'draft')
                <form method="POST" action="{{ route('admin.vote-groups.open', $group) }}">@csrf <button class="btn btn-sm btn-success">{{ __('Open Voting') }}</button></form>
            @elseif($group->status === 'open')
                <form method="POST" action="{{ route('admin.vote-groups.close', $group) }}" data-confirm="{{ __('Close voting? No more votes will be accepted.') }}" data-confirm-style="warning" data-confirm-btn="{{ __('Close') }}">@csrf <button class="btn btn-sm btn-dark">{{ __('Close Voting') }}</button></form>
            @endif
        </div>
    </div>

    @php $badges = ['draft'=>'secondary','open'=>'success','closed'=>'dark']; @endphp
    <p>
        <span class="badge bg-{{ $badges[$group->status] ?? 'secondary' }} fs-6">{{ ucfirst($group->status) }}</span>
        @if($group->opens_at) <span class="text-muted ms-2">{{ __('Opens') }}: {{ $group->opens_at->format('d/m/Y H:i') }}</span> @endif
        @if($group->closes_at) <span class="text-muted ms-2">{{ __('Closes') }}: {{ $group->closes_at->format('d/m/Y H:i') }}</span> @endif
    </p>
    @if($group->description)<p class="text-muted">{{ $group->description }}</p>@endif

    {{-- Token management --}}
    <div class="card dc-card mb-4">
        <div class="card-header fw-bold">{{ __('Tokens') }} ({{ $group->tokens->count() }})</div>
        <div class="card-body">
            <p>{{ __('Consumed') }}: {{ $group->tokens->where('is_consumed', true)->count() }} / {{ $group->tokens->count() }}</p>
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('admin.vote-groups.generate-tokens', $group) }}">@csrf <button class="btn btn-sm btn-primary">{{ __('Generate Tokens') }}</button></form>
                @if($group->tokens->count())
                <form method="POST" action="{{ route('admin.vote-groups.send-tokens', $group) }}" data-confirm="{{ __('Send voting links to all members?') }}" data-confirm-style="warning" data-confirm-btn="{{ __('Send') }}">@csrf <button class="btn btn-sm btn-outline-success">{{ __('Send Voting Links') }}</button></form>
                @endif
            </div>
        </div>
    </div>

    {{-- Questions & results --}}
    @foreach($group->votes as $vote)
    <div class="card dc-card mb-3">
        <div class="card-header d-flex justify-content-between">
            <strong>{{ $vote->title }}</strong>
            <span class="badge bg-info">{{ $vote->mode === 'election' ? __('Election (pick :n)', ['n' => $vote->num_positions]) : __('Simple') }}</span>
        </div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Option') }}</th><th style="width:100px">{{ __('Votes') }}</th><th style="width:120px">{{ __('%') }}</th></tr></thead>
                <tbody>
                @php $totalBallots = $vote->ballots->unique('token_hash')->count(); @endphp
                @foreach($vote->options->sortByDesc(fn($o) => $o->ballots->count()) as $option)
                    @php $count = $option->ballots->count(); $pct = $totalBallots ? round($count / $totalBallots * 100) : 0; @endphp
                    <tr>
                        <td>{{ $option->label }}</td>
                        <td>{{ $count }}</td>
                        <td>
                            <div class="progress" style="height:18px">
                                <div class="progress-bar" style="width:{{ $pct }}%">{{ $pct }}%</div>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <small class="text-muted">{{ __('Total voters') }}: {{ $totalBallots }}</small>
        </div>
    </div>
    @endforeach
</x-admin-layout>
