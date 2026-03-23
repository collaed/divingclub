<x-layout :title="$article->title">
    @php
        $m = $article->typeMeta();
        $theme = \App\Services\ThemeService::settings();
        $typeBg = $theme['article_bg_' . $article->article_type] ?? ($m['color'] . '10');
    @endphp

    <div style="background:{{ $typeBg }}; margin:-1rem -1rem 1.5rem; padding:1.5rem 1rem .5rem; border-bottom:3px solid {{ $m['color'] }};">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="/">{{ __('Home') }}</a></li>
                        @if($article->article_type === 'classified')
                            <li class="breadcrumb-item"><a href="{{ route('classifieds.index') }}">{{ __('Classifieds') }}</a></li>
                        @endif
                        <li class="breadcrumb-item active">{{ $article->title }}</li>
                    </ol>
                </nav>
                <span class="badge mb-1" style="background:{{ $m['color'] }}">{{ $m['icon'] }} {{ __($m['label']) }}</span>
                @if($article->isExpired()) <span class="badge bg-secondary">{{ __('Expired') }}</span>
                @elseif($article->expires_at) <span class="badge bg-warning text-dark">{{ __('Expires') }}: {{ $article->expires_at->format('d/m/Y') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <article>
                @if($article->featured_image)
                    <img src="{{ asset('storage/' . $article->featured_image) }}" class="img-fluid rounded mb-4" alt="{{ $article->title }}">
                @endif
                <h2>{{ $article->title }}</h2>
                <p class="text-muted small">{{ $article->created_at->format('d/m/Y') }} — {{ $article->author?->name }}</p>

                {{-- Translation tabs --}}
                @php
                    $currentLocale = app()->getLocale();
                    $t = $article->translated($currentLocale);
                    $hasTranslations = !empty($translatedLocales ?? []);
                    $allLocales = array_unique(array_merge(['original'], $translatedLocales ?? []));
                @endphp
                @if($hasTranslations)
                    <ul class="nav nav-tabs nav-tabs-sm mb-3" role="tablist" style="font-size:.85rem">
                        <li class="nav-item">
                            <button class="nav-link fw-bold {{ !in_array($currentLocale, $translatedLocales ?? []) ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-original">@icon('🇫🇷') Original</button>
                        </li>
                        @foreach($article->translations as $tr)
                            <li class="nav-item">
                                <button class="nav-link {{ $tr->locale === $currentLocale ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $tr->locale }}">
                                    {{ strtoupper($tr->locale) }}@if($tr->stale) @icon('⚠')️@endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane {{ !in_array($currentLocale, $translatedLocales ?? []) ? 'show active' : '' }}" id="tab-original">
                            <div class="article-body">{!! $article->renderedBody() !!}</div>
                        </div>
                        @foreach($article->translations as $tr)
                            <div class="tab-pane {{ $tr->locale === $currentLocale ? 'show active' : '' }}" id="tab-{{ $tr->locale }}">
                                @if($tr->stale)
                                    <div class="alert alert-warning py-1 small">@icon('⚠️') {{ __('This translation may be outdated — the original article was modified.') }}</div>
                                @endif
                                @if($tr->auto_translated) <small class="text-muted fst-italic mb-2 d-block">@icon('🤖') {{ __('Auto-translated') }}</small> @endif
                                <div class="article-body">{!! (new \App\Models\Article(['body' => $tr->body]))->renderedBody() !!}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="article-body">{!! $article->renderedBody() !!}</div>
                @endif

                {{-- Admin: trigger translation --}}
                @if(auth()->user()?->isBureauMaster())
                    <form method="POST" action="{{ route('admin.articles.translate', $article) }}" class="mt-2">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">@icon('🌐') {{ __('Generate translations') }}</button>
                    </form>
                @endif

                {{-- Dynamic instructor profiles --}}
                @if(isset($instructors) && $instructors->count())
                    <div class="row g-3 mt-3">
                        @foreach($instructors as $detail)
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            @if($detail->avatar_path)
                                                <img src="{{ asset('storage/' . $detail->avatar_path) }}" class="rounded-circle me-2" width="48" height="48" alt="">
                                            @else
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:48px;height:48px;font-size:1.1rem">{{ strtoupper(substr($detail->first_name,0,1) . substr($detail->last_name,0,1)) }}</div>
                                            @endif
                                            <div>
                                                <strong>{{ $detail->first_name }} {{ $detail->last_name }}</strong>
                                                @if($detail->user?->primaryCertification())
                                                    <br><small class="text-muted">{{ $detail->user->primaryCertification()->name }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        @if($detail->instructor_bio) <p class="small mb-1">{{ $detail->instructor_bio }}</p> @endif
                                        @if($detail->instructor_specialties) <p class="small mb-1"><strong>{{ __('Specialties') }}:</strong> {{ $detail->instructor_specialties }}</p> @endif
                                        @if($detail->instructor_motivation) <p class="small mb-0 text-muted fst-italic">{{ $detail->instructor_motivation }}</p> @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Live member statistics charts --}}
                @if(isset($memberStats))
                    <div class="alert alert-info py-2 mt-4">
                        @icon('📊') {{ __('Live data from :count active members', ['count' => $memberStats['total']]) }}
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card h-100"><div class="card-body">
                                <h6>{{ __('By Gender') }}</h6>
                                <div style="height:220px"><canvas id="chartGender"></canvas></div>
                            </div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100"><div class="card-body">
                                <h6>{{ __('By Age Bracket') }}</h6>
                                <div style="height:220px"><canvas id="chartAge"></canvas></div>
                            </div></div>
                        </div>
                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <h6>{{ __('By Certification Level') }}</h6>
                                <div id="wrapCert"><canvas id="chartCert"></canvas></div>
                            </div></div>
                        </div>
                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <h6>{{ __('By Nationality') }}</h6>
                                <div id="wrapNat"><canvas id="chartNat"></canvas></div>
                            </div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100"><div class="card-body">
                                <h6>{{ __('By Preferred Language') }}</h6>
                                <div style="height:260px"><canvas id="chartLang"></canvas></div>
                            </div></div>
                        </div>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
                    <script>
                    const palette = ['#003366','#0066cc','#3399ff','#66ccff','#99ddff','#ccf0ff','#ff9933','#ff6600','#cc3300','#993300','#669966','#339933','#006600','#996699','#663366'];
                    const stats = @json($memberStats);

                    // Country → flag emoji mapping
                    const flags = {France:'🇫🇷',Luxembourg:'🇱🇺',Belgium:'🇧🇪',Romania:'🇷🇴',Portugal:'🇵🇹',Italy:'🇮🇹',Germany:'🇩🇪',Poland:'🇵🇱',Greece:'🇬🇷',Spain:'🇪🇸',Netherlands:'🇳🇱',Hungary:'🇭🇺',Ireland:'🇮🇪',UK:'🇬🇧','United Kingdom':'🇬🇧',Austria:'🇦🇹',Switzerland:'🇨🇭',Croatia:'🇭🇷',Serbia:'🇷🇸',Bulgaria:'🇧🇬',Czechia:'🇨🇿','Czech Republic':'🇨🇿',Slovakia:'🇸🇰',Slovenia:'🇸🇮',Sweden:'🇸🇪',Denmark:'🇩🇰',Finland:'🇫🇮',Norway:'🇳🇴',Turkey:'🇹🇷',Morocco:'🇲🇦',Tunisia:'🇹🇳',Algeria:'🇩🇿',Brazil:'🇧🇷',USA:'🇺🇸',Canada:'🇨🇦',Russia:'🇷🇺',Ukraine:'🇺🇦',China:'🇨🇳',Japan:'🇯🇵',India:'🇮🇳',Lebanon:'🇱🇧',Iran:'🇮🇷',Colombia:'🇨🇴',Mexico:'🇲🇽',Philippines:'🇵🇭',Vietnam:'🇻🇳',Thailand:'🇹🇭',Lithuania:'🇱🇹',Latvia:'🇱🇻',Estonia:'🇪🇪',Malta:'🇲🇹',Cyprus:'🇨🇾',Iceland:'🇮🇸',Albania:'🇦🇱',Kosovo:'🇽🇰','Bosnia':'🇧🇦','North Macedonia':'🇲🇰',Montenegro:'🇲🇪',Moldova:'🇲🇩',Georgia:'🇬🇪',Armenia:'🇦🇲',Azerbaijan:'🇦🇿'};
                    const langFlags = {en:'🇬🇧',fr:'🇫🇷',de:'🇩🇪',lb:'🇱🇺',pt:'🇵🇹',it:'🇮🇹',nl:'🇳🇱',es:'🇪🇸',pl:'🇵🇱',hu:'🇭🇺',ro:'🇷🇴',el:'🇬🇷',cs:'🇨🇿',sk:'🇸🇰',hr:'🇭🇷',bg:'🇧🇬',sv:'🇸🇪',da:'🇩🇰',fi:'🇫🇮',no:'🇳🇴',tr:'🇹🇷',ru:'🇷🇺',uk:'🇺🇦',ar:'🇱🇧',zh:'🇨🇳',ja:'🇯🇵'};
                    const langNames = {en:'English',fr:'Français',de:'Deutsch',lb:'Lëtzebuergesch',pt:'Português',it:'Italiano',nl:'Nederlands',es:'Español',pl:'Polski',hu:'Magyar',ro:'Română',el:'Ελληνικά',cs:'Čeština',sk:'Slovenčina',hr:'Hrvatski',bg:'Български',sv:'Svenska',da:'Dansk',fi:'Suomi',no:'Norsk',tr:'Türkçe',ru:'Русский',uk:'Українська',ar:'العربية',zh:'中文',ja:'日本語'};

                    const noSkip = {autoSkip:false, font:{size:13}};
                    const base = {responsive:true, maintainAspectRatio:false};

                    // Gender — doughnut
                    const genderLabels = Object.keys(stats.gender).map(g => g === 'M' ? '♂ {{ __("Male") }}' : '♀ {{ __("Female") }}');
                    new Chart(document.getElementById('chartGender'), {type:'doughnut', data:{labels:genderLabels, datasets:[{data:Object.values(stats.gender), backgroundColor:['#0066cc','#ff6699']}]}, options:{...base, plugins:{legend:{position:'bottom'}}}});

                    // Age — vertical bar
                    new Chart(document.getElementById('chartAge'), {type:'bar', data:{labels:Object.keys(stats.age), datasets:[{label:'{{ __("Members") }}', data:Object.values(stats.age), backgroundColor:'#0066cc'}]}, options:{...base, plugins:{legend:{display:false}}, scales:{x:{ticks:noSkip}, y:{beginAtZero:true}}}});

                    // Certification — horizontal bar, fixed wrapper height
                    const certH = Math.max(250, Object.keys(stats.certification).length * 32);
                    document.getElementById('wrapCert').style.height = certH + 'px';
                    new Chart(document.getElementById('chartCert'), {type:'bar', data:{labels:Object.keys(stats.certification), datasets:[{label:'{{ __("Members") }}', data:Object.values(stats.certification), backgroundColor:'#339933'}]}, options:{...base, indexAxis:'y', plugins:{legend:{display:false}}, scales:{y:{ticks:noSkip}}}});

                    // Nationality — horizontal bar with flags, fixed wrapper height
                    const natKeys = Object.keys(stats.nationality);
                    const natLabels = natKeys.map(n => (flags[n]||'') + ' ' + n);
                    const natH = Math.max(300, natKeys.length * 32);
                    document.getElementById('wrapNat').style.height = natH + 'px';
                    new Chart(document.getElementById('chartNat'), {type:'bar', data:{labels:natLabels, datasets:[{label:'{{ __("Members") }}', data:Object.values(stats.nationality), backgroundColor:'#ff9933'}]}, options:{...base, indexAxis:'y', plugins:{legend:{display:false}}, scales:{y:{ticks:noSkip}}}});

                    // Language — doughnut with flags
                    const langKeys = Object.keys(stats.language);
                    const langLabels = langKeys.map(l => (langFlags[l]||'') + ' ' + (langNames[l]||l.toUpperCase()));
                    new Chart(document.getElementById('chartLang'), {type:'doughnut', data:{labels:langLabels, datasets:[{data:Object.values(stats.language), backgroundColor:palette}]}, options:{...base, plugins:{legend:{position:'bottom', labels:{font:{size:12}}}}}});
                    </script>
                @endif

                {{-- Image gallery --}}
                @if($article->images->count())
                    <div class="row g-2 mt-4">
                        @foreach($article->images as $img)
                            @php $colClass = match($img->layout_hint) { 'third' => 'col-md-4', 'half' => 'col-md-6', default => 'col-12' }; @endphp
                            <div class="{{ $colClass }}">
                                <figure class="figure w-100">
                                    <a href="{{ asset('storage/' . $img->file_path) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $img->file_path) }}" class="figure-img img-fluid rounded w-100" alt="{{ $img->alt_text }}" style="{{ $img->layout_hint !== 'full' ? 'max-height:300px;object-fit:cover' : '' }}">
                                    </a>
                                    @if($img->caption)
                                        <figcaption class="figure-caption text-center">{{ $img->caption }}</figcaption>
                                    @endif
                                </figure>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>

            {{-- Embedded vote for trip proposals --}}
            @if($article->vote && $article->vote->isOpen() && auth()->check())
                @php $token = $article->vote->tokens()->where('user_id', auth()->id())->first(); @endphp
                @if($token)
                    <div class="card dc-card mt-4 border-primary">
                        <div class="card-header bg-primary text-white">@icon('🗳️') {{ $article->vote->title }}</div>
                        <div class="card-body">
                            <p>{{ $article->vote->description }}</p>
                            <a href="{{ route('vote.show', $token->token) }}" class="btn btn-primary">{{ __('Cast your vote') }}</a>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Prev / Next navigation --}}
            @php
                $prevType = $article->previousInType();
                $nextType = $article->nextInType();
                $prevAll = $article->previousOverall();
                $nextAll = $article->nextOverall();
            @endphp
            <nav class="mt-4 pt-3 border-top">
                <div class="row">
                    <div class="col-6">
                        @if($prevType)
                            <small class="text-muted d-block">← {{ __($m['label']) }}</small>
                            <a href="{{ route('article.show', $prevType->slug) }}">{{ Str::limit($prevType->title, 40) }}</a>
                        @elseif($prevAll)
                            <small class="text-muted d-block">← {{ __('Previous') }}</small>
                            <a href="{{ route('article.show', $prevAll->slug) }}">{{ Str::limit($prevAll->title, 40) }}</a>
                        @endif
                    </div>
                    <div class="col-6 text-end">
                        @if($nextType)
                            <small class="text-muted d-block">{{ __($m['label']) }} →</small>
                            <a href="{{ route('article.show', $nextType->slug) }}">{{ Str::limit($nextType->title, 40) }}</a>
                        @elseif($nextAll)
                            <small class="text-muted d-block">{{ __('Next') }} →</small>
                            <a href="{{ route('article.show', $nextAll->slug) }}">{{ Str::limit($nextAll->title, 40) }}</a>
                        @endif
                    </div>
                </div>
            </nav>

            {{-- Comments --}}
            @auth
                <section class="mt-5 pt-3 border-top">
                    <h5>{{ __('Comments') }} ({{ $article->comments->count() }})</h5>

                    {{-- New comment form --}}
                    <form method="POST" action="{{ route('comments.store', $article) }}" class="mb-4">
                        @csrf
                        <div class="mb-2">
                            <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="3" placeholder="{{ __('Write a comment...') }}" required></textarea>
                            @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button class="btn btn-sm btn-primary">{{ __('Post Comment') }}</button>
                    </form>

                    {{-- Thread --}}
                    @foreach($article->rootComments()->with(['user.detail', 'replies.user.detail'])->get() as $comment)
                        @include('cms.partials.comment', ['comment' => $comment, 'depth' => 0])
                    @endforeach
                </section>
            @endauth
        </div>
    </div>
    <script>document.querySelectorAll('.reply-toggle').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.target).classList.toggle('d-none')));</script>
</x-layout>
