@if(($widget['data']['photos'] ?? collect())->count())
<div class="card dc-card mb-4">
    <div class="card-header">📸 {{ __('Recent Photos') }}</div>
    <div class="card-body p-0">
        <x-slideshow :photos="$widget['data']['photos']" height="200px" :interval="5000" :rounded="false" />
    </div>
</div>
@endif
