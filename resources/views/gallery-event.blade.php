{{-- Gallery event detail — grid of all photos + upload form | ClubCEP.eu --}}
<x-layout :title="$event->title . ' — ' . __('Photos')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">@icon('📸') {{ $event->title }}</h4>
        <a href="{{ route('gallery') }}" class="btn btn-sm btn-outline-secondary">← {{ __('Back') }}</a>
    </div>
    @if($event->event_date)
        <p class="text-muted small">{{ $event->event_date->translatedFormat('d F Y') }} · {{ $photos->count() }} {{ __('photos') }}</p>
    @endif

    @if($photos->count())
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-2">
            @foreach($photos as $photo)
                <div class="col">
                    <a href="{{ $photo->url }}" target="_blank">
                        <img src="{{ $photo->thumb_url }}" alt="{{ $photo->caption }}" loading="lazy"
                             class="w-100 rounded" style="height:140px;object-fit:cover">
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-muted">{{ __('No photos yet.') }}</p>
    @endif

    @auth
        <hr>
        <h6>@icon('📤') {{ __('Upload Photos') }}</h6>
        <form method="POST" action="{{ route('gallery.upload', $event) }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <input type="file" name="photos[]" class="form-control @error('photos.*') is-invalid @enderror" accept="image/*,video/mp4,video/quicktime,video/webm,video/x-msvideo,application/zip,application/x-zip-compressed,.zip,.mov,.mp4" multiple required>
                    @error('photos.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-muted">{{ __('Max 100MB per file. Photos, videos (MP4/MOV), or a ZIP archive.') }}</small>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
                </div>
            </div>
        </form>
    @endauth
</x-layout>
