<x-layout :title="__('Votes')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">{{ __('Votes & Elections') }}</h4>
        <a href="{{ route('admin.votes.create') }}" class="btn btn-sm btn-primary">{{ __('New Vote') }}</a>
    </div>

    @foreach($votes as $vote)
        <div class="card dc-card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">{{ $vote->title }}</h6>
                    <span class="badge bg-{{ $vote->status === 'open' ? 'success' : ($vote->status === 'closed' ? 'secondary' : ($vote->status === 'draft' ? 'info' : 'danger')) }}">{{ ucfirst($vote->status) }}</span>
                    <span class="badge bg-outline-secondary">{{ ucfirst($vote->mode) }}</span>
                    <small class="text-muted ms-2">{{ $vote->tokens_count }} tokens · {{ $vote->ballots_count }} ballots</small>
                </div>
                <a href="{{ route('admin.votes.show', $vote) }}" class="btn btn-sm btn-outline-primary">{{ __('Manage') }}</a>
            </div>
        </div>
    @endforeach
</x-layout>
