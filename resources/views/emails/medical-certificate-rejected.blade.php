<!doctype html>
<html>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #222; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ __('Your medical certificate needs a new upload') }}</h2>

        <p>{{ __('The medical certificate you submitted could not be accepted:') }}</p>

        <div style="background:#fff5f5; border-left: 4px solid #dc3545; padding: 12px 16px; margin: 16px 0; white-space: pre-wrap;">{{ $document->review_comment }}</div>

        <p>{{ __('Please upload a corrected certificate from your profile as soon as possible.') }}</p>

        <p style="margin: 24px 0;">
            <a href="{{ route('profile.show', ['tab' => 'medical']) }}"
               style="display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                {{ __('Go to my profile') }}
            </a>
        </p>
    </div>
</body>
</html>
