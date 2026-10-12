<div class="card dc-card mb-3" data-menu-item-dropdown data-id="{{ $dropdown->id }}">
    <div class="card-header d-flex justify-content-between align-items-center gap-2" data-menu-item-row data-id="{{ $dropdown->id }}" data-url="{{ route('admin.menu-items.update', $dropdown) }}">
        <div class="d-flex gap-2 align-items-center flex-grow-1">
            <strong class="text-muted small">{{ __('Dropdown:') }}</strong>
            <input type="text" class="form-control form-control-sm w-auto" value="{{ $dropdown->label }}" data-menu-item-field="label" aria-label="{{ __('Label') }}">
            <input type="number" class="form-control form-control-sm" style="width:4.5rem" value="{{ $dropdown->sort_order }}" data-menu-item-field="sort_order" title="{{ __('Position among other dropdowns') }}" aria-label="{{ __('Order') }}">
            <select class="form-select form-select-sm w-auto" data-menu-item-field="auth_required" aria-label="{{ __('Visible to') }}">
                <option value="" @selected($dropdown->auth_required === null)>{{ __('Everyone') }}</option>
                <option value="1" @selected($dropdown->auth_required === true)>{{ __('Logged-in only') }}</option>
                <option value="0" @selected($dropdown->auth_required === false)>{{ __('Guests only') }}</option>
            </select>
            <span class="text-success small d-none" data-menu-item-saved>@icon('✓') {{ __('Saved') }}</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" data-menu-item-delete
                data-url="{{ route('admin.menu-items.destroy', $dropdown) }}"
                data-confirm="{{ __('Remove the ":label" dropdown and all its items?', ['label' => $dropdown->label]) }}">@icon('🗑️')</button>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Icon') }}</th>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Target') }}</th>
                    <th>{{ __('Restrict to roles') }}</th>
                    <th>{{ __('Order') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-menu-item-children data-parent-id="{{ $dropdown->id }}">
                @foreach($dropdown->children as $item)
                    @include('admin.menu-items._row', ['item' => $item])
                @endforeach
            </tbody>
        </table>
    </div>
    @include('admin.menu-items._add_item_form', ['dropdown' => $dropdown])
</div>
