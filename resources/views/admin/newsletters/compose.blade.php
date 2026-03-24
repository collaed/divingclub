<x-layout :title="$newsletter ? __('Edit Newsletter') : __('New Newsletter')">
    <h4 class="mb-4">📬 {{ $newsletter ? __('Edit Newsletter') : __('Compose Newsletter') }}</h4>

    <form method="POST"
          action="{{ $newsletter ? route('admin.newsletters.update', $newsletter) : route('admin.newsletters.store') }}"
          enctype="multipart/form-data" id="composeForm">
        @csrf
        @if($newsletter) @method('PUT') @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">{{ __('Title') }}</label>
                <input type="text" name="title" class="form-control" required
                       value="{{ old('title', $newsletter?->title ?? 'Bulles et Aventures : votre newsletter plongée') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">{{ __('Month') }}</label>
                <input type="month" name="month" class="form-control" required
                       value="{{ old('month', $newsletter?->month ?? now()->format('Y-m')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">{{ __('Background') }}</label>
                <select id="bgSelect" class="form-select" onchange="changeBg()">
                    <option value="default-bulles" {{ (!$newsletter || !$newsletter->background_image) ? 'selected' : '' }}>🌊 Bulles et Aventures</option>
                    <option value="gradient-abyss">🌑 Abyss</option>
                    <option value="gradient-coral">🪸 Coral Reef</option>
                    <option value="gradient-arctic">🧊 Arctic</option>
                    <option value="custom" {{ ($newsletter?->background_image && !str_starts_with($newsletter->background_image, 'gradient-')) ? 'selected' : '' }}>📁 {{ __('Custom upload') }}</option>
                </select>
                <input type="hidden" name="background_preset" id="bgPreset" value="{{ $newsletter?->background_image ?? 'default-bulles' }}">
            </div>
            <div class="col-md-3" id="customBgWrap" style="display:none">
                <label class="form-label fw-bold">{{ __('Upload') }}</label>
                <input type="file" name="background_image" class="form-control" accept="image/*">
            </div>
        </div>

        <div class="row">
            {{-- Left: Newsletter visual layout --}}
            <div class="col-lg-8">
                <div class="position-relative rounded overflow-hidden" id="previewArea"
                     style="min-height:750px;padding:20px;background-size:cover;background-position:center">

                    {{-- Title overlay --}}
                    <h3 class="text-center mb-4" style="color:#d4a843;text-shadow:2px 2px 6px rgba(0,0,0,0.8);font-family:Georgia,serif;font-size:1.6rem">
                        {{ __('Newsletter Preview') }}
                    </h3>

                    {{-- 2×2 grid for slots 1-4 --}}
                    <div class="row g-3 mb-3">
                        @for($i = 1; $i <= 4; $i++)
                            @php
                                $defaultTypes = [1 => 'news', 2 => 'trip_report', 3 => 'training', 4 => 'safety'];
                                $slotType = $newsletter ? (collect($newsletter->slots)->firstWhere('position', $i)['article_type'] ?? $defaultTypes[$i]) : $defaultTypes[$i];
                            @endphp
                            <div class="col-6">
                                <div class="card slot-card h-100" data-slot="{{ $i }}"
                                     style="min-height:220px;cursor:pointer;border:3px solid transparent;transition:all 0.2s;opacity:0.95"
                                     onclick="selectSlot({{ $i }})" id="slotCard{{ $i }}">
                                    <div class="card-header py-1 d-flex justify-content-between align-items-center bg-light">
                                        <small class="fw-bold text-muted">{{ __('Slot') }} {{ $i }}</small>
                                        <select class="form-select form-select-sm" style="width:auto;font-size:11px"
                                                name="slots_meta[{{ $i }}][article_type]"
                                                onchange="filterArticlesForSlot({{ $i }}, this.value); event.stopPropagation()"
                                                id="slotType{{ $i }}">
                                            @foreach(\App\Models\Article::TYPES as $typeKey => $typeMeta)
                                                @if($typeKey !== 'classified')
                                                    <option value="{{ $typeKey }}" {{ $slotType === $typeKey ? 'selected' : '' }}>
                                                        {{ $typeMeta['icon'] }} {{ $typeMeta['label'] }}
                                                    </option>
                                                @endif
                                            @endforeach
                                            <option value="">{{ __('All types') }}</option>
                                        </select>
                                    </div>
                                    <div class="card-body text-center d-flex flex-column justify-content-center p-2" id="slotContent{{ $i }}">
                                        <div class="text-muted">
                                            <span class="fs-1 opacity-25">{{ $i }}</span><br>
                                            <small>{{ __('Click to select, then pick an article →') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>

                    {{-- Slot 5: centered bottom banner --}}
                    @php $slotType5 = $newsletter ? (collect($newsletter->slots)->firstWhere('position', 5)['article_type'] ?? 'news') : 'news'; @endphp
                    <div class="d-flex justify-content-center">
                        <div class="card slot-card" data-slot="5" onclick="selectSlot(5)" id="slotCard5"
                             style="cursor:pointer;border:3px solid transparent;transition:all 0.2s;width:55%;opacity:0.95">
                            <div class="card-header py-1 d-flex justify-content-between align-items-center bg-light">
                                <small class="fw-bold text-muted">{{ __('Slot') }} 5</small>
                                <select class="form-select form-select-sm" style="width:auto;font-size:11px"
                                        name="slots_meta[5][article_type]"
                                        onchange="filterArticlesForSlot(5, this.value); event.stopPropagation()"
                                        id="slotType5">
                                    @foreach(\App\Models\Article::TYPES as $typeKey => $typeMeta)
                                        @if($typeKey !== 'classified')
                                            <option value="{{ $typeKey }}" {{ $slotType5 === $typeKey ? 'selected' : '' }}>
                                                {{ $typeMeta['icon'] }} {{ $typeMeta['label'] }}
                                            </option>
                                        @endif
                                    @endforeach
                                    <option value="">{{ __('All types') }}</option>
                                </select>
                            </div>
                            <div class="card-body py-2 text-center" id="slotContent5">
                                <span class="text-muted"><small>{{ __('Click to select, then pick an article →') }}</small></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Hidden inputs for slot data --}}
                <div id="slotInputs"></div>

                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $newsletter ? __('Update Draft') : __('Save Draft') }}</button>
                    <a href="{{ route('admin.newsletters.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                </div>
            </div>

            {{-- Right: Article picker sidebar --}}
            <div class="col-lg-4">
                <div class="card dc-card sticky-top" style="top:80px;max-height:85vh;overflow-y:auto">
                    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                        <span>📰 {{ __('Articles') }}</span>
                        <span class="badge bg-primary" id="activeSlotBadge" style="display:none">{{ __('Slot') }} <span id="activeSlotNum">-</span></span>
                    </div>
                    <div class="card-body p-2">
                        <input type="text" class="form-control form-control-sm mb-2" id="articleSearch"
                               placeholder="{{ __('Search articles…') }}" oninput="filterArticles()">
                        <div id="articleList">
                            @foreach($articles as $article)
                                <div class="article-pick border rounded p-2 mb-1" data-id="{{ $article->id }}"
                                     data-title="{{ e($article->title) }}"
                                     data-image="{{ $article->featured_image ? asset('storage/'.$article->featured_image) : '' }}"
                                     data-excerpt="{{ e(Str::limit(strip_tags($article->body), 80)) }}"
                                     data-type="{{ $article->article_type }}"
                                     style="cursor:pointer;transition:background 0.15s;font-size:13px"
                                     onclick="assignArticle(this)">
                                    <div class="d-flex gap-2 align-items-start">
                                        @if($article->featured_image)
                                            <img src="{{ asset('storage/'.$article->featured_image) }}" style="width:44px;height:44px;object-fit:cover;border-radius:4px" alt="">
                                        @else
                                            <div style="width:44px;height:44px;background:#eee;border-radius:4px" class="d-flex align-items-center justify-content-center flex-shrink-0">
                                                <span>{{ \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '📄' }}</span>
                                            </div>
                                        @endif
                                        <div class="flex-grow-1" style="min-width:0">
                                            <div class="fw-bold text-truncate">{{ $article->title }}</div>
                                            <div class="text-muted" style="font-size:11px">
                                                {{ \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '' }}
                                                {{ ucfirst($article->article_type) }} · {{ $article->created_at->format('d/m/Y') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div id="noArticles" class="text-muted text-center py-3" style="display:none">
                            {{ __('No articles match this type.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <style>
        .slot-card.active { border-color: #0077be !important; box-shadow: 0 0 12px rgba(0,119,190,0.5); }
        .slot-card:hover { opacity: 1 !important; }
        .article-pick:hover { background: #e8f4fd; }
        .article-pick.dimmed { opacity: 0.35; }
    </style>

    <script>
        let activeSlot = null;
        const slots = {};
        const defaultBgUrl = '{{ asset("images/newsletter/bulles/header.jpg") }}'; // just for reference

        const bgStyles = {
            'default-bulles': '#1a6fa0',
            'gradient-abyss': 'linear-gradient(160deg, #0a0a2e 0%, #1a1a5e 30%, #0d2b45 60%, #000428 100%)',
            'gradient-coral': 'linear-gradient(160deg, #1a3a5c 0%, #0e4d6e 25%, #2d6a7a 50%, #c0392b 80%, #e74c3c 100%)',
            'gradient-arctic': 'linear-gradient(160deg, #37474f 0%, #455a64 25%, #546e7a 50%, #78909c 75%, #b0bec5 100%)',
        };

        // Init background
        changeBg();

        // Restore existing slots on edit
        @if($newsletter)
            @foreach($newsletter->slots ?? [] as $s)
                @php $a = \App\Models\Article::find($s['article_id']); @endphp
                @if($a)
                    slots[{{ $s['position'] }}] = {
                        id: {{ $a->id }},
                        title: @json($a->title),
                        image: @json($a->featured_image ? asset('storage/'.$a->featured_image) : ''),
                        excerpt: @json(Str::limit(strip_tags($a->body), 80)),
                        type: @json($s['article_type'] ?? $a->article_type)
                    };
                    renderSlot({{ $s['position'] }});
                @endif
            @endforeach
        @endif
        syncInputs();

        function selectSlot(slot) {
            // Deselect previous
            document.querySelectorAll('.slot-card').forEach(c => c.classList.remove('active'));
            // Select new
            activeSlot = slot;
            document.getElementById('slotCard' + slot).classList.add('active');
            document.getElementById('activeSlotBadge').style.display = '';
            document.getElementById('activeSlotNum').textContent = slot;

            // Filter sidebar to match slot's type
            const typeSelect = document.getElementById('slotType' + slot);
            filterArticlesForSlot(slot, typeSelect.value);
        }

        function assignArticle(el) {
            if (!activeSlot) {
                // Auto-select first empty slot
                for (let i = 1; i <= 5; i++) {
                    if (!slots[i]) { selectSlot(i); break; }
                }
                if (!activeSlot) selectSlot(1);
            }
            const typeSelect = document.getElementById('slotType' + activeSlot);
            slots[activeSlot] = {
                id: parseInt(el.dataset.id),
                title: el.dataset.title,
                image: el.dataset.image,
                excerpt: el.dataset.excerpt,
                type: typeSelect.value || el.dataset.type
            };
            renderSlot(activeSlot);
            syncInputs();

            // Auto-advance to next empty slot
            for (let i = 1; i <= 5; i++) {
                if (!slots[i] && i !== activeSlot) { selectSlot(i); return; }
            }
        }

        function renderSlot(slot) {
            const art = slots[slot];
            if (!art) return;
            const card = document.getElementById('slotContent' + slot);
            const imgHtml = art.image ? '<img src="' + art.image + '" style="width:100%;max-height:100px;object-fit:cover;border-radius:4px" alt="">' : '';
            if (slot <= 4) {
                card.innerHTML = imgHtml +
                    '<div class="text-start mt-1"><strong class="small">' + escHtml(art.title) + '</strong>' +
                    '<br><span class="text-muted" style="font-size:11px">' + escHtml(art.excerpt) + '</span></div>' +
                    '<button type="button" class="btn btn-outline-danger btn-sm mt-1" onclick="clearSlot(' + slot + '); event.stopPropagation()">✕</button>';
            } else {
                card.innerHTML = '<strong class="small">' + escHtml(art.title) + '</strong>' +
                    ' <button type="button" class="btn btn-outline-danger btn-sm btn-sm ms-2" onclick="clearSlot(5); event.stopPropagation()">✕</button>';
            }
        }

        function clearSlot(slot) {
            delete slots[slot];
            const card = document.getElementById('slotContent' + slot);
            card.innerHTML = '<div class="text-muted"><span class="fs-1 opacity-25">' + (slot <= 4 ? slot : '5') + '</span><br><small>{{ __("Click to select, then pick an article →") }}</small></div>';
            syncInputs();
        }

        function syncInputs() {
            const container = document.getElementById('slotInputs');
            container.innerHTML = '';
            Object.entries(slots).forEach(([pos, art]) => {
                container.innerHTML +=
                    '<input type="hidden" name="slots[' + pos + '][position]" value="' + pos + '">' +
                    '<input type="hidden" name="slots[' + pos + '][article_id]" value="' + art.id + '">' +
                    '<input type="hidden" name="slots[' + pos + '][article_type]" value="' + (art.type || '') + '">';
            });
        }

        function filterArticlesForSlot(slot, type) {
            filterArticles(type);
        }

        function filterArticles(forceType) {
            const q = document.getElementById('articleSearch').value.toLowerCase();
            const type = forceType !== undefined ? forceType : (activeSlot ? document.getElementById('slotType' + activeSlot)?.value : '');
            let visible = 0;
            document.querySelectorAll('.article-pick').forEach(el => {
                const matchText = !q || el.dataset.title.toLowerCase().includes(q);
                const matchType = !type || el.dataset.type === type;
                el.style.display = (matchText && matchType) ? '' : 'none';
                if (matchText && matchType) visible++;
            });
            document.getElementById('noArticles').style.display = visible === 0 ? '' : 'none';
        }

        function changeBg() {
            const sel = document.getElementById('bgSelect');
            const val = sel.value;
            const area = document.getElementById('previewArea');
            document.getElementById('customBgWrap').style.display = val === 'custom' ? '' : 'none';

            if (bgStyles[val]) {
                area.style.background = bgStyles[val];
                area.style.backgroundSize = 'cover';
                area.style.backgroundPosition = 'center';
            }
            document.getElementById('bgPreset').value = val === 'custom' ? '' : val;
        }

        function escHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
    </script>
</x-layout>
