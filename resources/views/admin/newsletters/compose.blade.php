<x-layout :title="$newsletter ? __('Edit Newsletter') : __('New Newsletter')">
    <h4 class="mb-4">📬 {{ $newsletter ? __('Edit Newsletter') : __('Compose Newsletter') }}</h4>

    <form method="POST"
          action="{{ $newsletter ? route('admin.newsletters.update', $newsletter) : route('admin.newsletters.store') }}"
          enctype="multipart/form-data" id="composeForm">
        @csrf
        @if($newsletter) @method('PUT') @endif

        <div class="row">
            {{-- Left: Newsletter visual layout --}}
            <div class="col-lg-8">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control" required
                               value="{{ old('title', $newsletter?->title ?? 'Bulles et Aventures : votre newsletter plongée') }}"
                               placeholder="Bulles et Aventures…">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">{{ __('Month') }}</label>
                        <input type="month" name="month" class="form-control" required
                               value="{{ old('month', $newsletter?->month ?? now()->format('Y-m')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">{{ __('Background') }}</label>
                        <input type="file" name="background_image" class="form-control" accept="image/*">
                    </div>
                </div>

                {{-- Visual grid matching the template image --}}
                <div class="position-relative rounded overflow-hidden" style="min-height:700px;background:linear-gradient(135deg,#003366,#0077be);padding:20px">
                    @if($newsletter?->background_image)
                        <img src="{{ asset('storage/'.$newsletter->background_image) }}" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit:cover;opacity:0.3" alt="">
                    @endif
                    <div class="position-relative">
                        <h3 class="text-center text-warning mb-4" style="text-shadow:2px 2px 4px rgba(0,0,0,0.7)">
                            {{ __('Newsletter Preview') }}
                        </h3>

                        {{-- 2×2 grid for slots 1-4 --}}
                        <div class="row g-3 mb-3">
                            @for($i = 1; $i <= 4; $i++)
                                <div class="col-6">
                                    <div class="card h-100 slot-card" data-slot="{{ $i }}" style="min-height:200px;cursor:pointer;border:2px dashed #ccc;transition:all 0.2s"
                                         onclick="openPicker({{ $i }})" id="slotCard{{ $i }}">
                                        <div class="card-body text-center d-flex flex-column justify-content-center" id="slotContent{{ $i }}">
                                            <div class="text-muted">
                                                <span class="fs-1 opacity-50">{{ $i }}</span><br>
                                                <small>{{ __('Click to assign article') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        </div>

                        {{-- Slot 5: small bottom banner --}}
                        <div class="slot-card" data-slot="5" onclick="openPicker(5)" id="slotCard5"
                             style="cursor:pointer;border:2px dashed #ccc;border-radius:6px;background:rgba(255,255,255,0.9);padding:15px;text-align:center;max-width:60%">
                            <div id="slotContent5">
                                <span class="text-muted"><small>5 — {{ __('Quick link / announcement') }}</small></span>
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
                <div class="card dc-card sticky-top" style="top:80px;max-height:80vh;overflow-y:auto">
                    <div class="card-header fw-bold">📰 {{ __('Available Articles') }}</div>
                    <div class="card-body p-2">
                        <input type="text" class="form-control form-control-sm mb-2" id="articleSearch"
                               placeholder="{{ __('Search articles…') }}" oninput="filterArticles()">
                        <div id="articleList">
                            @foreach($articles as $article)
                                <div class="article-pick border rounded p-2 mb-2" data-id="{{ $article->id }}"
                                     data-title="{{ e($article->title) }}"
                                     data-image="{{ $article->featured_image ? asset('storage/'.$article->featured_image) : '' }}"
                                     data-excerpt="{{ e(Str::limit(strip_tags($article->body), 80)) }}"
                                     data-type="{{ $article->article_type }}"
                                     style="cursor:pointer;transition:background 0.15s"
                                     onmouseover="this.style.background='#e8f4fd'" onmouseout="this.style.background=''">
                                    <div class="d-flex gap-2 align-items-start">
                                        @if($article->featured_image)
                                            <img src="{{ asset('storage/'.$article->featured_image) }}" style="width:50px;height:50px;object-fit:cover;border-radius:4px" alt="">
                                        @else
                                            <div style="width:50px;height:50px;background:#eee;border-radius:4px" class="d-flex align-items-center justify-content-center">
                                                <span>{{ \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '📄' }}</span>
                                            </div>
                                        @endif
                                        <div class="flex-grow-1" style="min-width:0">
                                            <div class="fw-bold small text-truncate">{{ $article->title }}</div>
                                            <div class="text-muted" style="font-size:11px">
                                                {{ ucfirst($article->article_type) }} · {{ $article->created_at->format('d/m/Y') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Article picker modal --}}
    <div class="modal fade" id="pickerModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">{{ __('Assign to slot') }} <span id="pickerSlotNum"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-2 text-center">
                    <p class="text-muted small">{{ __('Click an article in the sidebar to assign it.') }}</p>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearSlot()">{{ __('Clear this slot') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let activeSlot = null;
        const slots = {}; // {position: {article_id, title, image, excerpt}}
        const pickerModal = new bootstrap.Modal(document.getElementById('pickerModal'));

        // Restore existing slots on edit
        @if($newsletter)
            @foreach($newsletter->slots ?? [] as $s)
                @php $a = \App\Models\Article::find($s['article_id']); @endphp
                @if($a)
                    assignToSlot({{ $s['position'] }}, {
                        id: {{ $a->id }},
                        title: @json($a->title),
                        image: @json($a->featured_image ? asset('storage/'.$a->featured_image) : ''),
                        excerpt: @json(Str::limit(strip_tags($a->body), 80))
                    });
                @endif
            @endforeach
        @endif

        function openPicker(slot) {
            activeSlot = slot;
            document.getElementById('pickerSlotNum').textContent = '#' + slot;
            pickerModal.show();
        }

        function clearSlot() {
            if (!activeSlot) return;
            delete slots[activeSlot];
            const card = document.getElementById('slotContent' + activeSlot);
            const num = activeSlot <= 4 ? activeSlot : '5';
            card.innerHTML = '<div class="text-muted"><span class="fs-1 opacity-50">' + num + '</span><br><small>{{ __("Click to assign article") }}</small></div>';
            document.getElementById('slotCard' + activeSlot).style.borderColor = '#ccc';
            syncInputs();
            pickerModal.hide();
        }

        function assignToSlot(slot, article) {
            slots[slot] = article;
            const card = document.getElementById('slotContent' + slot);
            const imgHtml = article.image ? '<img src="' + article.image + '" class="card-img-top" style="max-height:100px;object-fit:cover" alt="">' : '';
            if (slot <= 4) {
                card.innerHTML = imgHtml + '<div class="p-2 text-start"><strong class="small">' + escHtml(article.title) + '</strong><br><span class="text-muted" style="font-size:11px">' + escHtml(article.excerpt) + '</span></div>';
            } else {
                card.innerHTML = '<strong class="small">' + escHtml(article.title) + '</strong>';
            }
            document.getElementById('slotCard' + slot).style.borderColor = '#0077be';
            syncInputs();
        }

        // Click handler for article picks in sidebar
        document.querySelectorAll('.article-pick').forEach(el => {
            el.addEventListener('click', () => {
                if (!activeSlot) { openPicker(1); } // default to slot 1 if none active
                assignToSlot(activeSlot, {
                    id: parseInt(el.dataset.id),
                    title: el.dataset.title,
                    image: el.dataset.image,
                    excerpt: el.dataset.excerpt
                });
                pickerModal.hide();
            });
        });

        function syncInputs() {
            const container = document.getElementById('slotInputs');
            container.innerHTML = '';
            Object.entries(slots).forEach(([pos, art]) => {
                container.innerHTML += '<input type="hidden" name="slots[' + pos + '][position]" value="' + pos + '">' +
                    '<input type="hidden" name="slots[' + pos + '][article_id]" value="' + art.id + '">';
            });
        }

        function filterArticles() {
            const q = document.getElementById('articleSearch').value.toLowerCase();
            document.querySelectorAll('.article-pick').forEach(el => {
                el.style.display = el.dataset.title.toLowerCase().includes(q) || el.dataset.type.includes(q) ? '' : 'none';
            });
        }

        function escHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
    </script>
</x-layout>
