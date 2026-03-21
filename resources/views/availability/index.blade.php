<x-layout :title="__('Instructor Availability')">
    @php
        $actColors = $colors;
        $dow = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">@icon('📅') {{ __('Instructor Planning') }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('availability.index', ['month' => $start->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary">←</a>
            <span class="btn btn-sm btn-primary disabled">{{ $start->translatedFormat('F Y') }}</span>
            <a href="{{ route('availability.index', ['month' => $start->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary">→</a>
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-3 d-flex flex-wrap gap-2 small">
        @foreach($actColors as $key => $c)
            <span class="badge px-2 py-1" style="background:{{ $c['color'] }};color:{{ $c['text'] }}">{{ $c['icon'] }} {{ __($c['label']) }}</span>
        @endforeach
        <span class="badge px-2 py-1 bg-danger text-white">@icon('⚠️') {{ __('No instructor') }}</span>
    </div>

    @if($isInstructor)
        <div class="alert alert-info small py-2 mb-3">
            @icon('💡') {{ __('Click a day cell, pick an activity type, and your initial will appear. Click your initial to remove.') }}
        </div>
    @endif

    {{-- Calendar grid: weekly rows, day columns --}}
    <div class="table-responsive">
        <table class="table table-bordered table-sm text-center align-middle" style="font-size:.85rem">
            <thead class="table-dark">
                <tr>
                    <th style="width:30px">{{ __('Wk') }}</th>
                    @foreach($dow as $d)
                        <th>{{ __($d) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    // Start from Monday of the week containing the 1st
                    $cursor = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                    $endWeek = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                @endphp
                @while($cursor->lte($endWeek))
                    <tr>
                        <td class="text-muted small fw-bold">{{ $cursor->weekOfYear }}</td>
                        @for($d = 0; $d < 7; $d++)
                            @php
                                $day = $cursor->copy()->addDays($d);
                                $dateStr = $day->format('Y-m-d');
                                $inMonth = $day->month === $start->month;
                                $isPast = $day->lt(today());
                                $dayAvails = $availabilities[$dateStr] ?? collect();
                                $dayEvents = $events[$dateStr] ?? collect();
                                $isWeekend = $day->isWeekend();
                            @endphp
                            <td class="{{ !$inMonth ? 'text-muted bg-light' : '' }} {{ $isWeekend && $inMonth ? 'bg-light' : '' }} {{ $day->isToday() ? 'border-primary border-2' : '' }}" style="vertical-align:top;min-width:90px;height:60px">
                                @if($inMonth)
                                    <div class="fw-bold small {{ $isPast ? 'text-muted' : '' }}">{{ $day->format('d') }}</div>
                                    {{-- Show events for this day --}}
                                    @foreach($dayEvents as $ev)
                                        <div class="badge text-truncate d-block mb-1" style="max-width:100%;background:{{ $ev->color_hex ?? '#6c757d' }};font-size:.65rem" title="{{ $ev->title }}">{{ Str::limit($ev->title, 12) }}</div>
                                    @endforeach
                                    {{-- Show instructor availability grouped by activity --}}
                                    @php $byActivity = $dayAvails->groupBy('activity_type'); @endphp
                                    @foreach($byActivity as $actType => $avails)
                                        @php $ac = $actColors[$actType] ?? $actColors['pool']; @endphp
                                        <div class="d-inline-block px-1 rounded mb-1" style="background:{{ $ac['color'] }};color:{{ $ac['text'] }};font-size:.7rem;cursor:default" title="{{ __($ac['label']) }}">
                                            @foreach($avails as $av)
                                                <span class="fw-bold avail-initial" title="{{ $av->user->detail?->first_name }} {{ $av->user->detail?->last_name }}"
                                                    @if($isInstructor && $av->user_id === auth()->id() && !$isPast)
                                                        style="cursor:pointer;text-decoration:underline"
                                                        onclick="removeAvail('{{ $dateStr }}','{{ $av->slot }}','{{ $actType }}')"
                                                    @endif
                                                >{{ mb_strtoupper(mb_substr($av->user->detail?->first_name ?? '?', 0, 1)) }}</span>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    {{-- Add button for instructors --}}
                                    @if($isInstructor && !$isPast)
                                        <div class="mt-1">
                                            <button class="btn btn-outline-secondary border-0 p-0" style="font-size:.6rem;line-height:1" onclick="showPicker('{{ $dateStr }}')" title="{{ __('Add availability') }}">＋</button>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        @endfor
                    </tr>
                    @php $cursor->addWeek(); @endphp
                @endwhile
            </tbody>
        </table>
    </div>

    {{-- Instructor initials legend --}}
    <div class="mt-2 small text-muted">
        <strong>{{ __('Instructors') }}:</strong>
        @foreach($instructors as $inst)
            <span class="badge bg-secondary me-1">{{ mb_strtoupper(mb_substr($inst->detail?->first_name ?? '?', 0, 1)) }} = {{ $inst->detail?->first_name }}</span>
        @endforeach
    </div>

    {{-- Activity picker modal --}}
    <div class="modal fade" id="activityPicker" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2"><h6 class="modal-title">{{ __('Pick activity') }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body d-flex flex-wrap gap-2 justify-content-center">
                    @foreach($actColors as $key => $c)
                        <button class="btn btn-sm px-2" style="background:{{ $c['color'] }};color:{{ $c['text'] }}" onclick="addAvail('{{ $key }}')">{{ $c['icon'] }} {{ __($c['label']) }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if($isInstructor)
    <script>
    let pickerDate = null;
    const pickerModal = new bootstrap.Modal(document.getElementById('activityPicker'));

    function showPicker(date) {
        pickerDate = date;
        pickerModal.show();
    }

    function addAvail(activityType) {
        pickerModal.hide();
        fetch('{{ route("availability.toggle") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
            body: JSON.stringify({date: pickerDate, slot: 'evening', activity_type: activityType})
        }).then(r => r.json()).then(() => location.reload());
    }

    function removeAvail(date, slot, activityType) {
        if (!confirm('{{ __("Remove your availability?") }}')) return;
        fetch('{{ route("availability.toggle") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
            body: JSON.stringify({date, slot, activity_type: activityType})
        }).then(r => r.json()).then(() => location.reload());
    }
    </script>
    @endif
</x-layout>
