@forelse($contacts as $c)
    <tr data-contact-row>
        <td><code>{{ $c->email }}</code></td>
        <td>{{ $c->name ?? '—' }}</td>
        <td><span class="badge {{ $c->subscribed ? 'bg-success' : 'bg-secondary' }}" data-contact-status>{{ $c->subscribed ? __('Subscribed') : __('Unsubscribed') }}</span></td>
        <td class="small text-muted">{{ $c->created_at->format('Y-m-d') }}</td>
        @if($isBureauMaster)
        <td class="text-end text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-contact-toggle data-url="{{ route('admin.mail-list-contacts.toggle', $c) }}">{{ $c->subscribed ? __('Unsubscribe') : __('Resubscribe') }}</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-contact-delete data-url="{{ route('admin.mail-list-contacts.destroy', $c) }}" data-confirm="{{ __('Remove “:email” from the list?', ['email' => $c->email]) }}">@icon('🗑️')</button>
        </td>
        @endif
    </tr>
@empty
    <tr><td colspan="5" class="text-muted text-center py-3">{{ __('No contacts yet.') }}</td></tr>
@endforelse
