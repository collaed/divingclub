<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>
    <div class="mb-4 position-relative">
        <input type="text" id="memberSearch" class="form-control pe-5" placeholder="{{ __('Search by name...') }}" value="{{ request('search') }}" autofocus>
        <button type="button" id="clearSearch" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" style="display:none;font-size:1.2rem;text-decoration:none">&times;</button>
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
    let timer, input = document.getElementById('memberSearch'),
        clearBtn = document.getElementById('clearSearch');

    function toggleClear() { clearBtn.style.display = input.value ? 'block' : 'none'; }
    function doSearch() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch('{{ route("members.directory") }}?search=' + encodeURIComponent(input.value), {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(r => r.text())
            .then(html => {
                document.getElementById('memberRows').innerHTML = html;
                document.getElementById('memberPagination').innerHTML = '';
                history.replaceState(null, '', input.value ? '?search=' + encodeURIComponent(input.value) : location.pathname);
            });
        }, 300);
    }

    input.addEventListener('input', function(){ toggleClear(); doSearch(); });
    clearBtn.addEventListener('click', function(){ input.value = ''; toggleClear(); doSearch(); input.focus(); });
    toggleClear();
})();
</script>
@endpush
</x-layout>
