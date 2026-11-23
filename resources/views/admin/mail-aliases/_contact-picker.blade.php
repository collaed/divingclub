{{-- External, non-member addresses (App\Models\MailListContact) to include regardless of criteria — see MailAliasService::resolveDefinition(). --}}
<div class="mb-3">
    <label class="form-label small mb-1">{{ __('Mailing list contacts') }}</label>
    <div class="position-relative mb-1">
        <input type="text" class="form-control form-control-sm" data-member-combo="contact" placeholder="{{ __('Type a name or email…') }}" autocomplete="off">
        <div class="dropdown-menu w-100" data-member-dropdown="contact" style="max-height:200px;overflow-y:auto"></div>
    </div>
    <div data-chip-list="contact" class="d-flex flex-wrap gap-1"></div>
    <div class="form-text">{{ __('People who expressed interest but never registered.') }} <a href="{{ route('admin.mail-list-contacts.index') }}">{{ __('Manage the full list') }}</a></div>
</div>
