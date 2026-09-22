<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>

    <form method="GET" action="{{ route('members.directory') }}" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text" name="search" data-instant-search="members-table" class="form-control form-control-sm" placeholder="{{ __('Search by name…') }}" value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Statuses') }}</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->id }}" {{ request('status') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="instructor" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Members') }}</option>
                <option value="1" {{ request('instructor') === '1' ? 'selected' : '' }}>{{ __('Instructors') }}</option>
                <option value="0" {{ request('instructor') === '0' ? 'selected' : '' }}>{{ __('Non-instructors') }}</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="age" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Ages') }}</option>
                @foreach(['u12' => __('< 12'), 'u14' => __('< 14'), 'u16' => __('< 16'), 'u18' => __('< 18'), 'o18' => __('18 and over')] as $v => $l)
                    <option value="{{ $v }}" {{ request('age') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="level" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All Levels') }}</option>
                @foreach($levels as $l)
                    <option value="{{ $l }}" {{ request('level') === $l ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-sm btn-outline-primary">{{ __('Search') }}</button>
            @if(request()->hasAny(['search', 'status', 'instructor', 'age', 'level']))
                <a href="{{ route('members.directory') }}" class="btn btn-sm btn-outline-secondary">✕</a>
            @endif
            <span class="text-muted small ms-2">{{ $members->total() }} {{ __('members') }}</span>
        </div>
    </form>

    <x-table stack id="members-table" class="table-hover">
        <thead>
            <tr>
                <th></th>
                <th><x-sortable-th column="last_name" :label="__('Name')" /></th>
                <th><x-sortable-th column="certification_level" :label="__('Level')" /></th>
                <th>{{ __('Status') }}</th>
                <th><x-sortable-th column="adhesion_year" :label="__('Member Since')" /></th>
            </tr>
        </thead>
        <tbody id="memberRows">
            @include('members._directory_rows')
        </tbody>
    </x-table>
    <div id="memberPagination">{{ $members->links() }}</div>
</x-layout>
