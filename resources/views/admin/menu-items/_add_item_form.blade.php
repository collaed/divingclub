{{-- Default selections are always type=link / link_type=article (first options),
     so the initial show/hide state below is rendered server-side — no page-load JS
     needed. A single delegated 'change' listener in index.blade.php (on the stable
     #menuItemsRegion container) handles it from then on, including for forms added
     later via AJAX — a per-fragment inline <script> would silently not execute when
     inserted via innerHTML, so this partial intentionally carries no script of its own. --}}
<form class="row g-2 p-2 align-items-end" data-menu-item-add-form data-parent-id="{{ $dropdown->id }}" data-url="{{ route('admin.menu-items.store') }}">
    <div class="col-auto">
        <select class="form-select form-select-sm" data-add-field="type" aria-label="{{ __('Type') }}">
            <option value="link">{{ __('Link') }}</option>
            <option value="divider">{{ __('Divider') }}</option>
        </select>
    </div>
    <div class="col-auto" data-add-group="link_type">
        <select class="form-select form-select-sm" data-add-field="link_type" aria-label="{{ __('Target type') }}">
            <option value="article">{{ __('Page') }}</option>
            <option value="route">{{ __('Route name') }}</option>
            <option value="url">{{ __('URL') }}</option>
        </select>
    </div>
    <div class="col-auto" style="width:3rem" data-add-group="link_type">
        <input type="text" class="form-control form-control-sm" data-add-field="icon" placeholder="🔗" aria-label="{{ __('Icon') }}">
    </div>
    <div class="col-auto" data-add-group="link_type">
        <input type="text" class="form-control form-control-sm" data-add-field="label" placeholder="{{ __('Label (optional for pages)') }}" aria-label="{{ __('Label') }}">
    </div>
    <div class="col-auto" data-add-group="article">
        <select class="form-select form-select-sm" data-add-field="article_id" aria-label="{{ __('Page') }}">
            <option value="">{{ __('— choose a page —') }}</option>
            @foreach($articles as $article)
                <option value="{{ $article->id }}">{{ $article->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto d-none" data-add-group="target">
        <input type="text" class="form-control form-control-sm" data-add-field="target" placeholder="{{ __('route name or URL') }}" aria-label="{{ __('Target') }}">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-primary">+ {{ __('Add item') }}</button>
    </div>
</form>
