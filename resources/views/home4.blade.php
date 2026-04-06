<x-layout :title="__('Home')">
@php
    $primary = $theme['primary_color'] ?? '#003366';
    $name = $user->detail?->first_name ?? $user->name;
@endphp
<style>
.h4-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; }
.h4-tile { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem 1rem; border-radius: 12px; background: #fff; border: 1px solid #e8ecf0; text-decoration: none; color: #333; transition: transform .15s, box-shadow .15s; position: relative; min-height: 120px; }
.h4-tile:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.08); color: #333; }
.h4-tile-icon { font-size: 2rem; margin-bottom: .5rem; }
.h4-tile-label { font-size: .85rem; font-weight: 600; text-align: center; }
.h4-badge { position: absolute; top: 8px; right: 8px; background: #dc3545; color: #fff; border-radius: 50%; width: 22px; height: 22px; font-size: .7rem; display: flex; align-items: center; justify-content: center; font-weight: 700; }
.h4-badge-info { background: {{ $primary }}; }
.h4-section { margin-bottom: 1.5rem; }
.h4-section-title { font-size: .8rem; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: .75rem; font-weight: 600; }
.h4-event-row { display: flex; align-items: center; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid #f0f0f0; }
.h4-event-date { min-width: 50px; text-align: center; }
.h4-event-day { font-size: 1.4rem; font-weight: 800; line-height: 1; color: {{ $primary }}; }
.h4-event-month { font-size: .65rem; text-transform: uppercase; color: #999; }
.h4-article { display: flex; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: inherit; }
.h4-article:hover { background: #f8f9fa; }
.h4-article-img { width: 80px; height: 60px; border-radius: 6px; object-fit: cover; flex-shrink: 0; background: #eee; }
.h4-article-title { font-weight: 600; font-size: .9rem; margin-bottom: .2rem; }
.h4-article-teaser { font-size: .8rem; color: #777; line-height: 1.4; }
@media (max-width: 576px) { .h4-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; } .h4-tile { padding: 1rem .5rem; min-height: 100px; } }
</style>

{{-- Welcome --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">👋 {{ __('Welcome, :name', ['name' => $name]) }}</h4>
    <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">{{ __('Classic view') }}</a>
</div>

{{-- Tiles --}}
<div class="h4-section">
    <div class="h4-section-title">{{ __('Quick Actions') }}</div>
    <div class="h4-grid">
        {{-- Everyone --}}
        <a href="{{ route('events.index') }}" class="h4-tile">
            <span class="h4-tile-icon">📅</span>
            <span class="h4-tile-label">{{ __('Events') }}</span>
            @if($nextEvents->isNotEmpty())
                <span class="h4-badge h4-badge-info">{{ $nextEvents->count() }}</span>
            @endif
        </a>
        <a href="{{ route('profile.show') }}" class="h4-tile">
            <span class="h4-tile-icon">👤</span>
            <span class="h4-tile-label">{{ __('My Profile') }}</span>
        </a>
        <a href="{{ route('availability.index') }}" class="h4-tile" style="border-color:#00695c44">
            <span class="h4-tile-icon">🏊</span>
            <span class="h4-tile-label">{{ __('Instructor Calendar') }}</span>
        </a>
        <a href="{{ route('documents.index') }}" class="h4-tile">
            <span class="h4-tile-icon">📄</span>
            <span class="h4-tile-label">{{ __('Documents') }}</span>
        </a>
        <a href="{{ route('dues.show') }}" class="h4-tile">
            <span class="h4-tile-icon">💰</span>
            <span class="h4-tile-label">{{ __('Payments') }}</span>
        </a>
        <a href="{{ route('classifieds.index') }}" class="h4-tile">
            <span class="h4-tile-icon">📢</span>
            <span class="h4-tile-label">{{ __('Classifieds') }}</span>
        </a>

        {{-- Bureau only --}}
        @if($isBureau)
            <a href="{{ route('admin.dashboard') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">📋</span>
                <span class="h4-tile-label">{{ __('Worklist') }}</span>
                @if(($worklist['certs'] ?? 0) + ($worklist['ext_regs'] ?? 0) > 0)
                    <span class="h4-badge">{{ ($worklist['certs'] ?? 0) + ($worklist['ext_regs'] ?? 0) }}</span>
                @endif
            </a>
            <a href="{{ route('admin.members.index') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">👥</span>
                <span class="h4-tile-label">{{ __('Members') }}</span>
            </a>
            <a href="{{ route('admin.dive-sites.index') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">🤿</span>
                <span class="h4-tile-label">{{ __('Dive Sites') }}</span>
            </a>
            <a href="{{ route('admin.equipment.index') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">🔧</span>
                <span class="h4-tile-label">{{ __('Equipment') }}</span>
            </a>
            <a href="{{ route('admin.email.index') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">📧</span>
                <span class="h4-tile-label">{{ __('Send Email') }}</span>
            </a>
            <a href="{{ route('admin.newsletters.index') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">📬</span>
                <span class="h4-tile-label">{{ __('Newsletters') }}</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">💳</span>
                <span class="h4-tile-label">{{ __('Reconciliation') }}</span>
            </a>
            <a href="{{ route('admin.email-stats') }}" class="h4-tile" style="background:#fff8e1">
                <span class="h4-tile-icon">📊</span>
                <span class="h4-tile-label">{{ __('Email Stats') }}</span>
            </a>
        @endif
    </div>
</div>

<div class="row g-4">
    {{-- Next Up --}}
    <div class="col-md-7">
        <div class="h4-section">
            <div class="h4-section-title">
                @if($myRegs->isNotEmpty()) {{ __('My Upcoming Dives') }} @else {{ __('Next Events') }} @endif
            </div>
            <div class="card dc-card">
                <div class="card-body py-2">
                    @foreach(($myRegs->isNotEmpty() ? $myRegs : $nextEvents) as $ev)
                        <a href="{{ route('events.show', $ev) }}" class="h4-event-row text-decoration-none text-body">
                            <div class="h4-event-date">
                                <div class="h4-event-day">{{ $ev->event_date->format('d') }}</div>
                                <div class="h4-event-month">{{ $ev->event_date->translatedFormat('M') }}</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $ev->title }}</div>
                                @if($ev->location)<small class="text-muted">📍 {{ Str::limit($ev->location, 40) }}</small>@endif
                            </div>
                            <div>
                                @if($myRegs->contains('id', $ev->id))
                                    <span class="badge bg-success">✓ {{ __('Registered') }}</span>
                                @else
                                    <span class="badge bg-outline-secondary border">{{ ucfirst($ev->event_type) }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                    @if($myRegs->isEmpty() && $nextEvents->isEmpty())
                        <p class="text-muted py-3 mb-0 text-center">{{ __('No upcoming events') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Articles --}}
    <div class="col-md-5">
        <div class="h4-section">
            <div class="h4-section-title">{{ __('Recent Articles') }}</div>
            <div class="card dc-card">
                <div class="card-body py-2">
                    @foreach($articles as $art)
                        <a href="{{ route('article.show', $art->slug) }}" class="h4-article">
                            @if($art->featured_image)
                                <img src="{{ asset('storage/'.$art->featured_image) }}" class="h4-article-img" alt="">
                            @else
                                <div class="h4-article-img d-flex align-items-center justify-content-center text-muted">📰</div>
                            @endif
                            <div>
                                <div class="h4-article-title">{{ $art->title }}</div>
                                <div class="h4-article-teaser">{{ Str::limit(strip_tags($art->body), 80) }}</div>
                            </div>
                        </a>
                    @endforeach
                    @if($articles->isEmpty())
                        <p class="text-muted py-3 mb-0 text-center">{{ __('No articles yet') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</x-layout>
