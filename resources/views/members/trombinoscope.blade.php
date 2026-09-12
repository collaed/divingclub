<x-layout :title="__('Trombinoscope')">
    <h4 class="mb-3">{{ __('Trombinoscope') }}</h4>
    <div class="row g-2">
        @unless($viewerHasPhoto)
            <div class="col-4 col-sm-3 col-md-2 col-lg-auto">
                <a href="{{ route('profile.show', ['tab' => 'info']) }}" class="text-decoration-none">
                <div class="card dc-card text-center h-100 border-primary" style="width:120px">
                    <div class="card-body p-2">
                        <div class="rounded bg-primary d-inline-flex align-items-center justify-content-center mb-1 photo-cta" style="width:80px;height:80px">
                            <span class="text-white" style="font-size:1.8rem">📷</span>
                        </div>
                        <div class="text-body" style="font-size:0.7rem;line-height:1.2;font-weight:600">{{ __('Add photo') }}</div>
                    </div>
                </div>
                </a>
            </div>
            <style>.photo-cta { animation: pulse 2s ease-in-out infinite; } @keyframes pulse { 0%,100% { transform:scale(1); } 50% { transform:scale(1.08); } }</style>
        @endunless
        @foreach($members as $m)
            <div class="col-4 col-sm-3 col-md-2 col-lg-auto">
                <a href="{{ route('members.profile', $m) }}" class="text-decoration-none">
                <div class="card dc-card text-center h-100" style="width:120px">
                    <div class="card-body p-2">
                        <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded mb-1" style="width:80px;height:80px;object-fit:cover;" loading="lazy">
                        <div class="text-body" style="font-size:0.7rem;line-height:1.2;font-weight:600">{{ $m->detail?->first_name }}</div>
                        @php $cert = $m->primaryCertification(); @endphp
                        @if($cert)
                            <div class="text-muted" style="font-size:0.65rem">{{ $cert->code }}</div>
                        @else
                            <div class="text-muted" style="font-size:0.65rem">{{ $m->detail?->last_name }}</div>
                        @endif
                    </div>
                </div>
                </a>
            </div>
        @endforeach
    </div>
</x-layout>
