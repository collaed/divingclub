{{-- home2.blade.php — Modern single-page scrolling landing | ClubCEP.eu --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $theme['club_full_name'] ?? 'Club Européen de Plongée' }}</title>
    <link rel="icon" href="/images/club-logo.png" type="image/png">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>
    :root {--h2-primary: {{ $theme['primary_color'] ?? '#003366' }}; --h2-accent: {{ $theme['accent_color'] ?? '#00e5ff' }};}
    html { scroll-behavior: smooth; }
    body { overflow-x: hidden; }

    /* ── Hero ── */
    .h2-hero {
        position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #0d1642 0%, var(--h2-primary) 50%, #283593 100%);
        overflow: hidden;
    }
    .h2-hero-bg { position: absolute; inset: 0; opacity: .25; }
    .h2-hero-bg img { width: 100%; height: 100%; object-fit: cover; }
    .h2-hero-content { position: relative; z-index: 2; text-align: center; color: #fff; padding: 2rem; }
    .h2-hero-content h1 { font-size: clamp(2.5rem, 6vw, 4.5rem); font-weight: 800; letter-spacing: -1px; }
    .h2-hero-content p { font-size: clamp(1.1rem, 2.5vw, 1.5rem); opacity: .85; max-width: 600px; margin: 1rem auto 2rem; }
    .h2-scroll-hint { position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%); z-index: 2; animation: h2-bounce 2s infinite; color: rgba(255,255,255,.6); font-size: 2rem; }
    @keyframes h2-bounce { 0%,100% { transform: translateX(-50%) translateY(0); } 50% { transform: translateX(-50%) translateY(10px); } }

    /* ── Sections ── */
    .h2-section { padding: 5rem 0; position: relative; }
    .h2-section:nth-child(even) { background: rgba(0,0,0,.02); }
    [data-bs-theme="dark"] .h2-section:nth-child(even) { background: rgba(255,255,255,.03); }
    .h2-section-title { font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 700; margin-bottom: .5rem; }
    .h2-section-divider { width: 60px; height: 4px; background: var(--h2-accent); border-radius: 2px; margin-bottom: 2rem; }
    .h2-section-body { font-size: 1.05rem; line-height: 1.8; }
    .h2-section-body h5 { margin-top: 1.5rem; font-weight: 600; }
    .h2-section-body img { max-width: 100%; border-radius: 8px; }
    .h2-section-body table { width: 100%; }

    /* ── Photo strip ── */
    .h2-photo-strip { display: flex; gap: 0; overflow: hidden; height: 220px; }
    .h2-photo-strip img { flex: 1 0 0; min-width: 0; height: 100%; object-fit: cover; filter: brightness(.85); transition: filter .3s; }
    .h2-photo-strip img:hover { filter: brightness(1); }

    /* ── Events bar ── */
    .h2-events { background: var(--h2-primary); color: #fff; padding: 3rem 0; }
    .h2-event-card { background: rgba(255,255,255,.1); border-radius: 12px; padding: 1.5rem; backdrop-filter: blur(4px); transition: transform .2s; }
    .h2-event-card:hover { transform: translateY(-4px); }
    .h2-event-date { font-size: 2rem; font-weight: 800; line-height: 1; }
    .h2-event-month { font-size: .85rem; text-transform: uppercase; opacity: .7; }

    /* ── Sticky nav ── */
    .h2-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: .75rem 0;
        background: rgba(13,22,66,.95); backdrop-filter: blur(10px); transform: translateY(-100%); transition: transform .3s; }
    .h2-nav.visible { transform: translateY(0); }
    .h2-nav a { color: rgba(255,255,255,.8); text-decoration: none; font-size: .9rem; padding: .25rem .75rem; border-radius: 4px; transition: background .2s; }
    .h2-nav a:hover, .h2-nav a.active { background: rgba(255,255,255,.15); color: #fff; }

    /* ── Fade-in on scroll ── */
    .h2-reveal { opacity: 0; transform: translateY(30px); transition: opacity .6s ease, transform .6s ease; }
    .h2-reveal.visible { opacity: 1; transform: translateY(0); }

    /* ── Footer ── */
    .h2-footer { background: #0a0a1a; color: rgba(255,255,255,.6); padding: 2rem 0; text-align: center; font-size: .9rem; }
    </style>
</head>
<body>

{{-- Sticky nav (appears on scroll) --}}
<nav class="h2-nav" id="stickyNav">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="#hero" class="d-flex align-items-center gap-2 fw-bold" style="color:#fff">
            <img src="/images/club-logo.png" alt="" height="28"> {{ $theme['club_full_name'] ?? 'CEP' }}
        </a>
        <div class="d-none d-md-flex gap-1" id="navLinks">
            @foreach($sections as $s)
                <a href="#s-{{ $s->slug }}">{{ $s->title }}</a>
            @endforeach
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-sm btn-outline-light py-0" onclick="document.documentElement.dataset.bsTheme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark'" title="Toggle dark mode">🌓</button>
            @auth
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline-light py-0">{{ __('Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm" style="background:var(--h2-accent);color:#000;font-weight:600">{{ __('Login') }}</a>
            @endauth
        </div>
    </div>
</nav>

{{-- Hero --}}
<section class="h2-hero" id="hero">
    @if($photos->isNotEmpty())
        <div class="h2-hero-bg"><img src="{{ asset('storage/'.$photos->first()) }}" alt=""></div>
    @endif
    <div class="h2-hero-content">
        <img src="/images/club-logo.png" alt="" height="80" class="mb-3" style="filter:drop-shadow(0 4px 12px rgba(0,0,0,.4))">
        <h1>{{ $theme['club_full_name'] ?? 'Club Européen de Plongée' }}</h1>
        <p>Plongez avec nous au Luxembourg 🤿</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            @guest
                <a href="{{ route('login') }}" class="btn btn-lg px-4" style="background:var(--h2-accent);color:#000;font-weight:600">{{ __('Login') }}</a>
                <a href="{{ route('trial.show') }}" class="btn btn-lg btn-outline-light px-4">{{ __('Try Diving') }}</a>
            @else
                <a href="{{ route('events.index') }}" class="btn btn-lg px-4" style="background:var(--h2-accent);color:#000;font-weight:600">{{ __('Events') }}</a>
                <a href="{{ route('home') }}" class="btn btn-lg btn-outline-light px-4">{{ __('Dashboard') }}</a>
            @endguest
        </div>
    </div>
    <div class="h2-scroll-hint">↓</div>
</section>

{{-- Upcoming events bar --}}
@if($events->isNotEmpty())
<section class="h2-events">
    <div class="container">
        <h3 class="text-center mb-4 fw-bold">📅 {{ __('Upcoming Events') }}</h3>
        <div class="row g-3 justify-content-center">
            @foreach($events as $ev)
                <div class="col-md-4">
                    <a href="{{ route('events.show', $ev) }}" class="h2-event-card d-block text-white text-decoration-none">
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center" style="min-width:60px">
                                <div class="h2-event-date">{{ $ev->event_date->format('d') }}</div>
                                <div class="h2-event-month">{{ $ev->event_date->translatedFormat('M Y') }}</div>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $ev->title }}</div>
                                @if($ev->location)<small class="opacity-75">📍 {{ $ev->location }}</small>@endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Photo strip --}}
