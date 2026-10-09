<x-layout :title="__('Actions')">
    <style>
        {{-- A manually-added card (no source compte-rendu) reads as less "vetted"
             than one the AI extracted — a dashed, lighter outline says so at a
             glance without needing a label on every card. --}}
        .kanban-card-manual { border-style: dashed !important; border-color: var(--bs-secondary-border-subtle) !important; }
    </style>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-0">{{ __('Actions from the compte-rendus') }}</h4>
            <small class="text-muted">{{ __('Extracted automatically as meeting minutes are processed, a few hours apart. Check each card against its source before acting on it, and discard anything obsolete.') }}</small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#kanban-add-card">{{ __('+ Add a card') }}</button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div id="kanban-add-card" class="collapse mb-3 {{ $errors->any() || old('title') ? 'show' : '' }}">
        <div class="card dc-card">
            <div class="card-body">
                <form data-kanban-add-form action="{{ route('kanban.store') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Responsible') }}</label>
                        <input type="text" name="responsible" class="form-control" value="{{ old('responsible') }}" maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Context') }}</label>
                        <input type="text" name="context" class="form-control" value="{{ old('context') }}" maxlength="2000">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Add') }}</button>
                    </div>
                </form>
                <p class="small text-muted mt-2 mb-0">{{ __('Starts in "To do". Shown with a dashed outline to mark it as added by hand, not extracted from a compte-rendu.') }}</p>
            </div>
        </div>
    </div>

    <div class="row g-3" data-kanban-board data-todo-status="{{ $todoStatus }}">
        @foreach($columns as $status => $label)
            <div class="col-md-4">
                <div class="card dc-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $label }}</span>
                        <span class="badge bg-secondary" data-kanban-count>{{ $cards->get($status, collect())->count() }}</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-2" data-kanban-column="{{ $status }}">
                        @forelse($cards->get($status, collect()) as $card)
                            @include('kanban._card', ['card' => $card, 'columns' => $columns])
                        @empty
                            <p class="text-muted small mb-0" data-kanban-empty>{{ __('Nothing here yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const board = document.querySelector('[data-kanban-board]');
        if (!board || !csrf) { return; }

        function columnFor(status) {
            return board.querySelector('[data-kanban-column="' + status + '"]');
        }

        function refreshColumn(column) {
            const count = column.querySelectorAll('[data-kanban-card]').length;
            column.closest('.card').querySelector('[data-kanban-count]').textContent = count;
            let empty = column.querySelector('[data-kanban-empty]');
            if (count === 0 && !empty) {
                empty = document.createElement('p');
                empty.className = 'text-muted small mb-0';
                empty.setAttribute('data-kanban-empty', '');
                empty.textContent = '{{ __('Nothing here yet.') }}';
                column.appendChild(empty);
            } else if (count > 0 && empty) {
                empty.remove();
            }
        }

        function replaceCard(oldEl, html, targetColumn) {
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const newEl = wrap.firstElementChild;
            const originColumn = oldEl.closest('[data-kanban-column]');
            targetColumn.appendChild(newEl);
            oldEl.remove();
            refreshColumn(originColumn);
            if (originColumn !== targetColumn) { refreshColumn(targetColumn); }
        }

        board.addEventListener('click', function (e) {
            const moveBtn = e.target.closest('[data-kanban-move]');
            const discardBtn = e.target.closest('[data-kanban-discard]');
            if (!moveBtn && !discardBtn) { return; }

            const btn = moveBtn || discardBtn;
            const card = btn.closest('[data-kanban-card]');
            btn.disabled = true;

            if (moveBtn) {
                fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ status: btn.dataset.status }),
                }).then(async function (r) {
                    const data = await r.json().catch(function () { return {}; });
                    if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                    replaceCard(card, data.html, columnFor(btn.dataset.status));
                }).catch(function (err) {
                    btn.disabled = false;
                    if (typeof showToast === 'function') { showToast(err.message || '{{ __('Save failed') }}', 'danger'); }
                });
                return;
            }

            fetch(btn.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: '{}',
            }).then(async function (r) {
                const data = await r.json().catch(function () { return {}; });
                if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                const column = card.closest('[data-kanban-column]');
                card.remove();
                refreshColumn(column);
                if (typeof showToast === 'function') { showToast('{{ __('Card discarded.') }}', 'success'); }
            }).catch(function (err) {
                btn.disabled = false;
                if (typeof showToast === 'function') { showToast(err.message || '{{ __('Save failed') }}', 'danger'); }
            });
        });

        board.addEventListener('submit', function (e) {
            const form = e.target.closest('[data-kanban-comment-form]');
            if (!form) { return; }
            e.preventDefault();
            const input = form.querySelector('input[name="body"]');
            const body = input.value.trim();
            if (!body) { return; }
            const btn = form.querySelector('button');
            btn.disabled = true;

            fetch(form.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ body: body }),
            }).then(async function (r) {
                const data = await r.json().catch(function () { return {}; });
                if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                const list = form.closest('[data-kanban-card]').querySelector('[data-kanban-comments]');
                list.classList.add('border-top', 'mt-2', 'pt-2');
                const line = document.createElement('div');
                line.className = 'small mb-1';
                line.innerHTML = '<span class="dc-user-initials" style="width:20px;height:20px;font-size:0.65rem">'
                    + data.comment.author_initials + '</span> <span class="text-muted">' + data.comment.created_at + '</span> '
                    + data.comment.body;
                list.appendChild(line);
                input.value = '';
            }).catch(function (err) {
                if (typeof showToast === 'function') { showToast(err.message || '{{ __('Save failed') }}', 'danger'); }
            }).finally(function () { btn.disabled = false; });
        });

        const addForm = document.querySelector('[data-kanban-add-form]');
        addForm?.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = addForm.querySelector('button[type="submit"]');
            btn.disabled = true;

            fetch(addForm.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    title: addForm.querySelector('[name="title"]').value,
                    responsible: addForm.querySelector('[name="responsible"]').value,
                    context: addForm.querySelector('[name="context"]').value,
                }),
            }).then(async function (r) {
                const data = await r.json().catch(function () { return {}; });
                if (!r.ok || !data.ok) { throw new Error(data.message || ('HTTP ' + r.status)); }
                const column = columnFor(board.dataset.todoStatus);
                const wrap = document.createElement('div');
                wrap.innerHTML = data.html.trim();
                column.appendChild(wrap.firstElementChild);
                refreshColumn(column);
                addForm.reset();
                bootstrap.Collapse.getOrCreateInstance(document.getElementById('kanban-add-card')).hide();
                if (typeof showToast === 'function') { showToast('{{ __('Card added.') }}', 'success'); }
            }).catch(function (err) {
                if (typeof showToast === 'function') { showToast(err.message || '{{ __('Save failed') }}', 'danger'); }
            }).finally(function () { btn.disabled = false; });
        });
    })();
    </script>
    @endpush
</x-layout>
