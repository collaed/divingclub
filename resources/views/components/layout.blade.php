<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $theme['primary_color'] ?? '#003366' }}">
    <title>{{ $title ?? ($theme['club_full_name'] ?? 'DivingClub') }}</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>{!! $themeCSS ?? '' !!}
    .dc-header{background:linear-gradient(135deg,var(--dc-header-start) 0%,var(--dc-primary) 40%,var(--dc-header-end) 100%) !important}
    .dc-brand-accent{color:var(--dc-accent) !important}
    .dc-footer{background:var(--dc-footer-bg) !important}
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    {{-- Impersonation banner --}}
    @if(session('impersonating'))
        <div class="dc-impersonation-banner">
            ⚠️ {{ __('Impersonating') }}: {{ session('impersonating_name') }}
            — <a href="{{ route('admin.stop-impersonation') }}" class="text-dark text-decoration-underline">{{ __('Stop') }}</a>
        </div>
    @endif

    {{-- Header --}}
    <header class="dc-header py-3" style="overflow:visible">
        <div class="dc-bubbles">
            @if(($theme['header_bubbles'] ?? '1') === '1')
                <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
                <div class="bubble"></div><div class="bubble"></div>
            @endif
        </div>
        <div class="container position-relative" style="z-index:1">
            <div class="d-flex justify-content-between align-items-center">
                <a href="/" class="dc-brand text-decoration-none">
                    {{ $theme['logo_emoji'] ?? '🤿' }} <span class="dc-brand-accent">{{ $theme['logo_accent_text'] ?? 'Diving' }}</span>{{ $theme['logo_plain_text'] ?? 'Club' }}
                </a>
                <div class="text-white d-flex align-items-center gap-3">
                    {{-- Language selector --}}
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle py-0 px-2" data-bs-toggle="dropdown" aria-label="Language">
                            {{ strtoupper(app()->getLocale()) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width:auto">
                            @foreach(['en'=>'English','fr'=>'Français','de'=>'Deutsch','lb'=>'Lëtzebuergesch','pt'=>'Português','it'=>'Italiano','nl'=>'Nederlands','es'=>'Español','pl'=>'Polski','hu'=>'Magyar','ro'=>'Română'] as $code => $label)
                                <li><a class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" href="{{ url('locale/' . $code) }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    @auth
                        {{ __('Welcome') }}, {{ auth()->user()->name }}
                    @endauth
                </div>
            </div>
        </div>
    </header>

    {{-- Navigation --}}
    <nav class="dc-navbar navbar navbar-expand-lg">
        <div class="container">
            <button class="navbar-toggler border-primary" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('/') ? 'active fw-bold' : '' }}" href="/">{{ __('Home') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dues.*') ? 'active fw-bold' : '' }}" href="{{ route('dues.show') }}">{{ __('Dues') }}</a>
                    </li>
                    {{-- Public info pages (visible to everyone) --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('article.*') && !auth()->check() ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('About') }}</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/article/schedule') }}">🗓️ {{ __('Training Schedule') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/values') }}">🤝 {{ __('Our Values') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/history') }}">🏛️ {{ __('Club History') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/bureau') }}">👥 {{ __('The Bureau') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/instructors') }}">🎓 {{ __('Our Instructors') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/member-figures') }}">📊 {{ __('Our Members') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/local') }}">🏠 {{ __('Our Warehouse') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/contact-info') }}">📬 {{ __('Contact & Social') }}</a></li>
                        </ul>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('events.*') ? 'active fw-bold' : '' }}" href="{{ route('events.index') }}">{{ __('Calendar') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('classifieds.*') ? 'active fw-bold' : '' }}" href="{{ route('classifieds.index') }}">🏷️ {{ __('Classifieds') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('buddies.*') ? 'active fw-bold' : '' }}" href="{{ route('buddies.index') }}">🤝 {{ __('Buddies') }}</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('members.*') || request()->routeIs('article.*') || request()->routeIs('documents.*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('Info') }}</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('members.directory') }}">{{ __('Members Directory') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('members.trombinoscope') }}">{{ __('Trombinoscope') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('documents.index') }}">📁 {{ __('Documents') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('contact') }}">{{ __('Contact Us') }}</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('profile.*') ? 'active fw-bold' : '' }}" href="{{ route('profile.show') }}">{{ __('My Profile') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('gdpr.*') ? 'active fw-bold' : '' }}" href="{{ route('gdpr.consents') }}">{{ __('Privacy') }}</a>
                        </li>
                        @if(auth()->user()->isBureau())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('Administration') }}</a>
                                <ul class="dropdown-menu">
                                    @if(auth()->user()->isBureauMaster())
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.members.index') }}">{{ __('Members') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.seasons.index') }}">{{ __('Seasons') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.payments.index') }}">{{ __('Payments') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.equipment.index') }}">{{ __('Equipment') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.email.index') }}">{{ __('Email') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.votes.index') }}">{{ __('Votes') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.articles.index') }}">{{ __('Articles') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.links.index') }}">{{ __('Links') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.library.index') }}">📁 {{ __('Document Library') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.dive-sites.index') }}">🤿 {{ __('Dive Sites') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.dive-group-rules.index') }}">📋 {{ __('Dive Group Rules') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.audit-logs.index') }}">{{ __('Audit Log') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}">{{ __('Settings') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.guide.index') }}">📖 {{ __('Admin Guide') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.annual-report') }}">📊 {{ __('Annual Report') }}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                    @endauth
                </ul>
                <ul class="navbar-nav">
                    @guest
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a></li>
                    @else
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link">{{ __('Logout') }}</button>
                            </form>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    {{-- Flash messages --}}
    <div class="container mt-3">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
    </div>

    {{-- Content --}}
    <main class="{{ $theme['layout_width'] ?? 'container-lg' }} my-4 flex-grow-1">
        {{ $slot }}
    </main>

    {{-- Cookie consent banner --}}
    @guest
        <div id="cookieBanner" class="position-fixed bottom-0 start-0 end-0 bg-dark text-white p-3 text-center" style="z-index:1060; display:none;">
            {{ __('This site uses cookies for session management.') }}
            <button class="btn btn-sm btn-primary ms-2" onclick="document.getElementById('cookieBanner').style.display='none'; document.cookie='cookie_consent=1;path=/;max-age=31536000'">{{ __('Accept') }}</button>
        </div>
        <script>if(!document.cookie.includes('cookie_consent'))document.getElementById('cookieBanner').style.display='block';</script>
    @endguest

    {{-- Footer --}}
    <footer class="dc-footer py-4 mt-auto">
        <div class="container text-center">
            <p class="mb-1">{{ $theme['logo_emoji'] ?? '🤿' }} {{ $theme['club_full_name'] ?? 'DivingClub' }} — {{ __('Diving Club Management System') }}</p>
            <p class="mb-0 small opacity-75">© {{ date('Y') }} — {{ __('Powered by Laravel') }}</p>
        </div>
    </footer>
</body>
</html>
