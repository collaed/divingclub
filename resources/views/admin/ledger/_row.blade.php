@php
    [$label, $class] = $labels[$tx->state] ?? ['?', 'lg-unknown'];
    // Bulk-confirm silently ignores anything not already green (see
    // LedgerController::bulkConfirm) — disabling the checkbox here instead
    // of letting it submit and do nothing. Caught live: a bureau member
    // selected amber rows, got "0 line(s) confirmed.", and read that as the
    // button being broken rather than as "nothing here was eligible."
    $bulkEligible = in_array($tx->state, [\App\Models\LedgerTransaction::STATE_EXPECTED, \App\Models\LedgerTransaction::STATE_RECOGNISED, \App\Models\LedgerTransaction::STATE_LOOP], true);
@endphp
<tr class="lg-row {{ $class }}" data-ledger-row data-ledger-id="{{ $tx->id }}">
    <td>
        <input type="checkbox" name="ids[]" value="{{ $tx->id }}" form="lg-bulk-form" class="form-check-input"
               @disabled(! $bulkEligible)
               @unless($bulkEligible) title="{{ __('Only green (Expected / Recognised / Paired) lines can be bulk-confirmed — review this one individually.') }}" @endunless>
    </td>
    <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
    <td style="min-width:14rem">
        <strong>{{ $tx->counterparty?->name ?? $tx->counterparty_name ?? __('Unknown') }}</strong>
        <div class="small text-muted">{{ $tx->communication() ?: '—' }}</div>
        @if($tx->state_reason)<div class="small text-muted fst-italic">{{ $tx->state_reason }}</div>@endif
    </td>
    <td class="lg-num {{ $tx->amount >= 0 ? 'lg-in' : 'lg-out' }}">{{ $tx->amount >= 0 ? '+' : '−' }}{{ number_format(abs((float) $tx->amount), 2, ',', ' ') }}</td>
    <td><span class="lg-pill {{ $class }}">{{ $label }}</span></td>
    <td>@if($tx->category)<span class="lg-chip" title="{{ config('ledger.categories')[$tx->category] ?? '' }}">{{ $tx->category }}</span>@endif</td>
    <td>
        @foreach($tx->tags as $tag)
            @php
                $dirClass = $tag->direction === 'in' ? 'lg-tag-in' : ($tag->direction === 'out' ? 'lg-tag-out' : ($tag->pivot->value ? '' : ($tag->kind === 'variable' ? 'lg-var-empty' : '')));
            @endphp
            <form method="POST" action="{{ route('admin.ledger.tag.remove', [$tx, $tag]) }}" class="d-inline-block mb-1" data-ledger-ajax>
                @csrf @method('DELETE')
                <span class="lg-tag {{ $dirClass }}">
                    #{{ $tag->label }}{{ $tag->pivot->value ? ': '.$tag->pivot->value : '' }}
                    <button type="submit" class="lg-tag-remove" aria-label="{{ __('Remove tag') }}" title="{{ __('Remove tag') }}">×</button>
                </span>
            </form>
        @endforeach
        <div class="dropdown d-inline-block">
            <button class="btn btn-sm btn-outline-secondary py-0 px-1 dropdown-toggle" type="button" data-bs-toggle="dropdown">+</button>
            <div class="dropdown-menu p-2" style="min-width:16rem">
                @foreach($fixedTags as $tag)
                    <form method="POST" action="{{ route('admin.ledger.tag', $tx) }}" class="mb-1" data-ledger-ajax>
                        @csrf<input type="hidden" name="tag_id" value="{{ $tag->id }}">
                        <button type="submit" class="dropdown-item small">
                            @if($tag->direction === 'in')<span class="lg-dot lg-dot-in" title="{{ __('Club receives') }}"></span>
                            @elseif($tag->direction === 'out')<span class="lg-dot lg-dot-out" title="{{ __('Club pays') }}"></span>
                            @endif
                            #{{ $tag->label }}
                        </button>
                    </form>
                @endforeach
                <hr class="my-1">
                @foreach($variableTags as $tag)
                    <form method="POST" action="{{ route('admin.ledger.tag', $tx) }}" class="d-flex gap-1 mb-1 px-2" data-ledger-ajax>
                        @csrf<input type="hidden" name="tag_id" value="{{ $tag->id }}">
                        <input type="text" name="value" class="form-control form-control-sm" placeholder="{{ $tag->label }}">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Set') }}</button>
                    </form>
                @endforeach
                <hr class="my-1">
                <form method="POST" action="{{ route('admin.ledger.tag.create', $tx) }}" class="d-flex flex-wrap gap-1 px-2" data-ledger-ajax>
                    @csrf
                    <input type="text" name="label" list="lg-tags-list" class="form-control form-control-sm" style="width:8rem" placeholder="{{ __('New tag…') }}" maxlength="60" required>
                    <select name="direction" class="form-select form-select-sm" style="width:auto" aria-label="{{ __('Money direction (only used when creating)') }}">
                        <option value="">{{ __('Neutral') }}</option>
                        <option value="in">{{ __('Club receives') }}</option>
                        <option value="out">{{ __('Club pays') }}</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ __('Add this tag to the list and apply it here') }}">{{ __('Add') }}</button>
                </form>
            </div>
        </div>
    </td>
    <td style="min-width:11rem">
        {{-- Usually one, but exceptionally more than one (e.g. a single van-rental
             invoice split between two outings) — so every assigned group shows,
             removable, and the box to add another always stays available. --}}
        @foreach($tx->operations as $op)
            <form method="POST" action="{{ route('admin.ledger.operation.remove', [$tx, $op]) }}" class="d-inline-block mb-1" data-ledger-ajax>
                @csrf @method('DELETE')
                <span class="lg-chip">{{ $op->name }}<button type="submit" class="lg-tag-remove" aria-label="{{ __('Remove from :name', ['name' => $op->name]) }}" title="{{ __('Remove from :name', ['name' => $op->name]) }}">×</button></span>
            </form>
        @endforeach
        @if($tx->suggested_group && ! $tx->operations->contains('name', $tx->suggested_group))
            <div class="small text-muted">{{ __('Suggested:') }} {{ $tx->suggested_group }}</div>
        @endif
        <form method="POST" action="{{ route('admin.ledger.assign-operation', $tx) }}" class="d-flex gap-1 mt-1" data-ledger-ajax>
            @csrf
            <input type="text" name="new_name" list="lg-operations-list" class="form-control form-control-sm" style="width:9rem"
                   value="{{ $tx->operations->isEmpty() ? $tx->suggested_group : '' }}" placeholder="{{ $tx->operations->isEmpty() ? __('Type or pick a group…') : __('Add another…') }}">
            <select name="new_kind" class="form-select form-select-sm" style="width:auto" aria-label="{{ __('Kind (only used when creating a new group)') }}">
                <option value="trip">{{ __('Trip') }}</option>
                <option value="loop">{{ __('Loop') }}</option>
                <option value="cost_centre">{{ __('Cost centre') }}</option>
                <option value="other">{{ __('Other') }}</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Assign') }}</button>
        </form>
    </td>
    <td>
        {{-- data-ledger-confirm: the submit handler also folds in whatever's
             currently showing in this row's group box (see index.blade.php) —
             it looks already "set" there even before "Assign" is clicked. --}}
        <form method="POST" action="{{ route('admin.ledger.confirm', $tx) }}" data-ledger-ajax data-ledger-confirm>
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Confirm') }}</button>
        </form>
    </td>
</tr>
