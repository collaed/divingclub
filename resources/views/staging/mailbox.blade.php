<x-layout title="Staging Mailbox">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">@icon('📬') Staging Mailbox</h4>
        <div>
            <span class="badge bg-warning text-dark me-2">STAGING MODE</span>
            @if($mails->total() > 0)
                <form action="{{ route('staging.mail.clear') }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" data-confirm="Clear all?" data-confirm-style="primary" data-confirm-btn="{{ __('Confirm') }}">Clear all</button>
                </form>
            @endif
        </div>
    </div>

    <div class="alert alert-info">
        All outgoing emails are captured here instead of being sent. Click a message to view it with clickable links.
    </div>

    @if($mails->isEmpty())
        <p class="text-muted">No captured emails yet.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-dark">
                    <tr><th>Time</th><th>To</th><th>Subject</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($mails as $mail)
                        <tr>
                            <td class="text-nowrap small">{{ $mail->created_at->format('d/m H:i:s') }}</td>
                            <td>{{ $mail->to_email }}</td>
                            <td><a href="{{ route('staging.mail.show', $mail) }}">{{ $mail->subject }}</a></td>
                            <td><a href="{{ route('staging.mail.raw', $mail) }}" target="_blank" class="btn btn-sm btn-outline-secondary">HTML</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $mails->links() }}
    @endif
</x-layout>