@if($photos->count() >= 3)
<div class="h2-photo-strip">
    @foreach($photos->skip(1)->take(5) as $p)
        <img src="{{ asset('storage/'.$p) }}" alt="" loading="lazy">
    @endforeach
</div>
@endif

{{-- Article sections --}}
@foreach($sections as $i => $section)
<section class="h2-section" id="s-{{ $section->slug }}">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 h2-reveal">
                <h2 class="h2-section-title">{{ $section->title }}</h2>
                <div class="h2-section-divider"></div>
                <div class="h2-section-body">{!! $section->body !!}</div>
            </div>
        </div>
    </div>
</section>
@endforeach

{{-- CTA --}}
<section class="h2-events text-center">
    <div class="container h2-reveal">
        <h2 class="fw-bold mb-3">{{ __('Ready to dive?') }}</h2>
        <p class="mb-4 opacity-75" style="font-size:1.1rem">{{ __('Join us for a trial session — no experience needed!') }}</p>
        <a href="{{ route('trial.show') }}" class="btn btn-lg px-5" style="background:var(--h2-accent);color:#000;font-weight:600">{{ __('Book a Trial') }} →</a>
    </div>
</section>

{{-- Footer --}}
<footer class="h2-footer">
    <p class="mb-1">{{ $theme['club_full_name'] ?? 'Club Européen de Plongée' }} — Luxembourg</p>
    <p class="mb-0">© {{ date('Y') }} — <a href="{{ route('home') }}" class="text-white">{{ __('Classic Homepage') }}</a>
    · Powered by <a href="https://github.com/collaed/divingclub" class="text-white" target="_blank">DivingClub-Manager</a></p>
</footer>

<script>
// Sticky nav on scroll
const nav = document.getElementById('stickyNav');
window.addEventListener('scroll', () => nav.classList.toggle('visible', window.scrollY > window.innerHeight * 0.5), {passive: true});

// Reveal on scroll
const obs = new IntersectionObserver(entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } }), {threshold: 0.15});
document.querySelectorAll('.h2-reveal').forEach(el => obs.observe(el));

// Active nav link
const sections = document.querySelectorAll('.h2-section');
const links = document.querySelectorAll('#navLinks a');
window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(s => { if (window.scrollY >= s.offsetTop - 200) current = s.id; });
    links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + current));
}, {passive: true});
</script>
</body>
</html>
