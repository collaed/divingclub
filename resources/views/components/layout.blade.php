<!DOCTYPE html>
<!-- Powered by DivingClub-Manager — https://github.com/collaed/divingclub -->
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $theme['primary_color'] ?? '#003366' }}">
    <title>{{ $title ?? ($theme['club_full_name'] ?? 'DivingClub') }}</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/images/club-logo.png" type="image/png">
    <meta name="generator" content="DivingClub-Manager/1.0">
    <meta name="description" content="{{ $metaDescription ?? __('Diving club management — events, certifications, equipment, and more.') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>{!! $themeCSS ?? '' !!}
    .dc-header{background:linear-gradient(135deg,var(--dc-header-start) 0%,var(--dc-primary) 40%,var(--dc-header-end) 100%) !important}
    .dc-brand-accent{color:var(--dc-accent) !important}
    .dc-footer{background:var(--dc-footer-bg) !important}
    </style>
    @stack('styles')
    @if(config('services.umami.url') && config('services.umami.id'))
        <script defer src="{{ config('services.umami.url') }}/dive" data-website-id="{{ config('services.umami.id') }}"></script>
    @endif
</head>
<body class="d-flex flex-column min-vh-100">

    {{-- Impersonation banner --}}
    @if(session('impersonating'))
        <div class="dc-impersonation-banner">
            @icon('⚠️') {{ __('Impersonating') }}: {{ session('impersonating_name') }}
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
                <a href="/" class="dc-brand text-decoration-none d-flex align-items-center gap-2">
                    <img src="/images/club-logo.png" alt="" height="36" class="d-inline-block">
                    <span>{{ $theme['club_full_name'] ?? 'DivingClub' }}</span>
                </a>
                <div class="text-white d-flex align-items-center gap-3">
                    {{-- Dark mode toggle --}}
                    <button class="dc-dark-toggle" onclick="toggleDarkMode()" title="{{ __('Toggle dark mode') }}" id="darkToggle">🌙</button>
                    {{-- Font size --}}
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-light py-0 px-1" onclick="setFontSize(-1)" title="{{ __('Smaller text') }}">A-</button>
                        <button class="btn btn-outline-light py-0 px-1" onclick="setFontSize(1)" title="{{ __('Larger text') }}">A+</button>
                    </div>
                    {{-- Language selector --}}
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle py-0 px-2" data-bs-toggle="dropdown" aria-label="Language">
                            {{ strtoupper(app()->getLocale()) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width:auto;max-height:70vh;overflow-y:auto">
                            @foreach(\App\Http\Middleware\SetLocale::enabledLocalesWithLabels() as $code => $label)
                                <li><a class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" href="{{ url('locale/' . $code) }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    @auth
                        <a href="{{ route('profile.show') }}" class="text-white text-decoration-none">{{ auth()->user()->name }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    {{-- Navigation --}}
    @if(config('app.staging_mode'))
        <div class="bg-warning text-dark text-center py-1 small fw-bold">
            @icon('⚠️') STAGING MODE — Emails captured <a href="{{ route('staging.mail.index') }}" class="text-dark">@icon('📬') View Mailbox</a>
        </div>
    @endif
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

                    {{-- About — always visible --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('article.*') && !request()->routeIs('article.schedule') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('About') }}</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/article/values') }}">@icon('🤝') {{ __('Our Values') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/history') }}">@icon('🏛️') {{ __('Club History') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/bureau') }}">@icon('👥') {{ __('The Bureau') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/instructors') }}">@icon('🎓') {{ __('Our Instructors') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/member-figures') }}">@icon('📊') {{ __('Our Members') }}</a></li>
                            <li><a class="dropdown-item" href="{{ url('/article/contact-info') }}">@icon('📬') {{ __('Contact & Social') }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('dues.show') }}">@icon('💶') {{ __('Membership Fees') }}</a></li>
                        </ul>
                    </li>

                    @if(!auth()->check() || !auth()->user()->detail?->certification_level)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('trial.*') ? 'active fw-bold' : '' }}" href="{{ route('trial.show') }}">@icon('🐠') {{ __('Try Diving') }}</a>
                    </li>
                    @endif

                    @auth
                        {{-- Calendar --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('events.*') || request()->routeIs('availability.*') || request()->is('article/training-schedule-*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('Calendar') }}</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('events.index') }}">@icon('📆') {{ __('Events') }}</a></li>
                                <li><a class="dropdown-item" href="{{ url('/article/training-schedule-ULO7R') }}">@icon('🗓️') {{ __('Training Schedule') }}</a></li>
                                @if(auth()->user()->isBureau() || auth()->user()->hasRole('instructor'))
                                    <li><a class="dropdown-item" href="{{ route('availability.index') }}">@icon('📅') {{ __('Instructor Availability') }}</a></li>
                                @endif
                            </ul>
                        </li>

                        {{-- Members --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('members.*') || request()->routeIs('buddies.*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('Members') }}</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('members.directory') }}">@icon('📇') {{ __('Directory') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('members.trombinoscope') }}">@icon('📸') {{ __('Trombinoscope') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('buddies.index') }}">@icon('🤝') {{ __('Buddies') }}</a></li>
                            </ul>
                        </li>

                        {{-- Resources --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('documents.*') || request()->routeIs('gallery') || request()->routeIs('classifieds.*') || request()->routeIs('dues.*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('Resources') }}</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ url('/article/first-certification') }}">@icon('🎓') {{ __('First Certification') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('documents.index') }}">@icon('📁') {{ __('Documents') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('gallery') }}">@icon('📸') {{ __('Photo Gallery') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('classifieds.index') }}">@icon('🏷️') {{ __('Classifieds') }}</a></li>
                                <li><a class="dropdown-item" href="{{ url('/article/local') }}">@icon('🏠') {{ __('Our Warehouse') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('dues.show') }}">@icon('💶') {{ __('Membership Fees') }}</a></li>
                            </ul>
                        </li>

                        {{-- My Account --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('profile.*') || request()->routeIs('gdpr.*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('My Account') }}</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('profile.show') }}">@icon('👤') {{ __('My Profile') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('gdpr.consents') }}">@icon('🔒') {{ __('Privacy') }}</a></li>
                                <li><a class="dropdown-item" href="{{ route('contact') }}">@icon('📬') {{ __('Contact Us') }}</a></li>
                            </ul>
                        </li>

                        {{-- Administration --}}
                        @if(auth()->user()->isBureau())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.*') ? 'active fw-bold' : '' }}" href="#" data-bs-toggle="dropdown">{{ __('Admin') }}</a>
                                <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard.index') }}">@icon('📊') {{ __('Dashboard') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        {{-- People --}}
                                        <li><a class="dropdown-item" href="{{ route('admin.members.index') }}">{{ __('Members') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.guardians.index') }}">@icon('👨‍👧') {{ __('Minors & Consent') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.trial-requests.index') }}">@icon('🐠') {{ __('Trial Requests') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        {{-- Finance --}}
                                        <li><a class="dropdown-item" href="{{ route('admin.seasons.index') }}">{{ __('Seasons') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.payments.index') }}">{{ __('Payments') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        {{-- Content --}}
                                        <li><a class="dropdown-item" href="{{ route('admin.articles.index') }}">{{ __('Articles') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.links.index') }}">{{ __('Links') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.library.index') }}">@icon('📁') {{ __('Documents') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.email.index') }}">{{ __('Email') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.newsletters.index') }}">📬 {{ __('Newsletters') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.email-stats') }}">📊 {{ __('Email Stats') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.votes.index') }}">{{ __('Votes') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        {{-- Diving --}}
                                        <li><a class="dropdown-item" href="{{ route('admin.equipment.index') }}">{{ __('Equipment') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.dive-sites.index') }}">@icon('🤿') {{ __('Dive Sites') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.dive-group-rules.index') }}">@icon('📋') {{ __('Dive Group Rules') }}</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        {{-- System --}}
                                        <li><a class="dropdown-item" href="{{ route('admin.audit-logs.index') }}">{{ __('Audit Log') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.backups.index') }}">@icon('💾') {{ __('Backups') }}</a></li>
                                        <li><a class="dropdown-item" href="/horizon" target="_blank">⏱️ {{ __('Queue Monitor') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}">@icon('⚙️') {{ __('Settings') }}</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.roles.index') }}">🔐 {{ __('Roles & Permissions') }}</a></li>                                        <li><a class="dropdown-item" href="{{ route('admin.guide.index') }}">@icon('📖') {{ __('Admin Guide') }}</a></li>
                                        @can('view finances')
                                        <li><a class="dropdown-item" href="{{ route('admin.audit-finances') }}">📋 {{ __('Financial Audit') }}</a></li>
                                        @endcan                                        <li><a class="dropdown-item" href="{{ route('admin.annual-report') }}">@icon('📊') {{ __('Annual Report') }}</a></li>
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
                        <li class="nav-item d-flex align-items-center gap-2">
                            <a href="{{ route('profile.show') }}" class="dc-user-pill">
                                @if(auth()->user()->detail?->avatar_path)
                                    <img src="{{ asset('storage/' . auth()->user()->detail->avatar_path) }}" alt="" class="dc-user-avatar">
                                @else
                                    <span class="dc-user-initials">{{ strtoupper(substr(auth()->user()->detail?->first_name ?? '?', 0, 1) . substr(auth()->user()->detail?->last_name ?? '', 0, 1)) }}</span>
                                @endif
                                {{ auth()->user()->detail?->first_name ?? auth()->user()->username }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2" title="{{ __('Logout') }}">@icon('🚪')</button>
                            </form>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    {{-- Flash messages --}}
    <div class="container mt-3">
        {{-- Pending social link confirmation --}}
        @auth
            @if(session('pending_social_link'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    @icon('🔗') {{ __('A :provider account wants to link to your profile.', ['provider' => ucfirst(session('pending_social_link.provider'))]) }}
                    <form method="POST" action="{{ route('auth.social.confirm-link') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success ms-2">{{ __('Confirm Link') }}</button>
                    </form>
                    <form method="POST" action="{{ route('auth.social.dismiss-link') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-1">{{ __('Dismiss') }}</button>
                    </form>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Profile completeness banner --}}
            @if(!auth()->user()->hasDiveProfile())
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    @icon('📋') {{ __('Complete your profile to register for dives:') }}
                    <strong>{{ implode(', ', auth()->user()->missingDiveProfileFields()) }}</strong>
                    — <a href="{{ route('profile.show') }}" class="alert-link">{{ __('Edit Profile') }}</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        @endauth

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
        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('warning') }}
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
            <p class="mb-1"><img src="/images/club-logo.png" alt="" height="20" class="me-1">{{ $theme['club_full_name'] ?? 'DivingClub' }} — {{ __('Diving Club Management System') }}</p>
            <p class="mb-0 small opacity-75">© {{ date('Y') }} — {{ __('Powered by') }} <a href="https://github.com/collaed/divingclub" class="text-white" target="_blank">DivingClub-Manager</a></p>
        </div>
    </footer>
    {{-- Dark mode persistence --}}
    <script>
    (function(){
        var t = localStorage.getItem('dc_theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
        var b = document.getElementById('darkToggle');
        if(b) b.textContent = t === 'dark' ? '☀️' : '🌙';
    })();
    function toggleDarkMode(){
        var h = document.documentElement;
        var t = h.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        h.setAttribute('data-bs-theme', t);
        localStorage.setItem('dc_theme', t);
        var b = document.getElementById('darkToggle');
        if(b) b.textContent = t === 'dark' ? '☀️' : '🌙';
    }
    function setFontSize(d){
        var s = parseInt(localStorage.getItem('dc_fontsize') || '100');
        s = Math.max(80, Math.min(130, s + d * 10));
        document.documentElement.style.fontSize = s + '%';
        localStorage.setItem('dc_fontsize', s);
    }
    (function(){var s=localStorage.getItem('dc_fontsize');if(s)document.documentElement.style.fontSize=s+'%';})();
    </script>
    @auth
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.initPushNotifications) {
                window.initPushNotifications('{{ config("webpush.public_key") }}', '{{ csrf_token() }}');
            }
        });
    </script>
    @endauth
    <x-form-enhancements />
    <x-instant-search />

    {{-- Back to top --}}
    <button id="backToTop" class="position-fixed bottom-0 end-0 m-4 btn btn-primary rounded-circle shadow" style="width:44px;height:44px;z-index:1050;display:none;opacity:.8" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="{{ __('Back to top') }}">↑</button>
    <script>
    (function(){var b=document.getElementById('backToTop');if(!b)return;window.addEventListener('scroll',function(){b.style.display=window.scrollY>300?'block':'none'},{passive:true})})();
    </script>

    {{-- Confirm modal --}}
    <div class="modal fade" id="dcConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <p id="dcConfirmMsg" class="mb-3 fw-semibold"></p>
                    <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" id="dcConfirmBtn" class="btn btn-sm"></button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var pending=null, modal=null;
        // Confirm modal for forms with data-confirm
        document.addEventListener('submit', function(e){
            var f=e.target;
            if(!f.dataset.confirm || f._confirmed) return;
            e.preventDefault();
            pending=f;
            var m=document.getElementById('dcConfirmModal');
            document.getElementById('dcConfirmMsg').textContent=f.dataset.confirm;
            var btn=document.getElementById('dcConfirmBtn');
            btn.textContent=f.dataset.confirmBtn||'{{ __("Confirm") }}';
            btn.className='btn btn-sm btn-'+(f.dataset.confirmStyle||'danger');
            if(!modal) modal=new bootstrap.Modal(m);
            modal.show();
        });
        document.getElementById('dcConfirmBtn').addEventListener('click', function(){
            if(pending){pending._confirmed=true;pending.requestSubmit();pending=null;}
            if(modal) modal.hide();
        });
        // Confirm for links with data-confirm
        document.addEventListener('click', function(e){
            var a=e.target.closest('a[data-confirm]');
            if(!a) return;
            e.preventDefault();
            pending=null;
            var m=document.getElementById('dcConfirmModal');
            document.getElementById('dcConfirmMsg').textContent=a.dataset.confirm;
            var btn=document.getElementById('dcConfirmBtn');
            btn.textContent=a.dataset.confirmBtn||'{{ __("Confirm") }}';
            btn.className='btn btn-sm btn-'+(a.dataset.confirmStyle||'primary');
            if(!modal) modal=new bootstrap.Modal(m);
            btn.onclick=function(){window.location=a.href;};
            modal.show();
        });
        // Global confirm for inline JS: dcConfirm(msg, btnText, style, callback)
        window.dcConfirm=function(msg,btnText,style,cb){
            var m=document.getElementById('dcConfirmModal');
            document.getElementById('dcConfirmMsg').textContent=msg;
            var btn=document.getElementById('dcConfirmBtn');
            btn.textContent=btnText;btn.className='btn btn-sm btn-'+style;
            if(!modal) modal=new bootstrap.Modal(m);
            btn.onclick=function(){if(modal)modal.hide();cb(true);};
            m.addEventListener('hidden.bs.modal',function h(){m.removeEventListener('hidden.bs.modal',h);cb(false);},{once:true});
            modal.show();
        };
        // Double-submit prevention
        document.addEventListener('submit', function(e){
            var f=e.target;
            if(f.dataset.noGuard) return;
            var b=f.querySelector('button[type="submit"]:not([formnovalidate]), input[type="submit"]');
            if(!b||b.disabled) return;
            b.disabled=true;
            b.dataset.origText=b.innerHTML;
            b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>'+b.textContent;
            setTimeout(function(){b.disabled=false;b.innerHTML=b.dataset.origText;},10000);
        });
    })();
    </script>

    {{-- Unsaved changes warning (only on forms with tinymce, textarea[rows>2], or .dc-warn-unsaved) --}}
    <script>
    (function(){
        var dirty=false;
        function isWatched(el){
            var f=el.closest('form');
            if(!f||f.dataset.noWarn!==undefined||f.method==='get') return false;
            return f.classList.contains('dc-warn-unsaved')||f.querySelector('textarea.tinymce,textarea[rows],.dc-warn-unsaved');
        }
        document.addEventListener('input',function(e){if(isWatched(e.target))dirty=true;},{capture:true});
        document.addEventListener('change',function(e){if(isWatched(e.target))dirty=true;},{capture:true});
        document.addEventListener('submit',function(){dirty=false;});
        window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue='';}});
    })();
    </script>

    {{-- Offline banner --}}
    <div id="dcOffline" class="position-fixed top-0 start-0 end-0 text-center py-1 text-white small fw-semibold" style="z-index:1070;background:#dc3545;display:none;transition:transform .3s;transform:translateY(-100%)">
        🔌 {{ __('You are offline — changes may not be saved') }}
    </div>
    <script>
    (function(){
        var b=document.getElementById('dcOffline');
        function show(){b.style.display='block';setTimeout(function(){b.style.transform='translateY(0)'},10);}
        function hide(){b.style.transform='translateY(-100%)';setTimeout(function(){b.style.display='none'},300);}
        window.addEventListener('offline',show);
        window.addEventListener('online',hide);
        if(!navigator.onLine) show();
    })();
    </script>

    {{-- Keyboard hints for photo gallery --}}
    <script>
    (function(){
        var shown=localStorage.getItem('dc_kb_hint');
        if(shown) return;
        var obs=new MutationObserver(function(){
            var g=document.querySelector('.pg-overlay.open');
            if(!g) return;
            obs.disconnect();
            var h=document.createElement('div');
            h.innerHTML='<kbd>←</kbd> <kbd>→</kbd> {{ __("navigate") }} · <kbd>Esc</kbd> {{ __("close") }}';
            h.style.cssText='position:fixed;bottom:20%;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.8);color:#fff;padding:.5rem 1.2rem;border-radius:8px;z-index:9999;font-size:.85rem;pointer-events:none;transition:opacity 1s';
            document.body.appendChild(h);
            setTimeout(function(){h.style.opacity='0'},2500);
            setTimeout(function(){h.remove()},3500);
            localStorage.setItem('dc_kb_hint','1');
        });
        obs.observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['class']});
    })();
    </script>

    {{-- Client-side table sorting --}}
    <script>
    (function(){
        document.querySelectorAll('table[data-searchable] thead th, table.table thead th').forEach(function(th,ci){
            var tbl=th.closest('table');
            if(!tbl||th.dataset.noSort!==undefined) return;
            th.style.cursor='pointer';
            th.style.userSelect='none';
            th.title='{{ __("Click to sort") }}';
            var dir=0;
            th.addEventListener('click',function(){
                var tbody=tbl.querySelector('tbody');
                if(!tbody) return;
                var rows=Array.from(tbody.querySelectorAll('tr'));
                // Reset siblings
                th.parentElement.querySelectorAll('th').forEach(function(s){if(s!==th)s.dataset.sort='';});
                dir=dir===1?-1:1;
                th.dataset.sort=dir===1?'asc':'desc';
                rows.sort(function(a,b){
                    var ac=(a.children[ci]||{}).textContent||'';
                    var bc=(b.children[ci]||{}).textContent||'';
                    var an=parseFloat(ac.replace(/[^\d.,-]/g,'')),bn=parseFloat(bc.replace(/[^\d.,-]/g,''));
                    if(!isNaN(an)&&!isNaN(bn)) return (an-bn)*dir;
                    return ac.localeCompare(bc,'{{ app()->getLocale() }}',{sensitivity:'base'})*dir;
                });
                rows.forEach(function(r){tbody.appendChild(r);});
            });
        });
        // Sort indicator CSS
        var s=document.createElement('style');
        s.textContent='th[data-sort=asc]::after{content:" ↑";opacity:.5}th[data-sort=desc]::after{content:" ↓";opacity:.5}';
        document.head.appendChild(s);
    })();
    </script>
    @stack('scripts')
</body>
</html>
