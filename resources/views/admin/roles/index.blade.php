<x-admin-layout :title="__('Roles & Permissions')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">🔐 {{ __('Roles & Permissions') }}</h4>
    </div>

    <form method="POST" action="{{ route('admin.roles.update') }}">
        @csrf
        @method('PUT')

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="min-width:180px">{{ __('Permission') }}</th>
                        @foreach($roles as $role)
                            <th class="text-center" style="min-width:100px">
                                {{ $role->name }}
                                <div class="small text-muted fw-normal">{{ $role->users->count() }} {{ __('users') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissions as $perm)
                        <tr>
                            <td>
                                <strong>{{ $perm->name }}</strong>
                            </td>
                            @foreach($roles as $role)
                                <td class="text-center">
                                    <input type="checkbox"
                                        name="roles[{{ $role->id }}][]"
                                        value="{{ $perm->id }}"
                                        {{ $role->hasPermissionTo($perm->name) ? 'checked' : '' }}
                                        class="form-check-input">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('Save Permissions') }}</button>
    </form>

    <hr>

    <div class="row g-4 mt-2">
        <div class="col-md-6">
            <h5>{{ __('Role Assignments') }}</h5>
            <p class="text-muted small">{{ __('Assign roles to members from their profile page (Admin → Members → Edit).') }}</p>
            <table class="table table-sm">
                <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Members') }}</th></tr></thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr>
                            <td><a href="{{ route('admin.roles.members', $role) }}" class="badge bg-secondary text-decoration-none">{{ $role->name }}</a></td>
                            <td>
                                @foreach($role->users->take(8) as $u)
                                    <span class="small">{{ $u->detail?->first_name ?? $u->primary_email }}</span>{{ !$loop->last ? ', ' : '' }}
                                @endforeach
                                @if($role->users->count() > 8)
                                    <span class="text-muted small">+{{ $role->users->count() - 8 }} {{ __('more') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
