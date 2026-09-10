<x-admin-layout :title="__('Tracked documents')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('📤') {{ __('Tracked documents') }}</h4>
        <a href="{{ route('admin.document-dispatch.create') }}" class="btn btn-sm btn-primary">+ {{ __('New send') }}</a>
    </div>
    <p class="text-muted small">{{ __('Send a library PDF to selected members with a unique link each, and see who opened it, from where and when. Open records are kept indefinitely.') }}</p>

    <x-table class="table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Subject') }}</th>
                <th>{{ __('Document') }}</th>
                <th>{{ __('Sent') }}</th>
                <th class="text-end">{{ __('Recipients') }}</th>
                <th class="text-end">{{ __('Opened') }}</th>
                <th>{{ __('By') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dispatches as $d)
                <tr>
                    <td><a href="{{ route('admin.document-dispatch.show', $d) }}" class="text-decoration-none fw-medium">{{ $d->subject }}</a></td>
                    <td class="small text-muted">{{ $d->file?->original_name ?? '—' }}</td>
                    <td class="small text-muted text-nowrap">{{ $d->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="text-end">{{ $d->recipients_count }}</td>
                    <td class="text-end">{{ $d->opened_count }} / {{ $d->recipients_count }}</td>
                    <td class="small text-muted">{{ $d->creator?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing sent yet.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>

    {{ $dispatches->links() }}
</x-admin-layout>
