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

    {{-- Visual preview using sliced frame --}}
    @php
        $theme = $newsletter->background_image ?? 'default-bulles';

        // Build slot HTML
        $slotHtml = [];
        foreach (range(1, 5) as $i) {
            if (isset($slotArticles[$i])) {
                $article = $slotArticles[$i]['article'];
                $t = $article->translated('fr');
                $img = $article->featured_image
                    ? '<img src="'.asset('storage/'.$article->featured_image).'" style="width:100%;height:100px;object-fit:cover;display:block;border-radius:4px 4px 0 0" alt="">'
                    : '';
                $excerptLen = $article->featured_image ? 80 : 180;
                $excerpt = Str::limit(strip_tags($t['body']), $i <= 4 ? $excerptLen : 40);
                $url = route('article.show', $article->slug);
                $icon = \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '📄';

                if ($i <= 4) {
                    $slotHtml[$i] = '<div style="background:#fff;border-radius:6px;overflow:hidden;height:100%;display:flex;flex-direction:column">'
                        . $img
                        . '<div style="padding:8px;flex:1"><h6 style="font-size:13px;margin:0 0 6px;color:#003366">' . $icon . ' ' . e($t['title']) . '</h6>'
                        . '<p style="font-size:11px;color:#555;margin:0 0 8px;line-height:1.4">' . e($excerpt) . '</p>'
                        . '<a href="' . $url . '" target="_blank" style="font-size:11px;color:#0077be;text-decoration:none">' . __('Read more →') . '</a>'
                        . '</div></div>';
                } else {
                    $slotHtml[$i] = '<div style="background:#fff;border-radius:6px;padding:8px 16px;text-align:center;display:inline-block">'
                        . '<a href="' . $url . '" target="_blank" style="font-weight:bold;font-size:13px;text-decoration:none;color:#003366">' . $icon . ' ' . e($t['title']) . '</a>'
                        . '</div>';
                }
            } else {
                $slotHtml[$i] = '<div style="background:rgba(255,255,255,0.85);border-radius:6px;height:100%;display:flex;align-items:center;justify-content:center;color:#999;min-height:' . ($i <= 4 ? '150px' : '35px') . '"><small>' . __('Empty slot') . ' ' . $i . '</small></div>';
            }
        }
    @endphp

    @include('admin.newsletters._frame', [
        'theme' => $theme,
        'slot1' => $slotHtml[1],
        'slot2' => $slotHtml[2],
        'slot3' => $slotHtml[3],
        'slot4' => $slotHtml[4],
        'slot5' => $slotHtml[5],
    ])
</x-layout>
