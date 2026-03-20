<x-layout :title="__('Trombinoscope')">
    <h4 class="mb-3">{{ __('Trombinoscope') }}</h4>
    <div class="row g-3">
        @foreach($members as $m)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('admin.profile.show', $m) }}" class="text-decoration-none">
                <div class="card dc-card text-center h-100">
                    <div class="card-body py-3 px-2">
                        @if($m->detail?->avatar_path)
                            <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                        @else
                            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-2" style="width:80px;height:80px;">
                                <span class="text-white" style="font-size:1.5rem;">{{ strtoupper(substr($m->detail?->first_name ?? '?', 0, 1) . substr($m->detail?->last_name ?? '', 0, 1)) }}</span>
                            </div>
                        @endif
                        <div class="small fw-bold text-body">{{ $m->detail?->first_name }}</div>
                        <div class="small text-muted">{{ $m->detail?->last_name }}</div>
                        @if($m->detail?->certification_level)
                            <span class="badge bg-primary mt-1" style="font-size:0.65rem;">{{ $m->detail->certification_level }}</span>
                        @endif
                    </div>
                </div>
                </a>
            </div>
        @endforeach
    </div>
</x-layout>
