<x-layout :title="__('Actions')">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0">{{ __('Actions from the compte-rendus') }}</h4>
            <small class="text-muted">{{ __('Extracted automatically as meeting minutes are processed, a few hours apart. Check each card against its source before acting on it, and discard anything obsolete.') }}</small>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        @foreach($columns as $status => $label)
            <div class="col-md-4">
                <div class="card dc-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $label }}</span>
                        <span class="badge bg-secondary">{{ $cards->get($status, collect())->count() }}</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-2" data-kanban-column="{{ $status }}">
                        @forelse($cards->get($status, collect()) as $card)
                            <div class="border rounded p-2" data-kanban-card="{{ $card->id }}">
                                <div class="fw-semibold">{{ $card->title }}</div>
                                @if($card->responsible)
                                    <div class="small text-muted">{{ __('Responsible') }}: {{ $card->responsible }}</div>
                                @endif
                                @if($card->context)
                                    <div class="small text-muted fst-italic mt-1">“{{ $card->context }}”</div>
                                @endif
                                <div class="small text-muted mt-1">
                                    {{ $card->source_document_name }}
                                    @if($card->source_document_date) · {{ $card->source_document_date->format('d/m/Y') }} @endif
                                </div>
                                <div class="d-flex gap-1 mt-2">
                                    @foreach($columns as $s => $l)
                                        @if($s !== $status)
                                            <form method="POST" action="{{ route('kanban.status', $card) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $s }}">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $l }}</button>
                                            </form>
                                        @endif
                                    @endforeach
                                    <form method="POST" action="{{ route('kanban.discard', $card) }}" class="ms-auto">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Obsolete — hide this card') }}">{{ __('Discard') }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">{{ __('Nothing here yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layout>
