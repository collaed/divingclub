{{-- home2.blade.php — Modern single-page scrolling landing | ClubCEP.eu --}}
@php use Illuminate\Support\Facades\Storage; @endphp
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
            <a href="#s-contact">{{ __('Contact') }}</a>
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
                @if($section->slug === 'history')
                {{-- Condensed history with vintage gear images --}}
                <h2 class="h2-section-title">{{ __('Since 1972') }}</h2>
                <div class="h2-section-divider"></div>
                <p style="font-size:1.15rem;line-height:1.9">
                    {{ __('Founded on May 6, 1972 by Guy Le Gloan and a group of European civil servants, the CEP is one of Luxembourg\'s oldest diving clubs. Affiliated to the FFESSM and CMAS, we train divers from beginner to instructor level — with safety as our guiding principle.') }}
                </p>
                <p style="font-size:1.15rem;line-height:1.9">
                    {{ __('From the era of twin-hose regulators and Fenzy buoyancy vests to today\'s modern BCDs and dive computers, the spirit remains the same: share the passion, explore together, and welcome everyone — regardless of language or nationality.') }}
                </p>
                <div class="row g-3 my-4 text-center">
                    <div class="col-4">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Aqua-Lung_Mistral_1st_generation.jpg/220px-Aqua-Lung_Mistral_1st_generation.jpg" alt="Mistral regulator" class="rounded mb-2" style="width:100%;max-width:200px;aspect-ratio:1;object-fit:cover">
                        <div class="small text-muted">Mistral (1955) — the twin-hose regulator that started it all</div>
                    </div>
                    <div class="col-4">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4e/Fenzy_ABLJ.jpg/220px-Fenzy_ABLJ.jpg" alt="Fenzy buoyancy vest" class="rounded mb-2" style="width:100%;max-width:200px;aspect-ratio:1;object-fit:cover">
                        <div class="small text-muted">Fenzy — the buoyancy vest before BCDs existed</div>
                    </div>
                    <div class="col-4">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8e/Scubapro_Hydros_Pro_BCD.jpg/220px-Scubapro_Hydros_Pro_BCD.jpg" alt="Modern BCD" class="rounded mb-2" style="width:100%;max-width:200px;aspect-ratio:1;object-fit:cover">
                        <div class="small text-muted">Modern BCD — a long way from the Fenzy!</div>
                    </div>
                </div>
                <p class="text-center">
                    <a href="{{ route('article.show', 'history') }}" class="btn btn-outline-primary">{{ __('Read the full story') }} →</a>
                </p>
                @if($section->slug === 'bureau')
                {{-- Dynamic bureau section --}}
                <h2 class="h2-section-title">{{ __('The Bureau') }}</h2>
                <div class="h2-section-divider"></div>
                <p>{{ __('The bureau is the elected body of the club, responsible for day-to-day management, finances, and direction.') }}</p>
                <div class="row g-3 mt-2">
                    @foreach($bureauMembers as $bm)
                        <div class="col-6 col-md-4 text-center">
                            @if($bm->avatar_path && Storage::disk('public')->exists($bm->avatar_path))
                                <img src="{{ asset('storage/'.$bm->avatar_path) }}" alt="{{ $bm->first_name }}" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover">
                            @else
                                <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-white fw-bold" style="width:80px;height:80px;background:var(--h2-primary);font-size:1.5rem">{{ substr($bm->first_name,0,1) }}{{ substr($bm->last_name,0,1) }}</div>
                            @endif
                            <div class="fw-semibold">{{ $bm->first_name }} {{ $bm->last_name }}</div>
                        </div>
                    @endforeach
                </div>

                @elseif($section->slug === 'instructors')
                {{-- Dynamic instructors section --}}
                <h2 class="h2-section-title">{{ __('Our Instructors') }}</h2>
                <div class="h2-section-divider"></div>
                @if($instructors->isNotEmpty())
                    <div class="row g-4">
                        @foreach($instructors as $inst)
                            <div class="col-md-6">
                                <div class="d-flex gap-3">
                                    @if($inst->avatar_path && Storage::disk('public')->exists($inst->avatar_path))
                                        <img src="{{ asset('storage/'.$inst->avatar_path) }}" alt="" class="rounded-circle" style="width:60px;height:60px;object-fit:cover">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0" style="width:60px;height:60px;background:var(--h2-primary)">{{ substr($inst->first_name,0,1) }}{{ substr($inst->last_name,0,1) }}</div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $inst->first_name }} {{ $inst->last_name }}</div>
                                        <p class="small text-muted mb-0">{{ $inst->instructor_bio }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted">{{ __('Instructor profiles will appear here once they complete their bio in My Profile → Diving tab.') }}</p>
                @endif

                @else
                <h2 class="h2-section-title">{{ $section->title }}</h2>
                <div class="h2-section-divider"></div>
                <div class="h2-section-body">{!! $section->body !!}</div>
                @endif

                @if($section->slug === 'member-figures')
                {{-- Live stats --}}
                <div class="row g-4 mt-3 text-center">
                    <div class="col-4">
                        <div class="display-4 fw-bold" style="color:var(--h2-accent)">{{ $memberStats['total'] }}</div>
                        <div class="text-muted">{{ __('Members') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="display-4 fw-bold" style="color:var(--h2-accent)">{{ $memberStats['nationality']->count() }}</div>
                        <div class="text-muted">{{ __('Nationalities') }}</div>
                    </div>
                    <div class="col-4">
                        @php $f = $memberStats['gender']->get('F', 0); $pct = $memberStats['total'] ? round($f / $memberStats['total'] * 100) : 0; @endphp
                        <div class="display-4 fw-bold" style="color:var(--h2-accent)">{{ $pct }}%</div>
                        <div class="text-muted">{{ __('Women') }}</div>
                    </div>
                </div>
                <div class="mt-4">
                    <h5>🌍 {{ __('Nationalities') }}</h5>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach($memberStats['nationality'] as $nat => $count)
                            <span class="badge bg-secondary bg-opacity-25 text-body px-2 py-1">{{ $nat }} <strong>{{ $count }}</strong></span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endforeach

{{-- Contact & links section --}}
<section class="h2-section" id="s-contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 h2-reveal">
                <h2 class="h2-section-title">{{ __('Get in Touch') }}</h2>
                <div class="h2-section-divider"></div>

                <div class="row g-4 mt-2">
                    <div class="col-md-6">
                        <h5>📧 {{ __('Contact') }}</h5>
                        @if($theme['club_email'] ?? false)
                            <p><a href="mailto:{{ $theme['club_email'] }}">{{ $theme['club_email'] }}</a></p>
                        @endif
                        @if($theme['club_address'] ?? false)
                            <p class="text-muted">{!! nl2br(str_replace(' / ', "\n", e($theme['club_address']))) !!}</p>
                        @endif
                        <a href="{{ route('contact') }}" class="btn btn-outline-primary btn-sm">{{ __('Contact Form') }} →</a>
                    </div>

                    <div class="col-md-6">
                        <h5>📍 {{ __('Training Locations') }}</h5>
                        @php $locations = json_decode($theme['training_locations'] ?? '[]', true) @endphp
                        @foreach($locations as $loc)
                            <p class="mb-1"><strong>{{ $loc['name'] }}</strong>
                                @if($loc['address'] ?? false)<br><small class="text-muted">{{ $loc['address'] }}</small>@endif
                            </p>
                        @endforeach
                    </div>
                </div>

                <div class="row g-4 mt-2">
                    <div class="col-md-6">
                        <h5>📅 {{ __('Useful Links') }}</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="{{ route('events.index') }}">📆 {{ __('Events Calendar') }}</a></li>
                            <li class="mb-2"><a href="{{ route('article.show', 'training-schedule-ULO7R') }}">🗓️ {{ __('Training Schedule') }}</a></li>
                            <li class="mb-2"><a href="{{ route('trial.show') }}">🐠 {{ __('Try Diving') }}</a></li>
                            <li class="mb-2"><a href="{{ route('dues.show') }}">💶 {{ __('Membership Fees') }}</a></li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h5>👥 {{ __('The Bureau') }}</h5>
                        <p>{{ __('The club is run by an elected volunteer bureau.') }}</p>
                        <a href="{{ route('article.show', 'bureau') }}" class="btn btn-outline-primary btn-sm">{{ __('Meet the Bureau') }} →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
