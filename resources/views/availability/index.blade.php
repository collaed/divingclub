<x-layout :title="__('Instructor Availability')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">📅 {{ __('Instructor Availability') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('availability.index', ['month' => $start->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary">← {{ $start->copy()->subMonth()->translatedFormat('M Y') }}</a>
            <span class="btn btn-sm btn-primary disabled">{{ $start->translatedFormat('F Y') }}</span>
            <a href="{{ route('availability.index', ['month' => $start->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary">{{ $start->copy()->addMonth()->translatedFormat('M Y') }} →</a>
        </div>
    </div>

    @if($isInstructor)
        <div class="alert alert-info small py-2">
            💡 {{ __('Click a cell to toggle your availability. Green = you are available.') }}
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-bordered table-sm text-center align-middle" style="table-layout:fixed">
            <thead class="table-dark">
                <tr>
                    <th style="width:100px">{{ __('Date') }}</th>
                    @foreach($instructors as $inst)
                        <th class="small" style="writing-mode:vertical-lr;transform:rotate(180deg);height:100px;white-space:nowrap">
                            {{ $inst->detail?->first_name ?? $inst->name }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $cursor = $start->copy(); @endphp
                @while($cursor->lte($end))
                    @php
                        $dateStr = $cursor->format('Y-m-d');
                        $dayAvails = $availabilities[$dateStr] ?? collect();
                        $isWeekend = $cursor->isWeekend();
                        $dow = $cursor->translatedFormat('D');
                        $isPast = $cursor->lt(today());
                        // Highlight Mon/Wed/Fri (pool nights)
                        $isPoolNight = in_array($cursor->dayOfWeekIso, [1, 3, 5]);
                    @endphp
                    <tr class="{{ $isWeekend ? 'table-light' : '' }} {{ $isPoolNight && !$isWeekend ? 'border-start border-3 border-primary' : '' }}">
                        <td class="text-start small fw-bold {{ $isPast ? 'text-muted' : '' }}">
                            {{ $dow }} {{ $cursor->format('d') }}
                            @if($isPoolNight && !$isWeekend)<span class="badge bg-primary bg-opacity-25 text-primary ms-1" style="font-size:.6rem">🏊</span>@endif
                        </td>
                        @foreach($instructors as $inst)
                            @php $hasAvail = $dayAvails->contains('user_id', $inst->id); @endphp
                            <td class="{{ $hasAvail ? 'bg-success bg-opacity-25' : '' }} {{ $isPast ? 'text-muted' : 'avail-cell' }}"
                                @if($isInstructor && $inst->id === auth()->id() && !$isPast)
                                    role="button"
                                    data-date="{{ $dateStr }}"
                                    data-slot="evening"
                                    onclick="toggleAvail(this)"
                                    style="cursor:pointer"
                                @endif
                            >
                                @if($hasAvail)✅@endif
                            </td>
                        @endforeach
                    </tr>
                    @php $cursor->addDay(); @endphp
                @endwhile
            </tbody>
        </table>
    </div>

    <div class="mt-3 small text-muted">
        <span class="badge bg-primary bg-opacity-25 text-primary">🏊</span> = {{ __('Pool night (Mon/Wed/Fri)') }}
        &nbsp;|&nbsp; ✅ = {{ __('Available') }}
    </div>

    @if($isInstructor)
    <script>
    function toggleAvail(cell) {
        const date = cell.dataset.date;
        const slot = cell.dataset.slot;
        fetch('{{ route("availability.toggle") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
            body: JSON.stringify({date, slot})
        }).then(r => r.json()).then(d => {
            if (d.status === 'added') {
                cell.innerHTML = '✅';
                cell.classList.add('bg-success','bg-opacity-25');
            } else {
                cell.innerHTML = '';
                cell.classList.remove('bg-success','bg-opacity-25');
            }
        });
    }
    </script>
    @endif
</x-layout>
