<x-layout :title="__('Admin Guide')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📖 {{ __('Administration Guide') }}</h4>
        <a href="{{ route('admin.dashboard.index') }}" class="btn btn-sm btn-outline-primary">← {{ __('Dashboard') }}</a>
    </div>

    <div class="row g-3">
        @foreach($sections as $slug => $title)
            <div class="col-md-4 col-lg-3">
                <a href="{{ route('admin.guide.show', $slug) }}" class="card dc-card text-decoration-none h-100">
                    <div class="card-body text-center">
                        <div class="fs-2 mb-2">
                            @switch($slug)
                                @case('overview') 🏠 @break
                                @case('first-steps') 🚀 @break
                                @case('members') 👥 @break
                                @case('seasons-events') 📅 @break
                                @case('medical') 🏥 @break
                                @case('payments') 💰 @break
                                @case('equipment') 🤿 @break
                                @case('email') ✉️ @break
                                @case('voting') 🗳️ @break
                                @case('gdpr') 🔒 @break
                                @case('settings') ⚙️ @break
                                @case('api-keys') 🔑 @break
                                @case('backup') 💾 @break
                                @case('troubleshooting') 🔧 @break
                            @endswitch
                        </div>
                        <h6 class="card-title text-dark">{{ $title }}</h6>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</x-layout>
