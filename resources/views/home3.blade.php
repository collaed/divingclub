{{-- home3.blade.php — Visual-first landing page with slide-in login --}}
@php
    $clubName = $theme['club_full_name'] ?? 'Club Européen de Plongée';
    $clubEmail = $theme['club_email'] ?? '';
    $primary = $theme['primary_color'] ?? '#003366';
    $accent = $theme['accent_color'] ?? '#00e5ff';
    $totalMembers = $stats['total'] ?? 0;
    $nationalities = isset($stats['nationality']) ? $stats['nationality']->count() : 0;
    $pctWomen = $totalMembers ? round(($stats['gender']->get('F', 0) / $totalMembers) * 100) : 0;
    $providers = collect([
        'google' => '🔵  Google',
        'microsoft' => '🟦  Microsoft',
        'facebook' => '🔷  Facebook',
        'x' => '⬛  X',
    ])->filter(fn ($label, $key) => config("services.{$key}.client_id"));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $clubName }}</title>
    <link rel="icon" href="/images/club-logo.png" type="image/png">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>
    :root { --h3-primary: {{ $primary }}; --h3-accent: {{ $accent }}; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, sans-serif; overflow-x: hidden; color: #222; }

    /* ── Hero ── */
    .h3-hero { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .h3-hero-bg { position: absolute; inset: 0; }
    .h3-hero-bg img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 1.5s ease; }
    .h3-hero-bg img.active { opacity: 1; }
    .h3-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,20,50,.6) 0%, rgba(0,20,50,.4) 100%); }
    .h3-hero-content { position: relative; z-index: 2; text-align: center; color: #fff; padding: 2rem; }
    .h3-hero-content h1 { font-size: clamp(2.5rem, 7vw, 5rem); font-weight: 800; letter-spacing: -1px; text-shadow: 0 2px 20px rgba(0,0,0,.5); }
    .h3-hero-content p { font-size: clamp(1rem, 2.5vw, 1.4rem); opacity: .85; margin: 1rem auto 2.5rem; max-width: 500px; }
    .h3-btn { display: inline-block; padding: .9rem 2.5rem; border-radius: 50px; font-weight: 700; font-size: 1rem; text-decoration: none; transition: transform .2s, box-shadow .2s; }
    .h3-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.3); }
    .h3-btn-accent { background: var(--h3-accent); color: #000; }
    .h3-btn-outline { border: 2px solid rgba(255,255,255,.7); color: #fff; background: transparent; }
    .h3-btn-outline:hover { background: rgba(255,255,255,.15); color: #fff; }
    .h3-scroll { position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%); z-index: 2; color: rgba(255,255,255,.5); font-size: 2rem; animation: h3bounce 2s infinite; }
    @keyframes h3bounce { 0%,100% { transform: translateX(-50%) translateY(0); } 50% { transform: translateX(-50%) translateY(12px); } }

    /* ── Sticky nav ── */
    .h3-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: .6rem 1.5rem; background: rgba(0,15,40,.95); backdrop-filter: blur(12px); transform: translateY(-100%); transition: transform .3s; display: flex; align-items: center; justify-content: space-between; }
    .h3-nav.visible { transform: translateY(0); }
    .h3-nav-brand { display: flex; align-items: center; gap: .5rem; color: #fff; text-decoration: none; font-weight: 700; font-size: .95rem; }
    .h3-nav-brand img { height: 28px; }

    /* ── Numbers ── */
    .h3-numbers { background: var(--h3-primary); color: #fff; padding: 3.5rem 1rem; }
    .h3-numbers .row { max-width: 800px; margin: 0 auto; }
    .h3-num { font-size: clamp(2.5rem, 5vw, 3.5rem); font-weight: 800; line-height: 1; }
    .h3-num-label { font-size: .85rem; opacity: .6; margin-top: .3rem; }

    /* ── Photo mosaic ── */
    .h3-mosaic { display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: 200px 200px; gap: 4px; }
    .h3-mosaic a { overflow: hidden; display: block; }
    .h3-mosaic img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
    .h3-mosaic a:hover img { transform: scale(1.08); }
    .h3-mosaic a:nth-child(1) { grid-row: 1 / 3; grid-column: 1; }
    .h3-mosaic a:nth-child(6) { grid-row: 1 / 3; grid-column: 4; }
    @media (max-width: 768px) { .h3-mosaic { grid-template-columns: 1fr 1fr; grid-template-rows: repeat(4, 150px); } .h3-mosaic a:nth-child(1), .h3-mosaic a:nth-child(6) { grid-row: auto; grid-column: auto; } }

    /* ── Events ── */
    .h3-events { background: var(--h3-primary); padding: 4rem 1rem; color: #fff; }
    .h3-ev-card { background: rgba(255,255,255,.1); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(4px); text-decoration: none; color: #fff; display: flex; align-items: center; gap: 1.2rem; transition: transform .2s, background .2s; }
    .h3-ev-card:hover { transform: translateY(-3px); background: rgba(255,255,255,.18); color: #fff; }
    .h3-ev-date { font-size: 2.5rem; font-weight: 800; line-height: 1; min-width: 60px; text-align: center; }
    .h3-ev-month { font-size: .75rem; text-transform: uppercase; opacity: .6; }

    /* ── Values ── */
    .h3-values { padding: 5rem 1rem; background: #f8f9fa; }
    .h3-val-card { text-align: center; padding: 2rem 1.5rem; }
    .h3-val-icon { font-size: 3rem; margin-bottom: 1rem; }
    .h3-val-text { font-size: 1.05rem; color: #555; line-height: 1.6; }

    /* ── Faces ── */
    .h3-faces { padding: 3rem 1rem; overflow-x: auto; }
    .h3-faces-row { display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap; }
    .h3-face { text-align: center; flex-shrink: 0; }
    .h3-face img, .h3-face-init { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; }
    .h3-face-init { display: flex; align-items: center; justify-content: center; background: var(--h3-primary); color: #fff; font-weight: 700; font-size: 1.3rem; margin: 0 auto; }
    .h3-face-name { font-size: .8rem; margin-top: .4rem; color: #666; }

    /* ── CTA ── */
    .h3-cta { background: linear-gradient(135deg, #0d1642, var(--h3-primary)); color: #fff; padding: 5rem 1rem; text-align: center; }
    .h3-cta h2 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 800; margin-bottom: 1.5rem; }

    /* ── Footer ── */
    .h3-footer { background: #0a0a1a; color: rgba(255,255,255,.5); padding: 1.5rem; text-align: center; font-size: .85rem; }
    .h3-footer a { color: rgba(255,255,255,.7); }

    /* ── Slide-in login ── */
    .h3-login-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 200; opacity: 0; pointer-events: none; transition: opacity .3s; }
    .h3-login-backdrop.open { opacity: 1; pointer-events: auto; }
    .h3-login-panel { position: fixed; top: 0; right: 0; bottom: 0; width: 380px; max-width: 90vw; background: #fff; z-index: 201; transform: translateX(100%); transition: transform .35s ease; padding: 2rem; overflow-y: auto; box-shadow: -4px 0 30px rgba(0,0,0,.2); }
    .h3-login-panel.open { transform: translateX(0); }
    .h3-login-close { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }
    .h3-login-panel h3 { font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; }
    .h3-login-panel .form-control { border-radius: 8px; padding: .7rem 1rem; }
    .h3-login-panel .btn-primary { border-radius: 8px; padding: .7rem; font-weight: 600; }
    .h3-login-panel hr { margin: 1.5rem 0; }

    /* ── Reveal ── */
    .h3-reveal { opacity: 0; transform: translateY(25px); transition: opacity .6s, transform .6s; }
    .h3-reveal.visible { opacity: 1; transform: translateY(0); }

    /* ── Section title ── */
    .h3-stitle { text-align: center; margin-bottom: 2.5rem; }
    .h3-stitle h2 { font-size: clamp(1.6rem, 4vw, 2.2rem); font-weight: 700; }
    .h3-stitle-bar { width: 50px; height: 4px; background: var(--h3-accent); border-radius: 2px; margin: .5rem auto 0; }
    </style>
</head>
<body>

{{-- Sticky nav --}}
<nav class="h3-nav" id="stickyNav">
    <a href="#hero" class="h3-nav-brand"><img src="/images/club-logo.png" alt=""> {{ $clubName }}</a>
    <div style="display:flex;gap:.5rem;align-items:center">
        @auth
            <a href="{{ route('home') }}" class="h3-btn h3-btn-accent" style="padding:.4rem 1.2rem;font-size:.85rem">{{ __('Dashboard') }}</a>
        @else
            <button onclick="openLogin()" class="h3-btn h3-btn-accent" style="padding:.4rem 1.2rem;font-size:.85rem">{{ __('Login') }}</button>
        @endauth
    </div>
</nav>

{{-- ① Hero --}}
<section class="h3-hero" id="hero">
    <div class="h3-hero-bg">
        @foreach($photos as $i => $p)
            <img src="{{ asset('storage/'.$p) }}" alt="" @if($i === 0) class="active" @endif loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
        @endforeach
    </div>
    <div class="h3-hero-content">
        <img src="/images/club-logo.png" alt="" height="90" style="filter:drop-shadow(0 4px 15px rgba(0,0,0,.5));margin-bottom:1rem">
        <h1>{{ $clubName }}</h1>
        <p>{{ __('Dive with us in Luxembourg') }} 🤿</p>
        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
            @auth
                <a href="{{ route('events.index') }}" class="h3-btn h3-btn-accent">{{ __('Events') }}</a>
                <a href="{{ route('home') }}" class="h3-btn h3-btn-outline">{{ __('Dashboard') }}</a>
            @else
                <button onclick="openLogin()" class="h3-btn h3-btn-accent">{{ __('Login') }}</button>
                <a href="{{ route('trial.show') }}" class="h3-btn h3-btn-outline">{{ __('Try Diving') }}</a>
            @endauth
        </div>
    </div>
    <div class="h3-scroll">↓</div>
</section>

{{-- ② Numbers strip --}}
<section class="h3-numbers">
    <div class="row text-center g-4" id="numbersRow">
        <div class="col-6 col-md-3"><div class="h3-num" data-target="{{ $totalMembers }}">0</div><div class="h3-num-label">{{ __('Members') }}</div></div>
        <div class="col-6 col-md-3"><div class="h3-num" data-target="{{ $nationalities }}">0</div><div class="h3-num-label">{{ __('Nationalities') }}</div></div>
        <div class="col-6 col-md-3"><div class="h3-num" data-target="{{ date('Y') - 1972 }}">0</div><div class="h3-num-label">{{ __('Years of diving') }}</div></div>
        <div class="col-6 col-md-3"><div class="h3-num" data-target="{{ $pctWomen }}" data-suffix="%">0</div><div class="h3-num-label">{{ __('Women') }}</div></div>
    </div>
</section>

{{-- ③ Photo mosaic --}}
@if($photos->count() >= 6)
<div class="h3-mosaic h3-reveal">
    @foreach($photos->take(6) as $p)
        <a href="#"><img src="{{ asset('storage/'.$p) }}" alt="" loading="lazy"></a>
    @endforeach
</div>
@endif

{{-- ④ Upcoming events --}}
@if($events->isNotEmpty())
<section class="h3-events">
    <div class="container">
        <div class="h3-stitle h3-reveal"><h2 style="color:#fff">📅 {{ __('Upcoming') }}</h2><div class="h3-stitle-bar"></div></div>
        <div class="row g-3 justify-content-center">
            @foreach($events as $ev)
                <div class="col-md-4 h3-reveal">
                    <a href="{{ route('events.show', $ev) }}" class="h3-ev-card">
                        <div>
                            <div class="h3-ev-date">{{ $ev->event_date->format('d') }}</div>
                            <div class="h3-ev-month">{{ $ev->event_date->translatedFormat('M') }}</div>
                        </div>
                        <div>
                            <div style="font-weight:600">{{ $ev->title }}</div>
                            @if($ev->location)<div style="font-size:.85rem;opacity:.7">📍 {{ $ev->location }}</div>@endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ⑤ Value cards --}}
<section class="h3-values">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 h3-reveal">
                <div class="h3-val-card">
                    <div class="h3-val-icon">🤿</div>
                    <div class="h3-val-text">{{ __('From first breath underwater to instructor — all levels welcome.') }}</div>
                </div>
            </div>
            <div class="col-md-4 h3-reveal">
                <div class="h3-val-card">
                    <div class="h3-val-icon">🌍</div>
                    <div class="h3-val-text">{{ __('A truly international club — :count nationalities, united by the sea.', ['count' => $nationalities]) }}</div>
                </div>
            </div>
            <div class="col-md-4 h3-reveal">
                <div class="h3-val-card">
                    <div class="h3-val-icon">📅</div>
                    <div class="h3-val-text">{{ __('Weekly pool sessions, open water dives, and trips abroad — all year round.') }}</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ⑥ Faces --}}
@if($faces->isNotEmpty())
<section class="h3-faces h3-reveal">
    <div class="h3-stitle"><h2>{{ __('Our Team') }}</h2><div class="h3-stitle-bar"></div></div>
    <div class="h3-faces-row">
        @foreach($faces as $f)
            <div class="h3-face">
                @if($f->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($f->avatar_path))
                    <img src="{{ asset('storage/'.$f->avatar_path) }}" alt="{{ $f->first_name }}">
                @else
                    <div class="h3-face-init">{{ mb_substr($f->first_name,0,1) }}{{ mb_substr($f->last_name,0,1) }}</div>
                @endif
                <div class="h3-face-name">{{ $f->first_name }}</div>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ⑦ CTA --}}
<section class="h3-cta h3-reveal">
    <h2>{{ __('Ready to dive?') }}</h2>
    <a href="{{ route('trial.show') }}" class="h3-btn h3-btn-accent" style="font-size:1.1rem;padding:1rem 3rem">{{ __('Book a Trial') }} →</a>
</section>

{{-- ⑧ Footer --}}
<footer class="h3-footer">
    {{ $clubName }} — Luxembourg
    @if($clubEmail) · <a href="mailto:{{ $clubEmail }}">{{ $clubEmail }}</a> @endif
    <br>© {{ date('Y') }} · <a href="https://github.com/collaed/divingclub" target="_blank">DivingClub-Manager</a>
</footer>

{{-- Slide-in login panel --}}
<div class="h3-login-backdrop" id="loginBackdrop" onclick="closeLogin()"></div>
<div class="h3-login-panel" id="loginPanel">
    <button class="h3-login-close" onclick="closeLogin()">✕</button>
    <h3>{{ __('Login') }}</h3>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div style="margin-bottom:1rem">
            <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" required autofocus>
        </div>
        <div style="margin-bottom:1rem">
            <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required>
        </div>
        <div style="margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between">
            <label style="font-size:.85rem;display:flex;align-items:center;gap:.4rem">
                <input type="checkbox" name="remember"> {{ __('Remember me') }}
            </label>
            <a href="{{ route('password.request') }}" style="font-size:.85rem">{{ __('Forgot?') }}</a>
        </div>
        <button type="submit" class="btn btn-primary w-100">{{ __('Login') }}</button>
    </form>

    @if($providers->isNotEmpty())
        <hr>
        <p style="text-align:center;color:#999;font-size:.85rem;margin-bottom:.75rem">{{ __('Or sign in with') }}</p>
        <div style="display:grid;gap:.5rem">
            @foreach($providers as $provider => $label)
                <a href="{{ route('auth.social.redirect', $provider) }}" class="btn btn-outline-secondary btn-sm">{{ $label }}</a>
            @endforeach
        </div>
    @endif
    <hr>
    <a href="{{ route('auth.eulogin.redirect') }}" class="btn btn-outline-secondary btn-sm w-100">🇪🇺  EU Login</a>

    <div style="text-align:center;margin-top:1.5rem">
        <a href="{{ route('register') }}" style="font-size:.9rem">{{ __("Don't have an account? Register") }}</a>
    </div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded', () => openLogin());</script>
@endif

<script>
// Login panel
function openLogin() { document.getElementById('loginBackdrop').classList.add('open'); document.getElementById('loginPanel').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeLogin() { document.getElementById('loginBackdrop').classList.remove('open'); document.getElementById('loginPanel').classList.remove('open'); document.body.style.overflow = ''; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLogin(); });

// Sticky nav
const nav = document.getElementById('stickyNav');
window.addEventListener('scroll', () => nav.classList.toggle('visible', scrollY > innerHeight * 0.5), {passive: true});

// Hero photo cycling
const heroImgs = document.querySelectorAll('.h3-hero-bg img');
if (heroImgs.length > 1) { let cur = 0; setInterval(() => { heroImgs[cur].classList.remove('active'); cur = (cur + 1) % heroImgs.length; heroImgs[cur].classList.add('active'); }, 6000); }

// Scroll reveal
const obs = new IntersectionObserver(entries => entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } }), {threshold: 0.1});
document.querySelectorAll('.h3-reveal').forEach(el => obs.observe(el));

// Counter animation
const numObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (!e.isIntersecting) return;
        e.target.querySelectorAll('.h3-num').forEach(el => {
            const target = +el.dataset.target, suffix = el.dataset.suffix || '';
            let start = 0; const step = Math.max(1, Math.ceil(target / 40));
            const timer = setInterval(() => { start += step; if (start >= target) { start = target; clearInterval(timer); } el.textContent = start + suffix; }, 30);
        });
        numObs.unobserve(e.target);
    });
}, {threshold: 0.3});
const nr = document.getElementById('numbersRow');
if (nr) numObs.observe(nr);
</script>
</body>
</html>
