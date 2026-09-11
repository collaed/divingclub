<x-admin-layout :title="__('Medical Certificate Review')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('🩺') {{ __('Medical Certificate Review') }}</h4>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#dc-preset-panel">@icon('⚙️') {{ __('Predefined comments') }}</button>
    </div>
    <p class="text-muted small">{{ __('Members with a newly-submitted medical certificate awaiting review. Compare it against their previous one, then validate, validate with a comment (shown to the member), or reject (the member is emailed the reason).') }}</p>

    <div class="collapse mb-3" id="dc-preset-panel">
        <div class="card dc-card">
            <div class="card-header">{{ __('Predefined comments') }}</div>
            <div class="list-group list-group-flush">
                @forelse($presets as $preset)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $preset->text }}</span>
                        <form method="POST" action="{{ route('admin.medical-review-comments.destroy', $preset) }}" data-confirm="{{ __('Remove this predefined comment?') }}" data-confirm-style="danger">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0 px-1">✕</button>
                        </form>
                    </div>
                @empty
                    <div class="list-group-item text-muted small">{{ __('No predefined comments yet.') }}</div>
                @endforelse
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.medical-review-comments.store') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="text" class="form-control form-control-sm" maxlength="500" placeholder="{{ __('e.g. Scan is illegible, please resubmit') }}" required>
                    <button class="btn btn-sm btn-primary text-nowrap">{{ __('Add') }}</button>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    @if($pending->isEmpty())
        <div class="card dc-card"><div class="card-body text-center py-5 text-muted">{{ __('Nothing to review — all caught up.') }}</div></div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Previous certificate') }}</th>
                        <th>{{ __('New certificate') }}</th>
                        <th style="min-width:320px">{{ __('Review') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $doc)
                        @php $previous = $doc->supersededBy; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.profile.show', $doc->user_id) }}" class="fw-medium text-decoration-none">{{ $doc->user->name }}</a>
                            </td>
                            <td class="small">
                                @if($previous)
                                    {{ $previous->date_established?->format('d/m/Y') ?? '—' }}<br>
                                    <a href="{{ route('profile.document.view', $previous) }}" target="_blank" rel="noopener">@icon('📄') {{ __('View') }}</a>
                                @else
                                    <span class="text-muted">{{ __('First submission') }}</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $doc->date_established?->format('d/m/Y') ?? '—' }}<br>
                                <a href="{{ route('profile.document.view', $doc) }}" target="_blank" rel="noopener">@icon('📄') {{ __('View') }}</a>
                            </td>
                            <td>
                                <form method="POST" class="dc-medical-review-form" data-form>
                                    @csrf
                                    <div class="input-group input-group-sm mb-1">
                                        <select class="form-select dc-preset-select" data-target="comment-{{ $doc->id }}">
                                            <option value="">{{ __('Predefined comment…') }}</option>
                                            @foreach($presets as $preset)
                                                <option value="{{ $preset->text }}">{{ $preset->text }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <textarea name="comment" id="comment-{{ $doc->id }}" class="form-control form-control-sm mb-1" rows="2" placeholder="{{ __('Comment (required for yellow/red)') }}"></textarea>
                                    <div class="d-flex gap-1">
                                        <button type="submit" formaction="{{ route('admin.medical-review.validate', $doc) }}" class="btn btn-sm btn-success flex-fill" title="{{ __('Validate') }}">@icon('✅') {{ __('Validate') }}</button>
                                        <button type="submit" formaction="{{ route('admin.medical-review.validate', $doc) }}" class="btn btn-sm btn-warning flex-fill dc-require-comment" title="{{ __('Validate with comments') }}">@icon('⚠️') {{ __('With comment') }}</button>
                                        <button type="submit" formaction="{{ route('admin.medical-review.reject', $doc) }}" class="btn btn-sm btn-danger flex-fill dc-require-comment" title="{{ __('Reject') }}">@icon('⛔') {{ __('Reject') }}</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $pending->links() }}
    @endif

    <script>
        // No inline handlers, event delegation per project JS convention.
        document.querySelectorAll('.dc-preset-select').forEach(function (select) {
            select.addEventListener('change', function () {
                if (!select.value) return;
                var target = document.getElementById(select.dataset.target);
                target.value = select.value;
            });
        });

        document.querySelectorAll('.dc-medical-review-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var submitter = e.submitter;
                if (!submitter || !submitter.classList.contains('dc-require-comment')) return;
                var comment = form.querySelector('textarea[name="comment"]');
                if (!comment.value.trim()) {
                    e.preventDefault();
                    comment.classList.add('is-invalid');
                    comment.focus();
                }
            });
        });
    </script>
</x-admin-layout>
