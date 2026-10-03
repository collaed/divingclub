@php($fid = 'lvl-bulk-'.$federation->id)
<form id="{{ $fid }}" method="POST" action="{{ route('admin.federations.levels.bulk-update', $federation) }}">@csrf @method('PUT')</form>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-2">
        <thead>
            <tr>
                <th style="width:8rem">{{ __('Code') }}</th>
                <th>{{ __('Name') }}</th>
                <th style="width:9rem">{{ __('Category') }}</th>
                <th style="width:6rem">{{ __('Rank') }}</th>
                <th style="width:11rem">{{ __('Equivalence group') }}</th>
                <th style="width:3rem"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($levels as $lvl)
            <tr>
                <td><input type="text" form="{{ $fid }}" name="lvl[{{ $lvl->id }}][code]" class="form-control form-control-sm" value="{{ $lvl->code }}" aria-label="{{ __('Code') }}" required></td>
                <td><input type="text" form="{{ $fid }}" name="lvl[{{ $lvl->id }}][name]" class="form-control form-control-sm" value="{{ $lvl->name }}" aria-label="{{ __('Name') }}" required></td>
                <td>
                    <select form="{{ $fid }}" name="lvl[{{ $lvl->id }}][category]" class="form-select form-select-sm" aria-label="{{ __('Category') }}">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" @selected($lvl->category === $cat)>{{ __(ucfirst($cat)) }}</option>
                        @endforeach
                    </select>
                </td>
                <td><input type="number" form="{{ $fid }}" name="lvl[{{ $lvl->id }}][rank]" class="form-control form-control-sm" value="{{ $lvl->rank }}" min="0" aria-label="{{ __('Rank') }}" required></td>
                <td><input type="text" form="{{ $fid }}" name="lvl[{{ $lvl->id }}][equivalence_group]" class="form-control form-control-sm" value="{{ $lvl->equivalence_group }}" placeholder="—" aria-label="{{ __('Equivalence group') }}"></td>
                <td class="text-end">
                    <form method="POST" action="{{ route('admin.federations.levels.destroy', [$federation, $lvl]) }}" class="d-inline"
                          data-confirm="{{ __('Delete :code?', ['code' => $lvl->code]) }}" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" @disabled(($lvl->users_count ?? 0) > 0)
                                title="{{ ($lvl->users_count ?? 0) > 0 ? __(':n member(s) hold this level.', ['n' => $lvl->users_count]) : __('Delete') }}">✕</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No certification levels yet. Add the first one below.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($levels->isNotEmpty())
    <button type="submit" form="{{ $fid }}" class="btn btn-sm btn-primary mb-3">{{ __('Save all levels') }}</button>
@endif

<form method="POST" action="{{ route('admin.federations.levels.store', $federation) }}" class="row g-2">
    @csrf
    <div class="col-md-2"><input type="text" name="code" class="form-control form-control-sm" placeholder="{{ __('Code') }}" aria-label="{{ __('Code') }}" required></div>
    <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Name') }}" aria-label="{{ __('Name') }}" required></div>
    <div class="col-md-2">
        <select name="category" class="form-select form-select-sm" aria-label="{{ __('Category') }}">
            @foreach($categories as $cat)<option value="{{ $cat }}">{{ __(ucfirst($cat)) }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-1"><input type="number" name="rank" class="form-control form-control-sm" placeholder="{{ __('Rank') }}" value="0" min="0" aria-label="{{ __('Rank') }}" required></div>
    <div class="col-md-2"><input type="text" name="equivalence_group" class="form-control form-control-sm" placeholder="{{ __('Equiv. group') }}" aria-label="{{ __('Equivalence group') }}"></div>
    <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
</form>
