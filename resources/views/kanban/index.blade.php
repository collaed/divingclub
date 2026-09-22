<x-layout :title="__('Actions')">
    <style>
        {{-- A manually-added card (no source compte-rendu) reads as less "vetted"
             than one the AI extracted — a dashed, lighter outline says so at a
             glance without needing a label on every card. --}}
        .kanban-card-manual { border-style: dashed !important; border-color: var(--bs-secondary-border-subtle) !important; }
    </style>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0">{{ __('Actions from the compte-rendus') }}</h4>
            <small class="text-muted">{{ __('Extracted automatically as meeting minutes are processed, a few hours apart. Check each card against its source before acting on it, and discard anything obsolete.') }}</small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#kanban-add-card">{{ __('+ Add a card') }}</button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div id="kanban-add-card" class="collapse mb-3 {{ $errors->any() || old('title') ? 'show' : '' }}">
        <div class="card dc-card">
            <div class="card-body">
                <form method="POST" action="{{ route('kanban.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Responsible') }}</label>
                        <input type="text" name="responsible" class="form-control" value="{{ old('responsible') }}" maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Context') }}</label>
                        <input type="text" name="context" class="form-control" value="{{ old('context') }}" maxlength="2000">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Add') }}</button>
                    </div>
                </form>
                <p class="small text-muted mt-2 mb-0">{{ __('Starts in "To do". Shown with a dashed outline to mark it as added by hand, not extracted from a compte-rendu.') }}</p>
            </div>
        </div>
    </div>

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
                            <div class="border rounded p-2 {{ $card->isManual() ? 'kanban-card-manual' : '' }}" data-kanban-card="{{ $card->id }}"
                                 @if($card->responsibleColor()) style="border-left: 4px solid {{ $card->responsibleColor() }} !important;" @endif>
                                <div class="fw-semibold">{{ $card->title }}</div>
                                @if($card->responsible)
                                    <div class="small text-muted">{{ __('Responsible') }}: {{ $card->responsible }}</div>
                                @endif
                                @if($card->context)
                                    <div class="small text-muted fst-italic mt-1">“{{ $card->context }}”</div>
                                @endif
                                <div class="small text-muted mt-1">
                                    @if($card->isManual())
                                        {{ __('Added by hand') }}
                                    @else
                                        {{ $card->source_document_name }}
                                        @if($card->source_document_date) · {{ $card->source_document_date->format('d/m/Y') }} @endif
                                    @endif
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
                                @if($card->comments->isNotEmpty())
                                    <div class="border-top mt-2 pt-2">
                                        @foreach($card->comments as $comment)
                                            <div class="small mb-1">
                                                <span class="dc-user-initials" style="width:20px;height:20px;font-size:0.65rem">{{ $comment->user?->initials() ?? '?' }}</span>
                                                <span class="text-muted">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                                {{ $comment->body }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('kanban.comments.store', $card) }}" class="d-flex gap-1 mt-2">
                                    @csrf
                                    <input type="text" name="body" class="form-control form-control-sm" placeholder="{{ __('Add a progress note…') }}" maxlength="2000" required>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Add') }}</button>
                                </form>
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
