<x-admin-layout :title="__('Federations')">
    <h4>@icon('🎖️') {{ __('Federations') }}</h4>
    <p class="text-muted small">
        {{ __('Diving federations the club recognises, and the certification levels each one issues. Visibility controls where a federation appears: :active pre-selected in the certification picker, :recognized available but unchecked, :invisible hidden.', [
            'active' => __('Active'), 'recognized' => __('Recognized'), 'invisible' => __('Invisible'),
        ]) }}
    </p>

    @error('fed')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

    <form id="fed-bulk" method="POST" action="{{ route('admin.federations.bulk-update') }}">@csrf @method('PUT')</form>
    <div class="card dc-card mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:8rem">{{ __('Acronym') }}</th>
                        <th>{{ __('Full Name') }}</th>
                        <th style="width:11rem">{{ __('Visibility') }}</th>
                        <th class="text-end" style="width:11rem">{{ __('Levels') }}</th>
                        <th style="width:3rem"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($federations as $fed)
                    <tr>
                        <td><input type="text" form="fed-bulk" name="fed[{{ $fed->id }}][acronym]" class="form-control form-control-sm" value="{{ $fed->acronym }}" required></td>
                        <td><input type="text" form="fed-bulk" name="fed[{{ $fed->id }}][full_name]" class="form-control form-control-sm" value="{{ $fed->full_name }}" required></td>
                        <td>
                            <select form="fed-bulk" name="fed[{{ $fed->id }}][visibility]" class="form-select form-select-sm">
                                <option value="active" @selected($fed->visibility === 'active')>{{ __('Active') }}</option>
                                <option value="recognized" @selected($fed->visibility === 'recognized')>{{ __('Recognized') }}</option>
                                <option value="invisible" @selected($fed->visibility === 'invisible')>{{ __('Invisible') }}</option>
                            </select>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.federations.show', $fed) }}" class="btn btn-sm btn-outline-primary">
                                {{ trans_choice('{0}No levels|{1}:count level|[2,*]:count levels', $fed->certification_levels_count, ['count' => $fed->certification_levels_count]) }} →
                            </a>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.federations.destroy', $fed) }}" class="d-inline"
                                  data-confirm="{{ __('Delete :name and all its certification levels?', ['name' => $fed->acronym]) }}"
                                  data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" @disabled($fed->licences_count > 0)
                                        title="{{ $fed->licences_count > 0 ? __(':n member licence(s) reference this federation.', ['n' => $fed->licences_count]) : __('Delete') }}">✕</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body py-2">
            <button type="submit" form="fed-bulk" class="btn btn-sm btn-primary">{{ __('Save all federations') }}</button>
        </div>
    </div>

    <div class="card dc-card">
        <div class="card-header">{{ __('Add Federation') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.federations.store') }}" class="row g-2">
                @csrf
                <div class="col-md-2"><input type="text" name="acronym" class="form-control form-control-sm" placeholder="{{ __('Acronym') }}" required></div>
                <div class="col-md-6"><input type="text" name="full_name" class="form-control form-control-sm" placeholder="{{ __('Full Name') }}" required></div>
                <div class="col-md-2">
                    <select name="visibility" class="form-select form-select-sm">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="recognized">{{ __('Recognized') }}</option>
                        <option value="invisible">{{ __('Invisible') }}</option>
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
            </form>
        </div>
    </div>
</x-admin-layout>
