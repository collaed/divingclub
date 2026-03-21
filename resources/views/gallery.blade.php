{{-- Photo gallery — all approved event photos with Ken Burns slideshows | ClubCEP.eu --}}
<x-layout :title="__('Photo Gallery')">
    <h4 class="mb-4">@icon('📸') {{ __('Photo Gallery') }}</h4>

    @forelse($photos as $eventTitle => $eventPhotos)
        <div class="card dc-card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span>{{ $eventTitle }}</span>
                <span class="badge bg-secondary">{{ $eventPhotos->count() }} {{ __('photos') }}</span>
            </div>
            <div class="card-body p-0">
                <x-slideshow :photos="$eventPhotos" height="350px" :interval="5000" :rounded="false" />
            </div>
        </div>
    @empty
        <div class="card dc-card">
            <div class="card-body text-center py-5 text-muted">
                @icon('📷') {{ __('No photos yet. Photos will appear here after events.') }}
            </div>
        </div>
    @endforelse
</x-layout>
