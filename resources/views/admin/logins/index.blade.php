@php
    $ua = function (?string $s): string {
        if (! $s) {
            return '—';
        }
        $os = match (true) {
            str_contains($s, 'iPhone'), str_contains($s, 'iPad') => 'iOS',
            str_contains($s, 'Android') => 'Android',
            str_contains($s, 'Mac OS X') => 'macOS',
            str_contains($s, 'Windows') => 'Windows',
            str_contains($s, 'Linux') => 'Linux',
            default => '?',
        };
        $browser = match (true) {
            str_contains($s, 'Edg/') => 'Edge',
            str_contains($s, 'OPR/'), str_contains($s, 'Opera') => 'Opera',
            str_contains($s, 'Firefox/') => 'Firefox',
            str_contains($s, 'Chrome/') => 'Chrome',
            str_contains($s, 'Safari/') => 'Safari',
            default => 'other',
        };

        return "{$browser} · {$os}";
    };

    $memberName = function ($user): string {
        if (! $user) {
            return __('deleted user');
        }

        return trim(($user->detail?->first_name ?? '').' '.($user->detail?->last_name ?? ''))
            ?: ($user->username ?: $user->primary_email);
    };

    $statusClass = fn (?int $s): string => match (true) {
        $s === null => 'text-muted',
        $s >= 500 => 'text-danger fw-bold',
        $s >= 400 => 'text-warning',
        $s >= 300 => 'text-info',
        default => 'text-muted',
    };

    $tabs = ['logins', 'members', 'activity'];
    $tab = in_array(request()->query('tab'), $tabs, true) ? request()->query('tab') : 'logins';
@endphp

