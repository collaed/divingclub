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
                <div class="article-body">{!! $article->body !!}</div>

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
                        <div class="card-header bg-primary text-white">🗳️ {{ $article->vote->title }}</div>
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
