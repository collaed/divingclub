<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="{{ __('Search by name...') }}" value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary">{{ __('Search') }}</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th></th><th>{{ __('Name') }}</th><th>{{ __('Level') }}</th><th>{{ __('Status') }}</th><th>{{ __('Member Since') }}</th></tr></thead>
            <tbody>
            @foreach($members as $m)
                <tr>
                    <td style="width:50px">
                        @if($m->detail?->avatar_path)
                            <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                        @else
                            <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                <span class="text-white small">{{ strtoupper(substr($m->detail?->first_name ?? '?', 0, 1) . substr($m->detail?->last_name ?? '', 0, 1)) }}</span>
                            </div>
                        @endif
                    </td>
                    <td>{{ $m->detail?->first_name }} {{ $m->detail?->last_name }}</td>
                    <td>{{ $m->detail?->certification_level ?? '—' }}</td>
                    <td>{{ $m->status?->name ?? '—' }}</td>
                    <td>{{ $m->detail?->adhesion_year ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $members->links() }}
</x-layout>
