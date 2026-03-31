<x-layout :title="__('Trombinoscope')">
    <h4 class="mb-3">{{ __('Trombinoscope') }}</h4>
    <div class="row g-3">
        @unless($viewerHasPhoto)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('profile.show', ['tab' => 'info']) }}" class="text-decoration-none">
                <div class="card dc-card text-center h-100 border-primary">
                    <div class="card-body py-3 px-2">
                        <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mb-2 photo-cta" style="width:80px;height:80px">
                            <span class="text-white" style="font-size:1.8rem">📷</span>
                        </div>
                        <div class="small fw-bold text-primary">{{ __('Your photo could be here!') }}</div>
                    </div>
                </div>
                </a>
            </div>
            <style>.photo-cta { animation: pulse 2s ease-in-out infinite; } @keyframes pulse { 0%,100% { transform:scale(1); } 50% { transform:scale(1.08); } }</style>
        @endunless
        @foreach($members as $m)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('members.profile', $m) }}" class="text-decoration-none">
                <div class="card dc-card text-center h-100">
                    <div class="card-body py-3 px-2">
                        <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;" loading="lazy">
                        <div class="small fw-bold text-body">{{ $m->detail?->first_name }}</div>
                        <div class="small text-muted">{{ $m->detail?->last_name }}</div>
                        @php $cert = $m->primaryCertification(); @endphp
                        @if($cert)
                            <span class="badge bg-primary mt-1" style="font-size:0.65rem;">{{ $cert->code }}</span>
                        @endif
                    </div>
                </div>
                </a>
            </div>
        @endforeach
    </div>
</x-layout>
