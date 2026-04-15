{{-- Admin layout with sidebar --}}
<x-layout :title="$title ?? __('Administration')">
    <div class="row">
        <div class="col-lg-2 d-none d-lg-block">
            <x-admin-sidebar />
        </div>
        <div class="col-lg-10">
            {{-- Mobile sidebar (collapsible) --}}
            <div class="d-lg-none mb-3">
                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavMobile">
                    @icon('☰') {{ __('Admin Menu') }}
                </button>
                <div class="collapse mt-2" id="adminNavMobile">
                    <x-admin-sidebar />
                </div>
            </div>
            {{ $slot }}
        </div>
    </div>
</x-layout>
