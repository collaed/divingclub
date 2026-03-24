<x-layout :title="$newsletter->title">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📬 {{ $newsletter->title }}</h4>
        <div class="d-flex gap-2">
            @if($newsletter->status === 'draft')
                <a href="{{ route('admin.newsletters.edit', $newsletter) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.newsletters.submit', $newsletter) }}">
                    @csrf
                    <button class="btn btn-warning btn-sm">{{ __('Submit for Approval') }}</button>
                </form>
            @endif
            @if($newsletter->status !== 'sent')
                <form method="POST" action="{{ route('admin.newsletters.destroy', $newsletter) }}" onsubmit="return confirm('{{ __('Delete this newsletter?') }}')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Status & approvals --}}
    <div class="row mb-4">
        <div class="col-md-8">
            @php $badges = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','sent'=>'info']; @endphp
            <span class="badge bg-{{ $badges[$newsletter->status] ?? 'secondary' }} fs-6">{{ ucfirst($newsletter->status) }}</span>
            <span class="text-muted ms-2">{{ $newsletter->month }} · {{ __('by') }} {{ $newsletter->creator?->name }}</span>
            @if($newsletter->sent_at)
                <span class="text-muted ms-2">· {{ __('Sent') }} {{ $newsletter->sent_at->format('d/m/Y H:i') }}</span>
            @endif
        </div>
        <div class="col-md-4 text-end">
            @if($newsletter->status === 'pending')
                <strong>{{ __('Approvals') }}: {{ $newsletter->approvalCount() }}/3</strong>
            @endif
        </div>
    </div>

    {{-- Approval section --}}
    @if($newsletter->status === 'pending')
        <div class="card dc-card mb-4">
            <div class="card-header fw-bold">{{ __('Bureau Approval') }}</div>
            <div class="card-body">
                @if($newsletter->approvals->count())
                    <ul class="list-unstyled mb-3">
                        @foreach($newsletter->approvals->where('approved', true) as $a)
                            <li>✅ {{ $a->user->name }} <span class="text-muted small">{{ $a->created_at->format('d/m H:i') }}</span>
                                @if($a->comment) — <em>{{ $a->comment }}</em> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($newsletter->created_by === auth()->id())
                    <div class="alert alert-info py-2 mb-0">{{ __('You cannot approve your own newsletter. Waiting for 3 other bureau members.') }}</div>
                @elseif($newsletter->isApprovedBy(auth()->user()))
                    <div class="alert alert-success py-2 mb-0">{{ __('You have already approved this newsletter.') }}</div>
                @else
                    <form method="POST" action="{{ route('admin.newsletters.approve', $newsletter) }}" class="d-flex gap-2 align-items-end">
                        @csrf
                        <div class="flex-grow-1">
                            <input type="text" name="comment" class="form-control form-control-sm" placeholder="{{ __('Optional comment…') }}">
                        </div>
                        <button class="btn btn-success btn-sm">✅ {{ __('Approve') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- Send button --}}
    @if($newsletter->status === 'approved')
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <span>{{ __('Newsletter approved by 3 bureau members. Ready to send!') }}</span>
            <form method="POST" action="{{ route('admin.newsletters.send', $newsletter) }}" onsubmit="return confirm('{{ __('Send to all verified members?') }}')">
                @csrf
                <button class="btn btn-primary">🚀 {{ __('Send Newsletter') }}</button>
            </form>
        </div>
    @endif

    {{-- Visual preview --}}
    <div class="position-relative rounded overflow-hidden" style="max-width:650px;margin:0 auto;min-height:600px;background:linear-gradient(135deg,#003366,#0077be);padding:20px">
        @if($newsletter->background_image)
            <img src="{{ asset('storage/'.$newsletter->background_image) }}" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit:cover;opacity:0.4" alt="">
        @endif
        <div class="position-relative">
            <h3 class="text-center text-warning mb-4" style="text-shadow:2px 2px 4px rgba(0,0,0,0.7)">{{ $newsletter->title }}</h3>

            <div class="row g-3 mb-3">
                @for($i = 1; $i <= 4; $i++)
                    <div class="col-6">
                        @if(isset($slotArticles[$i]))
                            @php
                                $article = $slotArticles[$i]['article'];
                                $t = $article->translated('fr');
                            @endphp
                            <div class="card h-100">
                                @if($article->featured_image)
                                    <img src="{{ asset('storage/'.$article->featured_image) }}" class="card-img-top" style="max-height:120px;object-fit:cover" alt="">
                                @endif
                                <div class="card-body p-2">
                                    <h6 class="card-title mb-1" style="font-size:13px">{{ $t['title'] }}</h6>
                                    <p class="card-text text-muted" style="font-size:11px">{{ Str::limit(strip_tags($t['body']), 100) }}</p>
                                    <a href="{{ route('article.show', $article->slug) }}" class="small" target="_blank">{{ __('Read more →') }}</a>
                                </div>
                            </div>
                        @else
                            <div class="card h-100 border-dashed" style="min-height:150px">
                                <div class="card-body text-center text-muted d-flex align-items-center justify-content-center">
                                    <span>{{ __('Empty slot') }} {{ $i }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endfor
            </div>

            {{-- Slot 5 --}}
            @if(isset($slotArticles[5]))
                @php $a5 = $slotArticles[5]['article']; @endphp
                <div class="bg-white rounded p-2 text-center" style="max-width:60%">
                    <a href="{{ route('article.show', $a5->slug) }}" class="fw-bold small text-decoration-none" target="_blank">{{ $a5->translated('fr')['title'] }}</a>
                </div>
            @endif
        </div>
    </div>
</x-layout>
