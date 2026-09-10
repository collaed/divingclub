<x-admin-layout :title="$dispatch->subject">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('admin.document-dispatch.index') }}">{{ __('Tracked documents') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $dispatch->subject }}</li>
        </ol>
    </nav>

    <h4>@icon('📤') {{ $dispatch->subject }}</h4>
    <p class="text-muted small mb-3">
        {{ $dispatch->file?->original_name ?? '—' }} ·
        {{ __('sent by') }} {{ $dispatch->creator?->name ?? '—' }} ·
        {{ $dispatch->created_at?->format('d/m/Y H:i') }} ·
        {{ $dispatch->recipient_summary }}
    </p>

    @php
        $sent = $dispatch->recipients->whereNotNull('sent_at')->count();
        $opened = $dispatch->recipients->whereNotNull('first_opened_at')->count();
    @endphp
    <div class="d-flex flex-wrap gap-3 mb-3">
        <span class="badge bg-secondary fs-6">{{ $sent }} / {{ $dispatch->recipients->count() }} {{ __('sent') }}</span>
        <span class="badge bg-primary fs-6">{{ $opened }} {{ __('opened') }}</span>
    </div>

    <x-table class="table-sm align-middle">
        <thead>
            <tr>
                <th>{{ __('Recipient') }}</th>
                <th>{{ __('Sent to') }}</th>
                <th class="text-end">{{ __('Opens') }}</th>
                <th>{{ __('First open') }}</th>
                <th>{{ __('Latest open — IP · country · device · time') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dispatch->recipients as $r)
                @php($last = $r->opens->first())
                <tr>
                    <td>{{ $r->user?->name ?? $r->email }}</td>
                    <td class="small text-muted"><code>{{ $r->email }}</code>@if($r->send_error)<span class="badge bg-danger ms-1" title="{{ $r->send_error }}">{{ __('send failed') }}</span>@elseif(! $r->sent_at)<span class="badge bg-warning text-dark ms-1">{{ __('queued') }}</span>@endif</td>
                    <td class="text-end">{{ $r->opens_count }}</td>
                    <td class="small text-muted text-nowrap">{{ $r->first_opened_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="small text-muted">
                        @if($last)
                            <code>{{ $last->ip_address ?? '—' }}</code>
                            @if($last->country_code) · {{ \App\Support\GeoLocator::flag($last->country_code) }} {{ $last->country_code }}@endif
                            <span title="{{ $last->user_agent }}"> · {{ \Illuminate\Support\Str::limit($last->user_agent, 40) }}</span>
                            · {{ $last->opened_at?->format('d/m/Y H:i') }}
                            @if($r->opens_count > 1)
                                <details class="mt-1"><summary class="text-primary" style="cursor:pointer">{{ __('all :n opens', ['n' => $r->opens_count]) }}</summary>
                                    @foreach($r->opens as $o)
                                        <div><code>{{ $o->ip_address ?? '—' }}</code> · {{ $o->country_code ? \App\Support\GeoLocator::flag($o->country_code).' '.$o->country_code : '' }} · {{ \Illuminate\Support\Str::limit($o->user_agent, 40) }} · {{ $o->opened_at?->format('d/m/Y H:i:s') }}</div>
                                    @endforeach
                                </details>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
</x-admin-layout>
