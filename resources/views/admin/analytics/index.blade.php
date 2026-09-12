<x-admin-layout :title="__('Site analytics')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('📈') {{ __('Site analytics') }}</h4>
        @if($dashboardUrl)
            <a href="{{ $dashboardUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                {{ __('Open Umami') }} ↗
            </a>
        @endif
    </div>

    <p class="text-muted small">
        {{ __('Visitor traffic (all pages, anonymous included) — countries, browsers, referrers, popular pages. Bots and non-browser clients are not counted here.') }}
    </p>

    @if($shareUrl)
        <div class="ratio" style="--bs-aspect-ratio: 130%;">
            <iframe src="{{ $shareUrl }}" title="{{ __('Umami dashboard') }}" loading="lazy"
                    style="border:0;border-radius:var(--dc-radius,.375rem)" referrerpolicy="no-referrer"></iframe>
        </div>
    @else
        <div class="card dc-card">
            <div class="card-body">
                <p class="mb-2">{{ __('The embedded dashboard is not configured yet.') }}</p>
                <ol class="small text-muted mb-0">
                    <li>{{ __('In Umami, open the website → Settings → enable "Share URL".') }}</li>
                    <li>{{ __('Copy the share link and set it as UMAMI_SHARE_URL in the app config.') }}</li>
                </ol>
            </div>
        </div>
    @endif
</x-admin-layout>
