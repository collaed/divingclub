<!doctype html>
<html>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #222; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ $event->title }}</h2>
        <p style="color: #666; margin: 0 0 20px;">{{ $event->event_date?->format('d/m/Y') }}@if($event->location) · {{ $event->location }} @endif</p>

        <div style="white-space: pre-wrap;">{{ $body }}</div>

        <p style="margin: 24px 0;">
            <a href="{{ route('events.show', $event) }}"
               style="display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                {{ __('View the event') }}
            </a>
        </p>
    </div>
</body>
</html>