<x-admin-layout :title="__('Login history')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('🔑') {{ __('Login history') }}</h4>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="auto-refresh">
            <label class="form-check-label small" for="auto-refresh">{{ __('Auto-refresh (15s)') }}</label>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tab === 'logins' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-logins" data-tab="logins" type="button" role="tab">
                {{ __('Logins') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tab === 'members' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-members" data-tab="members" type="button" role="tab">
                {{ __('Members') }} <span class="badge bg-secondary">{{ $members->count() }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tab === 'activity' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-activity" data-tab="activity" type="button" role="tab">
                {{ __('Activity') }}
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ─────────────────────────── Logins ─────────────────────────── --}}
        <div class="tab-pane fade {{ $tab === 'logins' ? 'show active' : '' }}" id="tab-logins" role="tabpanel">
            <div class="d-flex flex-wrap gap-3 mb-4">
                <span class="badge bg-primary fs-6">{{ $stats['logins_24h'] }} {{ __('logins (24h)') }}</span>
                <span class="badge bg-secondary fs-6">{{ $stats['users_24h'] }} {{ __('distinct members (24h)') }}</span>
                @if($failed->isNotEmpty())
                    <span class="badge bg-warning text-dark fs-6">{{ $failed->count() }} {{ __('failed attempts (24h)') }}</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('When') }}</th>
                            <th>{{ __('IP') }}</th>
                            <th>{{ __('Device') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logins as $login)
                            <tr>
                                <td>
                                    @if($login->user)
                                        <a href="{{ route('admin.profile.show', $login->user) }}" class="text-decoration-none text-body">
                                            {{ $memberName($login->user) }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ __('deleted user') }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap" title="{{ $login->created_at?->format('Y-m-d H:i:s') }}">
                                    {{ $login->created_at?->diffForHumans() }}
                                </td>
                                <td class="text-nowrap"><code>{{ $login->ip_address ?? '—' }}</code></td>
                                <td class="text-nowrap small" title="{{ $login->user_agent }}">{{ $ua($login->user_agent) }}</td>
                                <td>@if($login->remember)<span class="badge bg-light text-muted" title="{{ __('Remember me') }}">🔒</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-4">{{ __('No logins recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logins->links() }}

            @if($failed->isNotEmpty())
                <h5 class="mt-4">@icon('⚠️') {{ __('Failed attempts (last 24h)') }}</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>{{ __('Identifier') }}</th><th>{{ __('IP') }}</th><th>{{ __('When') }}</th></tr></thead>
                        <tbody>
                            @foreach($failed as $f)
                                <tr>
                                    <td class="cell-truncate" title="{{ $f->email }}">{{ $f->email }}</td>
                                    <td><code>{{ $f->ip_address ?? '—' }}</code></td>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($f->attempted_at)->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ─────────────────────────── Members ─────────────────────────── --}}
        <div class="tab-pane fade {{ $tab === 'members' ? 'show active' : '' }}" id="tab-members" role="tabpanel">
            <p class="text-muted small">
                {{ __('Last activity per member (any authenticated page load, not just logins).') }}
                @if($neverSeen > 0)
                    <span class="ms-2">{{ trans_choice(':count member never seen since tracking started|:count members never seen since tracking started', $neverSeen, ['count' => $neverSeen]) }}</span>
                @endif
            </p>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('Last seen') }}</th>
                            <th>{{ __('Last login') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.profile.show', $member) }}" class="text-decoration-none text-body">{{ $memberName($member) }}</a>
                                    @if($member->last_seen_at?->gt(now()->subMinutes(15)))
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">{{ __('online') }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap" title="{{ $member->last_seen_at?->format('Y-m-d H:i:s') }}">{{ $member->last_seen_at?->diffForHumans() }}</td>
                                <td class="text-nowrap text-muted" title="{{ $member->last_login_at ? \Illuminate\Support\Carbon::parse($member->last_login_at)->format('Y-m-d H:i:s') : '' }}">
                                    {{ $member->last_login_at ? \Illuminate\Support\Carbon::parse($member->last_login_at)->diffForHumans() : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-4">{{ __('No activity recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ─────────────────────────── Activity ─────────────────────────── --}}
        <div class="tab-pane fade {{ $tab === 'activity' ? 'show active' : '' }}" id="tab-activity" role="tabpanel">
            @unless($activityEnabled)
                <div class="alert alert-secondary">
                    {{ __('Page-visit logging is disabled. Set TRACKING_PAGE_VISITS=true to enable it.') }}
                </div>
            @else
                <p class="text-muted small">
                    {{ trans_choice('Page views by logged-in members over the last :count day.|Page views by logged-in members over the last :count days.', $retentionDays, ['count' => $retentionDays]) }}
                </p>

                @if($trailUser)
                    <a href="{{ route('admin.logins.index', ['tab' => 'activity']) }}" class="btn btn-sm btn-outline-secondary mb-3">← {{ __('Back to recent connections') }}</a>
                    <h5 class="mb-3">@icon('👣') {{ $memberName($trailUser) }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Page') }}</th><th>{{ __('Route') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse($trail as $visit)
                                    <tr>
                                        <td class="text-nowrap small" title="{{ $visit->created_at?->format('Y-m-d H:i:s') }}">{{ $visit->created_at?->diffForHumans() }}</td>
                                        <td class="small"><code>{{ $visit->path }}</code></td>
                                        <td class="small text-muted">{{ $visit->route_name ?? '—' }}</td>
                                        <td class="small {{ $statusClass($visit->status) }}">{{ $visit->status ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted text-center py-4">{{ __('No page views in the retention window.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Member') }}</th>
                                    <th>{{ __('Last seen') }}</th>
                                    <th>{{ __('Since') }}</th>
                                    <th class="text-end">{{ __('Pages') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($connections as $conn)
                                    @php($cu = $conn->user_id ? $connectionUsers->get($conn->user_id) : null)
                                    <tr>
                                        <td>{{ $cu ? $memberName($cu) : __('deleted user') }}</td>
                                        <td class="text-nowrap" title="{{ \Illuminate\Support\Carbon::parse($conn->last_at)->format('Y-m-d H:i:s') }}">{{ \Illuminate\Support\Carbon::parse($conn->last_at)->diffForHumans() }}</td>
                                        <td class="text-nowrap text-muted small">{{ \Illuminate\Support\Carbon::parse($conn->first_at)->diffForHumans() }}</td>
                                        <td class="text-end">{{ $conn->hits }}</td>
                                        <td class="text-end">
                                            @if($conn->user_id)
                                                <a href="{{ route('admin.logins.index', ['tab' => 'activity', 'user' => $conn->user_id]) }}" class="btn btn-sm btn-outline-primary py-0">{{ __('View trail') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted text-center py-4">{{ __('No page views recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            @endunless
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            // ── Keep the active tab in the URL (so deep-links and auto-refresh land on the same pane)
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
                btn.addEventListener('shown.bs.tab', function () {
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', btn.dataset.tab);
                    if (btn.dataset.tab !== 'activity') { url.searchParams.delete('user'); }
                    window.history.replaceState({}, '', url);
                });
            });

            // ── Opt-in auto-refresh every 15s, remembered per browser
            var KEY = 'dc.logins.autorefresh';
            var box = document.getElementById('auto-refresh');
            if (!box) { return; }
            var enabled = false;
            try { enabled = localStorage.getItem(KEY) === '1'; } catch (e) {}
            box.checked = enabled;
            box.addEventListener('change', function () {
                try { localStorage.setItem(KEY, box.checked ? '1' : '0'); } catch (e) {}
                if (box.checked) { schedule(); }
            });
            function schedule() {
                setTimeout(function () {
                    var still = false;
                    try { still = localStorage.getItem(KEY) === '1'; } catch (e) {}
                    if (still && !document.hidden) { window.location.reload(); }
                    else if (still) { schedule(); }
                }, 15000);
            }
            if (enabled) { schedule(); }
        })();
    </script>
    @endpush
</x-admin-layout>
