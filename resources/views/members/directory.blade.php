<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>
    <div class="mb-4">
        <input type="text" id="memberSearch" class="form-control" placeholder="{{ __('Search by name...') }}" value="{{ request('search') }}" autofocus>
    </div>

    @php
        $sort = request('sort', 'last_name');
        $dir = request('dir', 'asc');
        $nextDir = fn($col) => $sort === $col && $dir === 'asc' ? 'desc' : 'asc';
        $arrow = fn($col) => $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        $sortUrl = fn($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir($col)]);
    @endphp

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th></th>
                    <th><a href="{{ $sortUrl('last_name') }}" class="text-decoration-none text-body">{{ __('Name') }}{!! $arrow('last_name') !!}</a></th>
                    <th><a href="{{ $sortUrl('certification_level') }}" class="text-decoration-none text-body">{{ __('Level') }}{!! $arrow('certification_level') !!}</a></th>
                    <th>{{ __('Status') }}</th>
                    <th><a href="{{ $sortUrl('adhesion_year') }}" class="text-decoration-none text-body">{{ __('Member Since') }}{!! $arrow('adhesion_year') !!}</a></th>
                </tr>
            </thead>
            <tbody id="memberRows">
                @include('members._directory_rows')
            </tbody>
        </table>
    </div>
    <div id="memberPagination">{{ $members->links() }}</div>

@push('scripts')
<script>
(function(){
    let timer, input = document.getElementById('memberSearch');
    input.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch('{{ route("members.directory") }}?search=' + encodeURIComponent(this.value), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(r => r.text())
            .then(html => {
                document.getElementById('memberRows').innerHTML = html;
                document.getElementById('memberPagination').innerHTML = '';
                history.replaceState(null, '', this.value ? '?search=' + encodeURIComponent(this.value) : location.pathname);
            });
        }, 300);
    });
})();
</script>
@endpush
</x-layout>
