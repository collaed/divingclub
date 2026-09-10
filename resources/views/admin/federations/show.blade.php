<x-admin-layout :title="$federation->acronym . ' — ' . __('Certification Levels')">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('admin.federations.index') }}">{{ __('Federations') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $federation->acronym }}</li>
        </ol>
    </nav>

    <h4>@icon('🎖️') {{ $federation->acronym }} <span class="text-muted fw-normal fs-6">— {{ $federation->full_name }}</span></h4>
    <p class="text-muted small">
        {{ __('Certification levels this federation issues. :rank orders them within a category (low → high). :group links equivalent levels across federations (e.g. cmas_2s) so the app can compare qualifications.', [
            'rank' => __('Rank'), 'group' => __('Equivalence group'),
        ]) }}
    </p>

    @error('level')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

    <form id="lvl-bulk" method="POST" action="{{ route('admin.federations.levels.bulk-update', $federation) }}">@csrf @method('PUT')</form>
    <div class="card dc-card mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
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
                        <td><input type="text" form="lvl-bulk" name="lvl[{{ $lvl->id }}][code]" class="form-control form-control-sm" value="{{ $lvl->code }}" required></td>
                        <td><input type="text" form="lvl-bulk" name="lvl[{{ $lvl->id }}][name]" class="form-control form-control-sm" value="{{ $lvl->name }}" required></td>
                        <td>
                            <select form="lvl-bulk" name="lvl[{{ $lvl->id }}][category]" class="form-select form-select-sm">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected($lvl->category === $cat)>{{ __(ucfirst($cat)) }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" form="lvl-bulk" name="lvl[{{ $lvl->id }}][rank]" class="form-control form-control-sm" value="{{ $lvl->rank }}" min="0" required></td>
                        <td><input type="text" form="lvl-bulk" name="lvl[{{ $lvl->id }}][equivalence_group]" class="form-control form-control-sm" value="{{ $lvl->equivalence_group }}" placeholder="—"></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.federations.levels.destroy', [$federation, $lvl]) }}" class="d-inline"
                                  data-confirm="{{ __('Delete :code?', ['code' => $lvl->code]) }}" data-confirm-style="danger" data-confirm-btn="{{ __('Confirm') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" @disabled($lvl->users_count > 0)
                                        title="{{ $lvl->users_count > 0 ? __(':n member(s) hold this level.', ['n' => $lvl->users_count]) : __('Delete') }}">✕</button>
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
            <div class="card-body py-2">
                <button type="submit" form="lvl-bulk" class="btn btn-sm btn-primary">{{ __('Save all levels') }}</button>
            </div>
        @endif
    </div>

    <div class="card dc-card">
        <div class="card-header">{{ __('Add Certification Level') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.federations.levels.store', $federation) }}" class="row g-2">
                @csrf
                <div class="col-md-2"><input type="text" name="code" class="form-control form-control-sm" placeholder="{{ __('Code') }}" required></div>
                <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Name') }}" required></div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        @foreach($categories as $cat)<option value="{{ $cat }}">{{ __(ucfirst($cat)) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-1"><input type="number" name="rank" class="form-control form-control-sm" placeholder="{{ __('Rank') }}" value="0" min="0" required></div>
                <div class="col-md-2"><input type="text" name="equivalence_group" class="form-control form-control-sm" placeholder="{{ __('Equiv. group') }}"></div>
                <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
            </form>
        </div>
    </div>
</x-admin-layout>
