<!doctype html>
<html>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #222; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ $dispatch->subject }}</h2>

        @if($dispatch->message)
            <div style="white-space: pre-wrap; margin-bottom: 20px;">{{ $dispatch->message }}</div>
        @endif

        <p style="margin: 24px 0;">
            <a href="{{ $url }}"
               style="display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                {{ __('Open the document') }}
            </a>
        </p>

        <p style="font-size: 12px; color: #888; margin-top: 28px;">
            {{ __('This link is personal to you. If the button does not work, copy this address into your browser:') }}<br>
            <span style="word-break: break-all;">{{ $url }}</span>
        </p>
    </div>
</body>
</html>
