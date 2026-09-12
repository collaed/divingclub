<!doctype html>
<html>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #222; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto; padding: 24px;">
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ __('Your medical certificate has been validated') }}</h2>

        <p>{{ __('Good news — the medical certificate you submitted has been reviewed and accepted.') }}</p>

        @if(count($perFederation))
            <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 6px 8px; background: #f1f8f2; border-bottom: 1px solid #d3e6d6;">{{ __('Federation') }}</th>
                        <th style="text-align: left; padding: 6px 8px; background: #f1f8f2; border-bottom: 1px solid #d3e6d6;">{{ __('Valid until') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($perFederation as $line)
                        @php [$fed, $date] = array_pad(explode(':', $line, 2), 2, ''); @endphp
                        <tr>
                            <td style="padding: 6px 8px; border-bottom: 1px solid #eee;">{{ trim($fed) }}</td>
                            <td style="padding: 6px 8px; border-bottom: 1px solid #eee;">{{ trim($date) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($document->review_comment)
            <div style="background:#fff9e6; border-left: 4px solid #ffc107; padding: 12px 16px; margin: 16px 0; white-space: pre-wrap;">{{ $document->review_comment }}</div>
        @endif

        <p style="margin: 24px 0;">
            <a href="{{ route('profile.show', ['tab' => 'medical']) }}"
               style="display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
                {{ __('Go to my profile') }}
            </a>
        </p>
    </div>
</body>
</html>
