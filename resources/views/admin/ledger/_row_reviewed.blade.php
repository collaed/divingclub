@php
    [$label, $class] = $labels[$tx->state] ?? ['?', 'lg-unknown'];
@endphp
{{-- Read-only besides Unconfirm: an already-confirmed line is done, and
     re-opening its tags/groups here would need the pill counts on this page
     (confirmed-scoped) and the inbox's (unconfirmed-scoped) to somehow stay
     in sync from the same edit — simpler to send it back to the inbox to
     change anything, via Unconfirm. --}}
<tr class="lg-row {{ $class }}" data-ledger-row data-ledger-id="{{ $tx->id }}">
    <td class="small">{{ $tx->transaction_date->format('d/m/Y') }}</td>
    <td style="min-width:14rem">
        <strong>{{ $tx->counterparty?->name ?? $tx->counterparty_name ?? __('Unknown') }}</strong>
        <div class="small text-muted">{{ $tx->communication() ?: '—' }}</div>
    </td>
    <td class="lg-num {{ $tx->amount >= 0 ? 'lg-in' : 'lg-out' }}">{{ $tx->amount >= 0 ? '+' : '−' }}{{ number_format(abs((float) $tx->amount), 2, ',', ' ') }}</td>
    <td><span class="lg-pill {{ $class }}">{{ $label }}</span></td>
    <td>@if($tx->category)<span class="lg-chip" title="{{ config('ledger.categories')[$tx->category] ?? '' }}">{{ $tx->category }}</span>@endif</td>
    <td>
        @foreach($tx->tags as $tag)
            @php $dirClass = $tag->direction === 'in' ? 'lg-tag-in' : ($tag->direction === 'out' ? 'lg-tag-out' : ''); @endphp
            <span class="lg-tag {{ $dirClass }}">#{{ $tag->label }}{{ $tag->pivot->value ? ': '.$tag->pivot->value : '' }}</span>
        @endforeach
    </td>
    <td style="min-width:11rem">
        @foreach($tx->operations as $op)<span class="lg-chip">{{ $op->name }}</span>@endforeach
    </td>
    <td class="small text-muted">
        {{ $tx->confirmed_at?->format('d/m/Y H:i') }}
        @if($tx->confirmedBy)<div>{{ __('by :name', ['name' => $tx->confirmedBy->username ?? $tx->confirmedBy->primary_email]) }}</div>@endif
    </td>
    <td>
        <form method="POST" action="{{ route('admin.ledger.unconfirm', $tx) }}" data-ledger-ajax>
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-warning">{{ __('Unconfirm') }}</button>
        </form>
    </td>
</tr>
