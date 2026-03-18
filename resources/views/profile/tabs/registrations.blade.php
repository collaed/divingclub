@php
    $from = now()->subYears(2)->startOfYear();
    $to = now()->addYear()->endOfYear();
    $regs = \App\Models\EventRegistration::where('user_id', $target->id)
        ->whereHas('event', fn($q) => $q->whereBetween('event_date', [$from, $to]))
        ->with('event')
        ->get()
        ->sortByDesc(fn($r) => $r->event->event_date);
    $today = now()->startOfDay();
    $tomorrow = now()->addDay()->startOfDay();
@endphp

<h6>{{ __('Event Registrations') }} <small class="text-muted">({{ $from->format('Y') }}–{{ $to->format('Y') }})</small></h6>

@if($regs->isEmpty())
    <p class="text-muted">{{ __('No registrations found.') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Event') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @foreach($regs as $reg)
                @php
                    $evDate = $reg->event->event_date;
                    $isToday = $evDate->isSameDay($today);
                    $isTomorrow = $evDate->isSameDay($tomorrow);
                    $rowClass = $isToday ? 'table-warning fw-bold' : ($isTomorrow ? 'table-info' : ($evDate->isPast() ? '' : ''));
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $evDate->format('d/m/Y') }}
                        @if($isToday) <span class="badge bg-warning text-dark">{{ __('Today') }}</span>
                        @elseif($isTomorrow) <span class="badge bg-info">{{ __('Tomorrow') }}</span>
                        @endif
                    </td>
                    <td><a href="{{ route('events.show', $reg->event) }}">{{ $reg->event->title }}</a></td>
                    <td><span class="badge" style="background:{{ $reg->event->typeColor() }}">{{ ucfirst($reg->event->event_type) }}</span></td>
                    <td><span class="badge bg-{{ $reg->status === 'confirmed' ? 'success' : ($reg->status === 'waiting' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($reg->status) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="small text-muted">{{ $regs->count() }} {{ __('registrations') }} · <span class="text-warning">■</span> {{ __('Today') }} · <span class="text-info">■</span> {{ __('Tomorrow') }}</p>
@endif
