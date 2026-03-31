<x-layout :title="__('Admin Guide')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">@icon('📖') {{ __('Administration Guide') }}</h4>
        <a href="{{ route('admin.dashboard.index') }}" class="btn btn-sm btn-outline-primary">← {{ __('Dashboard') }}</a>
    </div>

    <div class="row g-3">
        @foreach($sections as $slug => $title)
            <div class="col-md-4 col-lg-3">
                <a href="{{ route('admin.guide.show', $slug) }}" class="card dc-card text-decoration-none h-100">
                    <div class="card-body text-center">
                        <div class="fs-2 mb-2">
                            @switch($slug)
                                @case('overview') @icon('🏠') @break
                                @case('first-steps') @icon('🚀') @break
                                @case('members') @icon('👥') @break
                                @case('minors') @icon('👨‍👧') @break
                                @case('seasons-events') @icon('📅') @break
                                @case('dive-groups') @icon('🫧') @break
                                @case('medical') @icon('🏥') @break
                                @case('payments') @icon('💰') @break
                                @case('equipment') @icon('🤿') @break
                                @case('content') @icon('📰') @break
                                @case('email') @icon('✉️') @break
                                @case('voting') @icon('🗳️') @break
                                @case('partnerships') @icon('🤝') @break
                                @case('social-media') @icon('📱') @break
                                @case('gdpr') @icon('🔒') @break
                                @case('audit-log') @icon('📋') @break
                                @case('settings') @icon('⚙️') @break
                                @case('api-keys') @icon('🔑') @break
                                @case('backup') @icon('💾') @break
                                @case('newsletters') @icon('📬') @break
                                @case('system') @icon('🔄') @break
                                @case('troubleshooting') @icon('🔧') @break
                            @endswitch
                        </div>
                        <h6 class="card-title text-dark">{{ $title }}</h6>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</x-layout>
