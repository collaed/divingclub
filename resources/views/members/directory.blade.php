<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>

    <form method="GET" action="{{ route('members.directory') }}" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text" name="search" data-instant-search="members-table" class="form-control form-control-sm" placeholder="{{ __('Search by name…') }}" value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <select name="instructor" class="form-select form-select-sm" data-autosubmit>
                <option value="">{{ __('All Members') }}</option>
                <option value="1" {{ request('instructor') === '1' ? 'selected' : '' }}>{{ __('Instructors') }}</option>
                <option value="0" {{ request('instructor') === '0' ? 'selected' : '' }}>{{ __('Non-instructors') }}</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="age" class="form-select form-select-sm" data-autosubmit>
                <option value="">{{ __('All Age Categories') }}</option>
                @foreach(['u12' => __('< 12'), 'u14' => __('< 14'), 'u16' => __('< 16'), 'u18' => __('< 18'), 'o18' => __('18 and over')] as $v => $l)
                    <option value="{{ $v }}" {{ request('age') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="scuba_level_id" class="form-select form-select-sm" data-autosubmit>
                <option value="">{{ __('All Scuba Levels') }}</option>
                @foreach($scubaLevels as $fedAcronym => $levelsForFed)
                    <optgroup label="{{ $fedAcronym }}">
                        @foreach($levelsForFed as $l)
                            <option value="{{ $l->id }}" {{ (string) request('scuba_level_id') === (string) $l->id ? 'selected' : '' }}>{{ $l->code }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="freediving_level_id" class="form-select form-select-sm" data-autosubmit>
                <option value="">{{ __('All Freediving Levels') }}</option>
                @foreach($freedivingLevels as $fedAcronym => $levelsForFed)
                    <optgroup label="{{ $fedAcronym }}">
                        @foreach($levelsForFed as $l)
                            <option value="{{ $l->id }}" {{ (string) request('freediving_level_id') === (string) $l->id ? 'selected' : '' }}>{{ $l->code }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-sm btn-outline-primary">{{ __('Search') }}</button>
            @if(request()->hasAny(['search', 'instructor', 'age', 'scuba_level_id', 'freediving_level_id']))
                <a href="{{ route('members.directory') }}" class="btn btn-sm btn-outline-secondary">✕</a>
            @endif
            <span class="text-muted small ms-2">{{ $members->total() }} {{ __('members') }}</span>
        </div>
    </form>

    <div id="members-table-results">
    <x-table stack id="members-table" class="table-hover">
        <thead>
            <tr>
                <th></th>
                <th><x-sortable-th column="last_name" :label="__('Name')" /></th>
                <th>{{ __('Scuba') }}</th>
                <th>{{ __('Freediving') }}</th>
                <th>{{ __('Nationality') }}</th>
                <th>{{ __('Age Category') }}</th>
                <th><x-sortable-th column="adhesion_year" :label="__('Member Since')" /></th>
            </tr>
        </thead>
        <tbody id="memberRows">
            @include('members._directory_rows')
        </tbody>
    </x-table>
    <div id="memberPagination">{{ $members->links() }}</div>
    </div>
</x-layout>
