<x-layout :title="$newsletter->title">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📬 {{ $newsletter->title }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.newsletters.preview-email', $newsletter) }}" target="_blank" class="btn btn-outline-info btn-sm">📧 {{ __('Preview Email') }}</a>
            <a href="{{ route('admin.newsletters.test-send', $newsletter) }}" class="btn btn-outline-success btn-sm" onclick="return confirm('{{ __('Send a test to your email?') }}')">📨 {{ __('Send test to me') }}</a>
            @php
                $mailtoSubject = rawurlencode('Re: ' . $newsletter->title . ' — ' . ($newsletter->month ?? ''));
                $mailtoBody = rawurlencode(
                    __('Comments on newsletter') . ": " . $newsletter->title . "\n"
                    . __('Preview') . ": " . route('admin.newsletters.preview-email', $newsletter) . "\n\n"
                    . __('Slot articles') . ":\n"
                    . collect($newsletter->slotArticles())->map(fn($s, $pos) => "  {$pos}. " . $s['article']->title)->implode("\n")
                    . "\n\n" . __('Your comments') . ":\n\n"
                );
                $bureauEmails = \App\Models\User::whereHas('role', fn($q) => $q->whereIn('slug', ['bureau_master', 'bureau_finance', 'bureau_technical']))->pluck('primary_email')->implode(',');
            @endphp
            <a href="mailto:{{ $bureauEmails }}?subject={{ $mailtoSubject }}&body={{ $mailtoBody }}" class="btn btn-outline-warning btn-sm">💬 {{ __('Send for Comments') }}</a>
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
                    <div class="alert alert-info py-2 mb-0 d-flex justify-content-between align-items-center">
                        {{ __('You cannot approve your own newsletter. Waiting for 3 other bureau members.') }}
                        <form method="POST" action="{{ route('admin.newsletters.withdraw', $newsletter) }}" onsubmit="return confirm('{{ __('Withdraw and return to draft?') }}')">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm">✏️ {{ __('Back to Draft') }}</button>
                        </form>
                    </div>
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
                    ? '<img src="'.asset('storage/'.$article->featured_image).'" style="width:100%;height:60px;object-fit:cover;display:block" alt="">'
                    : '';
                $excerptLen = $article->featured_image ? 200 : 400;
                $excerpt = Str::limit(strip_tags($t['body']), $i <= 4 ? $excerptLen : 40);
                $url = route('article.show', $article->slug);
                $icon = \App\Models\Article::TYPES[$article->article_type]['icon'] ?? '📄';

                if ($i <= 4) {
                    $slotHtml[$i] = '<a href="' . $url . '" target="_blank" style="background:rgba(255,255,255,0.6);overflow:hidden;height:100%;display:flex;flex-direction:column;text-decoration:none;color:inherit;border-radius:4px">'
                        . $img
                        . '<div style="padding:6px;flex:1;overflow:hidden"><h6 style="font-size:12px;margin:0 0 4px;color:#001a33;line-height:1.3;font-weight:bold">' . $icon . ' ' . e($t['title']) . '</h6>'
                        . '<p style="font-size:9px;color:#111;margin:0;line-height:1.35;overflow:hidden">' . e($excerpt) . '</p>'
                        . '</div>'
                        . '<div style="padding:2px 6px 4px;text-align:right"><span style="font-size:9px;color:#004488;font-weight:bold">Lire la suite →</span></div>'
                        . '</a>';
                } else {
                    $slotHtml[$i] = '<a href="' . $url . '" target="_blank" style="background:rgba(255,255,255,0.6);height:100%;display:flex;align-items:center;justify-content:center;padding:0 8px;text-decoration:none;border-radius:4px">'
                        . '<span style="font-weight:bold;font-size:11px;color:#001a33;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . $icon . ' ' . e($t['title']) . '</span>'
                        . '</a>';
                }
            } else {
                $slotHtml[$i] = '<div style="background:#f0f7ff;height:100%;display:flex;align-items:center;justify-content:center;color:#999"><small style="font-size:10px">' . __('Empty slot') . ' ' . $i . '</small></div>';
            }
        }
    @endphp

    @include('admin.newsletters._frame', [
        'theme' => $theme,
        'month' => $newsletter->month,
        'slot1' => $slotHtml[1],
        'slot2' => $slotHtml[2],
        'slot3' => $slotHtml[3],
        'slot4' => $slotHtml[4],
        'slot5' => $slotHtml[5],
    ])
</x-layout>
