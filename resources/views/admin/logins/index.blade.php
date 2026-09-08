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
@endphp

<x-admin-layout :title="__('Login history')">
    <h4 class="mb-3">@icon('🔑') {{ __('Login history') }}</h4>

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
                                    {{ trim(($login->user->detail?->first_name ?? '') . ' ' . ($login->user->detail?->last_name ?? '')) ?: $login->user->username ?: $login->user->primary_email }}
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
</x-admin-layout>
