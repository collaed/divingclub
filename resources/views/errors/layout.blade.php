<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }}</title>
    @vite(['resources/scss/app.scss'])
    <style>
        body{display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#003366 0%,#004d80 50%,#006699 100%);color:#fff;font-family:system-ui,sans-serif;margin:0}
        .err-card{text-align:center;max-width:480px;padding:2rem}
        .err-code{font-size:5rem;font-weight:800;opacity:.2;line-height:1;margin-bottom:-.5rem}
        .err-img{width:220px;height:220px;border-radius:50%;object-fit:cover;border:4px solid rgba(255,255,255,.15);margin:1rem auto;display:block;box-shadow:0 8px 32px rgba(0,0,0,.3)}
        .err-title{font-size:1.4rem;font-weight:600;margin:.5rem 0}
        .err-msg{opacity:.8;margin-bottom:1.5rem;font-size:.95rem}
        .err-btn{display:inline-block;padding:.5rem 1.5rem;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:.5rem;text-decoration:none;transition:background .2s}
        .err-btn:hover{background:rgba(255,255,255,.25);color:#fff}
        .err-logo{opacity:.4;margin-top:2rem}
    </style>
</head>
<body>
    <div class="err-card">
        <div class="err-code">{{ $code }}</div>
        <img src="/images/errors/{{ $code }}.webp" alt="{{ $title }}" class="err-img">
        <div class="err-title">{{ $title }}</div>
        <div class="err-msg">{{ $message }}</div>
        <a href="/" class="err-btn">{{ __('Back to Home') }}</a>
        @auth
            <a href="{{ url()->previous() }}" class="err-btn ms-2">{{ __('Go Back') }}</a>
        @endauth
        <div class="err-logo"><img src="/images/club-logo.png" alt="{{ __("Logo") }}" height="28" style="opacity:.5"></div>
    </div>
</body>
</html>
