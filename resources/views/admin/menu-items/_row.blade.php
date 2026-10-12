@php
    $targetDesc = match(true) {
        $item->type === 'divider' => __('— divider —'),
        $item->link_type === 'article' => __('Page: :title', ['title' => $item->article?->title ?? __('(deleted)')]),
        $item->link_type === 'route' => __('Route: :name', ['name' => $item->target]),
        $item->link_type === 'url' => __('URL: :url', ['url' => $item->target]),
        default => '',
    };
@endphp
<tr data-menu-item-row data-id="{{ $item->id }}" data-url="{{ route('admin.menu-items.update', $item) }}">
    @if($item->type === 'divider')
        <td colspan="5" class="text-muted small">{{ __('— divider —') }}</td>
    @else
        <td style="width:4rem"><input type="text" class="form-control form-control-sm" value="{{ $item->icon }}" data-menu-item-field="icon" aria-label="{{ __('Icon') }}"></td>
        <td>
            <input type="text" class="form-control form-control-sm" value="{{ $item->label }}" data-menu-item-field="label"
                   placeholder="{{ $item->link_type === 'article' ? __('Leave blank to sync with the page title') : '' }}" aria-label="{{ __('Label') }}">
        </td>
        <td class="small text-muted text-nowrap">{{ $targetDesc }}</td>
        <td style="width:12rem">
            <select class="form-select form-select-sm" multiple size="2" data-menu-item-field="roles" aria-label="{{ __('Restrict to roles') }}">
                @foreach($roleNames as $role)
                    <option value="{{ $role }}" @selected(in_array($role, $item->roles ?? [], true))>{{ $role }}</option>
                @endforeach
            </select>
        </td>
    @endif
    <td style="width:4rem"><input type="number" class="form-control form-control-sm" value="{{ $item->sort_order }}" data-menu-item-field="sort_order" aria-label="{{ __('Order') }}"></td>
    <td class="text-end text-nowrap" style="width:5rem">
        <span class="text-success small me-1 d-none" data-menu-item-saved>@icon('✓')</span>
        <button type="button" class="btn btn-sm btn-outline-danger" data-menu-item-delete data-url="{{ route('admin.menu-items.destroy', $item) }}"
                data-confirm="{{ __('Remove this menu item?') }}">@icon('🗑️')</button>
    </td>
</tr>
