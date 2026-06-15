<x-admin-layout :title="__('Role') . ': ' . $role->name">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">{{ __('Roles & Permissions') }}</a></li>
        <li class="breadcrumb-item active">{{ $role->name }}</li>
    </ol></nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">{{ $role->name }} <span class="badge bg-secondary">{{ $role->users->count() }}</span></h4>
    </div>

    <div class="row">
        {{-- Current members --}}
        <div class="col-md-7">
            <div class="card dc-card">
                <div class="card-header">{{ __('Members in this role') }}</div>
                <div class="card-body p-0">
                    @if($role->users->count())
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                            @foreach($role->users->sortBy(fn($u) => $u->detail?->last_name) as $u)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.profile.show', $u) }}">{{ $u->detail?->first_name }} {{ $u->detail?->last_name }}</a>
                                        <small class="text-muted ms-1">{{ $u->primary_email }}</small>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.roles.remove-member', [$role, $u]) }}" class="d-inline" data-confirm="{{ __('Remove :name from :role?', ['name' => $u->detail?->first_name, 'role' => $role->name]) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger py-0">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted p-3 mb-0">{{ __('No members in this role.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Add member --}}
        <div class="col-md-5">
            <div class="card dc-card">
                <div class="card-header">{{ __('Add member') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.roles.add-member', $role) }}">
                        @csrf
                        <input type="hidden" name="user_id" id="add-role-user-id" value="">
                        <div class="position-relative mb-2">
                            <input type="text" id="add-role-combo" class="form-control form-control-sm" placeholder="{{ __('Type name…') }}" autocomplete="off">
                            <div id="add-role-dropdown" class="dropdown-menu w-100" style="max-height:250px;overflow-y:auto"></div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add to role') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const users = @json($availableUsers->map(fn($u) => ['id' => $u->id, 'name' => trim(($u->detail?->first_name ?? '') . ' ' . ($u->detail?->last_name ?? '')), 'email' => $u->primary_email])->values());
        const input = document.getElementById('add-role-combo');
        const dropdown = document.getElementById('add-role-dropdown');
        const userIdField = document.getElementById('add-role-user-id');

        function render(filter) {
            const f = filter.toLowerCase();
            const matches = f ? users.filter(u => u.name.toLowerCase().includes(f) || u.email.toLowerCase().includes(f)).slice(0, 15) : users.slice(0, 15);
            dropdown.innerHTML = matches.map(u => `<button type="button" class="dropdown-item small" data-id="${u.id}">${u.name} <span class="text-muted">${u.email}</span></button>`).join('');
            dropdown.classList.toggle('show', matches.length > 0);
        }

        input.addEventListener('focus', () => render(input.value));
        input.addEventListener('input', () => { userIdField.value = ''; render(input.value); });
        dropdown.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-id]');
            if (!btn) return;
            userIdField.value = btn.dataset.id;
            input.value = btn.textContent.trim();
            dropdown.classList.remove('show');
        });
        document.addEventListener('click', e => { if (!e.target.closest('#add-role-combo, #add-role-dropdown')) dropdown.classList.remove('show'); });
    });
    </script>
</x-admin-layout>
