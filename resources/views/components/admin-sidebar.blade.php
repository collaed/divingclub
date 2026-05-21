{{-- Admin sidebar navigation — replaces the mega dropdown on admin pages --}}
<nav class="dc-admin-sidebar">
    <div class="dc-admin-group">
        <div class="dc-admin-group-label">{{ __('People') }}</nav>
        <div class="list-group">
            <a href="{{ route('admin.dashboard.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.dashboard.*') ? 'active' : '' }}">@icon('📊') {{ __('Dashboard') }}</a>
            <a href="{{ route('admin.members.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.members.*') ? 'active' : '' }}">@icon('👥') {{ __('Members') }}</a>
            <a href="{{ route('admin.guardians.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.guardians.*') ? 'active' : '' }}">@icon('👨‍👧') {{ __('Minors') }}</a>
            <a href="{{ route('admin.trial-requests.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.trial-requests.*') ? 'active' : '' }}">@icon('🐠') {{ __('Trials') }}</a>
        </div>
    </div>
    <div class="dc-admin-group">
        <div class="dc-admin-group-label">{{ __('Finance') }}</div>
        <div class="list-group">
            <a href="{{ route('admin.seasons.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.seasons.*') ? 'active' : '' }}">@icon('📅') {{ __('Seasons') }}</a>
            <a href="{{ route('admin.payments.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">@icon('💶') {{ __('Payments') }}</a>
            @can('view finances')
            <a href="{{ route('admin.audit-finances') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.audit-finances') ? 'active' : '' }}">@icon('📋') {{ __('Audit') }}</a>
            @endcan
        </div>
    </div>
    <div class="dc-admin-group">
        <div class="dc-admin-group-label">{{ __('Content') }}</div>
        <div class="list-group">
            <a href="{{ route('admin.articles.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}">@icon('📝') {{ __('Articles') }}</a>
            <a href="{{ route('admin.library.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.library.*') ? 'active' : '' }}">@icon('📁') {{ __('Documents') }}</a>
            <a href="{{ route('admin.email.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.email.*') ? 'active' : '' }}">@icon('📧') {{ __('Email') }}</a>
            <a href="{{ route('admin.newsletters.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.newsletters.*') ? 'active' : '' }}">@icon('📬') {{ __('Newsletters') }}</a>
            <a href="{{ route('admin.votes.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.votes.*') ? 'active' : '' }}">@icon('🗳️') {{ __('Votes') }}</a>
        </div>
    </div>
    <div class="dc-admin-group">
        <div class="dc-admin-group-label">{{ __('Diving') }}</div>
        <div class="list-group">
            <a href="{{ route('admin.equipment.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.equipment.*') ? 'active' : '' }}">@icon('🤿') {{ __('Equipment') }}</a>
            <a href="{{ route('admin.dive-sites.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.dive-sites.*') ? 'active' : '' }}">@icon('🗺️') {{ __('Dive Sites') }}</a>
            <a href="{{ route('admin.partnerships.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.partnerships.*') ? 'active' : '' }}">@icon('🤝') {{ __('Partners') }}</a>
        </div>
    </div>
    <div class="dc-admin-group">
        <div class="dc-admin-group-label">{{ __('System') }}</div>
        <div class="list-group">
            <a href="{{ route('admin.settings.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">@icon('⚙️') {{ __('Settings') }}</a>
            <a href="{{ route('admin.roles.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">@icon('🔐') {{ __('Roles') }}</a>
            <a href="{{ route('admin.backups.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">@icon('💾') {{ __('Backups') }}</a>
            <a href="{{ route('admin.audit-logs.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">@icon('📜') {{ __('Audit Log') }}</a>
            <a href="{{ route('admin.guide.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('admin.guide.*') ? 'active' : '' }}">@icon('📖') {{ __('Guide') }}</a>
        </div>
    </div>
</nav>
