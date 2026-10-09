{{-- Single kanban card. Rendered both server-side (initial page load) and as a
     JSON-wrapped fragment (AJAX move/add) — keep it self-contained, relying
     only on $card and $columns, never on an outer loop variable. --}}
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
            @if($s !== $card->status)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-kanban-move
                        data-url="{{ route('kanban.status', $card) }}" data-status="{{ $s }}">{{ $l }}</button>
            @endif
        @endforeach
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-kanban-discard
                data-url="{{ route('kanban.discard', $card) }}"
                title="{{ __('Obsolete — hide this card') }}">{{ __('Discard') }}</button>
    </div>
    <div data-kanban-comments class="{{ $card->comments->isNotEmpty() ? 'border-top mt-2 pt-2' : '' }}">
        @foreach($card->comments as $comment)
            <div class="small mb-1">
                <span class="dc-user-initials" style="width:20px;height:20px;font-size:0.65rem">{{ $comment->user?->initials() ?? '?' }}</span>
                <span class="text-muted">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                {{ $comment->body }}
            </div>
        @endforeach
    </div>
    <form data-kanban-comment-form class="d-flex gap-1 mt-2" data-url="{{ route('kanban.comments.store', $card) }}">
        <input type="text" name="body" class="form-control form-control-sm" placeholder="{{ __('Add a progress note…') }}" maxlength="2000" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Add') }}</button>
    </form>
</div>
