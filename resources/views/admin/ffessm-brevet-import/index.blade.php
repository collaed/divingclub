<x-admin-layout :title="__('FFESSM Brevets Import')">
    <h4>@icon('🏅') {{ __('FFESSM Brevets Import') }}</h4>
    <p class="text-muted small">
        {{ __('Upload one or more "Liste des brevets" exports from the FFESSM club portal. Each row updates the matching member\'s certification history (date obtained) and FFESSM licence number — a name that doesn\'t match exactly one existing member, or a level we don\'t recognise, is left untouched and reported below for you to handle by hand.') }}
    </p>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning py-2 small">{{ session('warning') }}</div>
    @endif
    @error('files')
        <div class="alert alert-danger py-2 small">{{ $message }}</div>
    @enderror

    <div class="card dc-card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.ffessm-brevet-import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="brevets-files">{{ __('Brevets export file(s) (.xlsx)') }}</label>
                    <input type="file" id="brevets-files" name="files[]" class="form-control @error('files.*') is-invalid @enderror" accept=".xlsx" multiple required>
                    @error('files.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">@icon('📥') {{ __('Import') }}</button>
            </form>
        </div>
    </div>
</x-admin-layout>
