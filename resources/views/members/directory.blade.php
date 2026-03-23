<x-layout :title="__('Members Directory')">
    <h4 class="mb-3">{{ __('Members Directory') }}</h4>
    <div class="mb-4">
        <input type="text" id="memberSearch" class="form-control" placeholder="{{ __('Search by name...') }}" value="{{ request('search') }}" autofocus>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th></th><th>{{ __('Name') }}</th><th>{{ __('Level') }}</th><th>{{ __('Status') }}</th><th>{{ __('Member Since') }}</th></tr></thead>
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
