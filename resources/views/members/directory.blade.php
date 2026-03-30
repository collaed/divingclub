<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>
    <div class="mb-4 position-relative">
        <input type="text" id="memberSearch" class="form-control pe-5" placeholder="{{ __('Search by name...') }}" value="{{ request('search') }}" autofocus>
        <button type="button" id="clearSearch" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted" style="display:none;font-size:1.2rem;text-decoration:none">&times;</button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
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
