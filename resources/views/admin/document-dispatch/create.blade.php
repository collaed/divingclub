<x-admin-layout :title="__('New tracked send')">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('admin.document-dispatch.index') }}">{{ __('Tracked documents') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('New send') }}</li>
        </ol>
    </nav>

    <h4>@icon('📤') {{ __('New tracked send') }}</h4>

    @if($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    @php($statuses = $members->pluck('status.name')->filter()->unique()->sort()->values())

    <form method="POST" action="{{ route('admin.document-dispatch.store') }}">
        @csrf

        <div class="card dc-card mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Document (PDF from the library)') }}</label>
                    <select name="library_file_id" class="form-select" required>
                        <option value="">{{ __('— choose —') }}</option>
                        @foreach($files as $f)
                            <option value="{{ $f->id }}" @selected(old('library_file_id') == $f->id)>{{ $f->original_name }}{{ $f->folder ? ' · '.$f->folder : '' }}</option>
                        @endforeach
                    </select>
                    @if($files->isEmpty())<div class="form-text text-danger">{{ __('No PDF in the library yet — upload one first.') }}</div>@endif
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Email subject') }}</label>
                    <input type="text" name="subject" class="form-control" maxlength="255" value="{{ old('subject') }}" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">{{ __('Message') }} <span class="text-muted small">({{ __('optional') }})</span></label>
                    <textarea name="message" class="form-control" rows="4" maxlength="5000">{{ old('message') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card dc-card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>{{ __('Recipients') }} <span class="badge bg-secondary" id="dd-count">0</span></span>
                <span class="small">
                    {{ __('Filter:') }}
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-dd-all>{{ __('All') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-dd-none>{{ __('None') }}</button>
                    @foreach($statuses as $s)
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-dd-status="{{ $s }}">{{ $s }}</button>
                    @endforeach
                </span>
            </div>
            <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                <div class="row row-cols-1 row-cols-md-2 g-1">
                    @foreach($members as $m)
                        <div class="col">
                            <label class="d-flex align-items-center gap-2 small">
                                <input type="checkbox" name="recipients[]" value="{{ $m->id }}" class="form-check-input mt-0" data-dd-row data-status="{{ $m->status?->name }}">
                                <span>{{ $m->name }} <span class="text-muted">· {{ $m->primary_email }}</span>@if($m->status)<span class="badge bg-light text-muted ms-1">{{ $m->status->name }}</span>@endif</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" @disabled($files->isEmpty())>{{ __('Send') }}</button>
        <a href="{{ route('admin.document-dispatch.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </form>

    <script>
        (function () {
            const rows = Array.from(document.querySelectorAll('[data-dd-row]'));
            const count = document.getElementById('dd-count');
            const refresh = () => count.textContent = rows.filter(r => r.checked).length;
            rows.forEach(r => r.addEventListener('change', refresh));
            document.querySelectorAll('[data-dd-all]').forEach(b => b.addEventListener('click', () => { rows.forEach(r => r.checked = true); refresh(); }));
            document.querySelectorAll('[data-dd-none]').forEach(b => b.addEventListener('click', () => { rows.forEach(r => r.checked = false); refresh(); }));
            document.querySelectorAll('[data-dd-status]').forEach(b => b.addEventListener('click', () => {
                const s = b.dataset.ddStatus;
                rows.forEach(r => { if (r.dataset.status === s) r.checked = true; });
                refresh();
            }));
            refresh();
        })();
    </script>
</x-admin-layout>
