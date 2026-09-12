<x-admin-layout :title="__('Licence Scans')">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">@icon('🪪') {{ __('Licence Scans') }}</h4>
    </div>
    <p class="text-muted small">{{ __('Dump a batch of licence card scans; each one is read automatically and applied straight to the member when the name matches exactly one person. Anything else lands below for you to assign by hand.') }}</p>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @error('files.*')
        <div class="alert alert-danger py-2 small">{{ $message }}</div>
    @enderror

    <div class="card dc-card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.licence-scans.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small mb-1">{{ __('Federation') }}</label>
                    <select name="federation_id" class="form-select form-select-sm" required>
                        @foreach($federations as $federation)
                            <option value="{{ $federation->id }}">{{ $federation->acronym }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label class="form-label small mb-1">{{ __('Scans (PDF or image, one per member)') }}</label>
                    <div class="dc-dropzone rounded d-flex align-items-center justify-content-center gap-2 text-muted small" style="border: 2px dashed #ccc;" tabindex="0" role="button">
                        <span>@icon('📥')</span>
                        <span class="dc-dropzone-label">{{ __('Drop files here, or click to browse') }}</span>
                        <input type="file" name="files[]" class="dc-dropzone-input visually-hidden" accept="application/pdf,image/jpeg,image/png" multiple required>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">@icon('⬆️') {{ __('Upload & process') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="needsReviewSection">
        @if($needsReview->isEmpty())
            <div class="card dc-card"><div class="card-body text-center py-5 text-muted">{{ __('Nothing waiting for review.') }}</div></div>
        @else
            <h6>{{ __('Needs review') }}</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Scan') }}</th>
                            <th>{{ __('Federation') }}</th>
                            <th>{{ __('Extracted') }}</th>
                            <th style="min-width:350px">{{ __('Assign to') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($needsReview as $scan)
                            <tr data-scan-id="{{ $scan->id }}">
                                <td>
                                    @if($scan->image_path)
                                        <a href="{{ route('admin.licence-scans.image', $scan) }}" target="_blank" rel="noopener">
                                            <img src="{{ route('admin.licence-scans.image', $scan) }}" alt="" style="max-width:80px; max-height:52px; object-fit:cover; border-radius:4px;">
                                        </a>
                                    @else
                                        <span class="text-muted small">{{ __('No preview') }}</span>
                                    @endif
                                    <div class="small text-muted">{{ $scan->original_filename }}</div>
                                    @if($scan->extraction_error)
                                        <div class="small text-danger">{{ $scan->extraction_error }}</div>
                                    @endif
                                </td>
                                <td>{{ $scan->federation->acronym }}</td>
                                <td class="small">
                                    {{ $scan->extracted_name ?? __('(no name read)') }}<br>
                                    @if($scan->extracted_number) <strong>#{{ $scan->extracted_number }}</strong> @endif
                                    @if($scan->extracted_year) <span class="text-muted">{{ $scan->extracted_year }}</span> @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.licence-scans.assign', $scan) }}" class="d-flex flex-column gap-2">
                                        @csrf
                                        <div class="d-flex flex-wrap gap-1">
                                            <input type="text" list="dc-members-list" class="form-control form-control-sm dc-member-picker" placeholder="{{ __('Search member…') }}" style="min-width:160px" value="{{ $scan->extracted_name }}" autocomplete="off">
                                            <input type="hidden" name="user_id" class="dc-member-id">
                                            <input type="text" name="licence_number" class="form-control form-control-sm" style="width:90px" placeholder="{{ __('Number') }}" value="{{ $scan->extracted_number }}">
                                            <input type="text" name="year" class="form-control form-control-sm" style="width:70px" placeholder="{{ __('Year') }}" value="{{ $scan->extracted_year }}">
                                            <button type="submit" class="btn btn-sm btn-success flex-shrink-0">@icon('✅') {{ __('Assign') }}</button>
                                        </div>
                                        <div class="dc-fuzzy-suggestions small text-muted" style="display:none;"></div>
                                    </form>
                                    <form method="POST" action="{{ route('admin.licence-scans.destroy', $scan) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">@icon('🗑️') {{ __('Discard') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $needsReview->links() }}
        @endif
    </div>

    <datalist id="dc-members-list">
        @foreach($members as $member)
            <option data-id="{{ $member['id'] }}" value="{{ $member['name'] }}"></option>
        @endforeach
    </datalist>

    <style>
        .dc-dropzone { min-height: 38px; padding: 6px 10px; cursor: pointer; transition: border-color .15s, color .15s; }
        .dc-dropzone.dc-dropzone-active { border-color: #0d6efd !important; color: #0d6efd; }
        .dc-dropzone.dc-dropzone-filled { border-color: #198754 !important; color: #198754; }
    </style>

    <script>
        // Dropzone handler
        document.querySelectorAll('.dc-dropzone').forEach(function (zone) {
            var input = zone.querySelector('.dc-dropzone-input');
            var label = zone.querySelector('.dc-dropzone-label');
            var defaultLabel = label.textContent;

            var updateLabel = function () {
                var n = input.files.length;
                zone.classList.toggle('dc-dropzone-filled', n > 0);
                label.textContent = n > 0 ? n + ' ' + '{{ __('file(s) selected') }}' : defaultLabel;
            };

            zone.addEventListener('click', function () { input.click(); });
            zone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
            });
            input.addEventListener('change', updateLabel);

            ['dragenter', 'dragover'].forEach(function (evt) {
                zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('dc-dropzone-active'); });
            });
            ['dragleave', 'drop'].forEach(function (evt) {
                zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.remove('dc-dropzone-active'); });
            });
            zone.addEventListener('drop', function (e) {
                input.files = e.dataTransfer.files;
                updateLabel();
            });
        });

        // Member picker with fuzzy matching
        function levenshtein(a, b) {
            var m = a.length, n = b.length;
            var dp = Array(n + 1).fill(null).map(() => Array(m + 1).fill(0));
            for (var i = 0; i <= m; i++) dp[i][0] = i;
            for (var j = 0; j <= n; j++) dp[0][j] = j;
            for (var i = 1; i <= m; i++) {
                for (var j = 1; j <= n; j++) {
                    dp[i][j] = Math.min(
                        dp[i-1][j] + 1,
                        dp[i][j-1] + 1,
                        dp[i-1][j-1] + (a[i-1] !== b[j-1] ? 1 : 0)
                    );
                }
            }
            return dp[m][n];
        }

        function fuzzyMatch(query, target) {
            query = query.toUpperCase().trim();
            target = target.toUpperCase().trim();
            if (!query) return 100;
            if (target.indexOf(query) !== -1) return 100;
            var dist = levenshtein(query, target);
            var maxLen = Math.max(query.length, target.length);
            return Math.round((1 - dist / maxLen) * 100);
        }

        document.querySelectorAll('.dc-member-picker').forEach(function (input) {
            var form = input.closest('form');
            var hidden = form.querySelector('.dc-member-id');
            var suggestions = form.querySelector('.dc-fuzzy-suggestions');
            var membersList = Array.from(document.querySelectorAll('#dc-members-list option')).map(function (opt) {
                return { id: opt.dataset.id, name: opt.value };
            });

            var resolve = function () {
                var query = input.value.trim();
                if (!query) {
                    hidden.value = '';
                    suggestions.style.display = 'none';
                    return;
                }

                var matches = membersList.map(function (m) {
                    return { ...m, score: fuzzyMatch(query, m.name) };
                }).filter(function (m) { return m.score >= 70; }).sort(function (a, b) { return b.score - a.score; });

                if (matches.length === 0) {
                    hidden.value = '';
                    suggestions.style.display = 'none';
                } else if (matches[0].score === 100) {
                    // Exact match — auto-select
                    hidden.value = matches[0].id;
                    suggestions.style.display = 'none';
                } else if (matches[0].score >= 80) {
                    // High confidence — suggest it
                    hidden.value = matches[0].id;
                    var html = '<strong>💡 Suggested:</strong> ' + matches.slice(0, 3).map(function (m) {
                        return m.name + ' (' + m.score + '%)' + (m.id === matches[0].id ? ' <em>selected</em>' : '');
                    }).join(', ');
                    suggestions.innerHTML = html;
                    suggestions.style.display = 'block';
                } else {
                    hidden.value = '';
                    suggestions.style.display = 'none';
                }
            };

            input.addEventListener('input', resolve);
            input.addEventListener('blur', function () { setTimeout(resolve, 50); });
            resolve();
        });

        // Live refresh: poll for new scans every 3 seconds (only if no form has focus)
        setInterval(function () {
            // Don't refresh if user is actively editing a form
            if (document.activeElement && document.activeElement.closest('#needsReviewSection form')) {
                return;
            }
            fetch(window.location.href).then(function (r) { return r.text(); }).then(function (html) {
                var newSection = new DOMParser().parseFromString(html, 'text/html').querySelector('#needsReviewSection');
                var oldSection = document.getElementById('needsReviewSection');
                if (!newSection || !oldSection) return;

                // Count scans in old and new HTML
                var oldCount = oldSection.querySelectorAll('[data-scan-id]').length;
                var newCount = newSection.querySelectorAll('[data-scan-id]').length;

                // Only refresh if there are new scans (processing finished)
                if (newCount > oldCount) {
                    oldSection.innerHTML = newSection.innerHTML;
                    // Re-bind handlers to new elements
                    document.querySelectorAll('.dc-member-picker').forEach(function (input) {
                        var form = input.closest('form');
                        var hidden = form.querySelector('.dc-member-id');
                        var suggestions = form.querySelector('.dc-fuzzy-suggestions');
                        var membersList = Array.from(document.querySelectorAll('#dc-members-list option')).map(function (opt) {
                            return { id: opt.dataset.id, name: opt.value };
                        });
                        var resolve = function () {
                            var query = input.value.trim();
                            if (!query) { hidden.value = ''; suggestions.style.display = 'none'; return; }
                            var matches = membersList.map(function (m) {
                                return { ...m, score: fuzzyMatch(query, m.name) };
                            }).filter(function (m) { return m.score >= 70; }).sort(function (a, b) { return b.score - a.score; });
                            if (matches.length === 0) { hidden.value = ''; suggestions.style.display = 'none'; }
                            else if (matches[0].score === 100) { hidden.value = matches[0].id; suggestions.style.display = 'none'; }
                            else if (matches[0].score >= 80) { hidden.value = matches[0].id; suggestions.innerHTML = '<strong>💡 Suggested:</strong> ' + matches.slice(0, 3).map(function (m) { return m.name + ' (' + m.score + '%)' + (m.id === matches[0].id ? ' <em>selected</em>' : ''); }).join(', '); suggestions.style.display = 'block'; }
                            else { hidden.value = ''; suggestions.style.display = 'none'; }
                        };
                        input.addEventListener('input', resolve);
                        input.addEventListener('blur', function () { setTimeout(resolve, 50); });
                        resolve();
                    });
                }
            }).catch(function () { /* Silently fail on network error */ });
        }, 3000);
    </script>
</x-admin-layout>
