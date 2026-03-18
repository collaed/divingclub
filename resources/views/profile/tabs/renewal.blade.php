@php $licences = $target->licences; @endphp
<h6>{{ __('Membership Renewal') }}</h6>
<p class="text-muted small">{{ __('This tab is read-only for members. Bureau can edit licence details.') }}</p>

@foreach($licences as $lic)
    <div class="card dc-card mb-3">
        <div class="card-body">
            <h6>{{ $lic->federation->acronym }} — {{ $lic->federation->full_name }}</h6>
            <div class="row">
                <div class="col-md-4"><strong>{{ __('Licence Number') }}:</strong> {{ $lic->licence_number ?? '—' }}</div>
                <div class="col-md-4"><strong>{{ __('Request Date') }}:</strong> {{ $lic->licence_request_date?->format('d/m/Y') ?? '—' }}</div>
                <div class="col-md-4"><strong>{{ __('Pending') }}:</strong> {{ $lic->licence_request_pending ? __('Yes') : __('No') }}</div>
            </div>
        </div>
    </div>
@endforeach

@if($licences->isEmpty())
    <p class="text-muted">{{ __('No licence records yet.') }}</p>
@endif
