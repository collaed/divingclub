<div class="d-flex mb-3" style="margin-left:{{ $depth * 2 }}rem" id="comment-{{ $comment->id }}">
    <div class="flex-shrink-0 me-2">
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:14px">
            {{ strtoupper(substr($comment->user?->detail?->first_name ?? '?', 0, 1)) }}
        </div>
    </div>
    <div class="flex-grow-1">
        <div class="small">
            <strong>{{ $comment->user?->name ?? __('Deleted user') }}</strong>
            <span class="text-muted ms-2">{{ $comment->created_at->diffForHumans() }}</span>
            @if($comment->user_id === auth()->id() || auth()->user()->isBureauMaster())
                <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this comment?') }}')">
                    @csrf @method('DELETE')
                    <button class="btn btn-link btn-sm text-danger p-0 ms-2">{{ __('Delete') }}</button>
                </form>
            @endif
        </div>
        <div class="small mt-1">{!! $comment->body !!}</div>
        @if($depth < 3)
            <button class="btn btn-link btn-sm p-0 mt-1 reply-toggle" data-target="reply-{{ $comment->id }}">{{ __('Reply') }}</button>
            <form method="POST" action="{{ route('comments.store', $comment->article_id) }}" class="mt-2 d-none" id="reply-{{ $comment->id }}">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <textarea name="body" class="form-control form-control-sm mb-1" rows="2" placeholder="{{ __('Reply...') }}" required></textarea>
                <button class="btn btn-sm btn-primary">{{ __('Reply') }}</button>
            </form>
        @endif
        @foreach($comment->replies as $reply)
            @include('cms.partials.comment', ['comment' => $reply, 'depth' => $depth + 1])
        @endforeach
    </div>
</div>
