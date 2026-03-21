<x-layout title="Staging Mail">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">@icon('📧') {{ $mail->subject }}</h4>
        <a href="{{ route('staging.mail.index') }}" class="btn btn-sm btn-outline-secondary">← Back</a>
    </div>

    <div class="card dc-card mb-3">
        <div class="card-body">
            <div class="row small text-muted mb-2">
                <div class="col-auto"><strong>To:</strong> {{ $mail->to_email }}</div>
                <div class="col-auto"><strong>From:</strong> {{ $mail->from_name }} &lt;{{ $mail->from_email }}&gt;</div>
                <div class="col-auto"><strong>Date:</strong> {{ $mail->created_at->format('d/m/Y H:i:s') }}</div>
            </div>
            <hr>
            @if(str_contains($mail->body, '<'))
                {{-- HTML email: render in sandboxed iframe --}}
                <iframe srcdoc="{{ e($mail->body) }}" style="width:100%;min-height:400px;border:1px solid #ddd;border-radius:4px;" sandbox="allow-same-origin"></iframe>
            @else
                {{-- Plain text: extract and linkify URLs --}}
                <pre class="mb-0" style="white-space:pre-wrap;">{!! preg_replace(
                    '~(https?://[^\s<]+)~',
                    '<a href="$1" target="_blank">$1</a>',
                    e($mail->body)
                ) !!}</pre>
            @endif
        </div>
    </div>

    <a href="{{ route('staging.mail.raw', $mail) }}" target="_blank" class="btn btn-sm btn-outline-primary">View raw HTML</a>
</x-layout>
