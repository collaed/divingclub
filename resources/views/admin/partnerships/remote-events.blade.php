@extends('components.layout')
@section('content')
<div class="container py-4">
    <h2>Events from {{ $partnership->name }}</h2>
    <a href="{{ route('admin.partnerships.index') }}" class="btn btn-outline-secondary btn-sm mb-3">← Back</a>

    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    @forelse($events as $e)
    <div class="card mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <h6 class="mb-1">{{ $e['title'] }}</h6>
                    <small class="text-muted">{{ $e['event_date'] }} {{ $e['event_time'] ?? '' }} · {{ $e['location'] ?? 'TBD' }}</small>
                    @if($e['levels_display'])<br><small>Levels: {{ $e['levels_display'] }}</small>@endif
                    @if($e['estimated_cost'])<br><small>Cost: €{{ $e['estimated_cost'] }}</small>@endif
                </div>
                <div class="text-end">
                    <span class="badge bg-info">{{ $e['slots_taken'] }}/{{ $e['external_slots'] }} external slots</span>
                    <br><small>{{ $e['event_type'] }}</small>
                </div>
            </div>
            @if($e['description'])<p class="small mt-2 mb-0">{{ Str::limit($e['description'], 200) }}</p>@endif
        </div>
    </div>
    @empty
    <div class="alert alert-info">No federated events available from this partner.</div>
    @endforelse
</div>
@endsection
