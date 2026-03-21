@props(['options' => [30, 50, 100, 'all'], 'current' => request('per_page', 30)])
<form class="d-inline-flex align-items-center gap-1 small">
    @foreach(request()->except(['per_page','page']) as $k => $v)
        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
    @endforeach
    <label class="text-muted mb-0">{{ __('Show') }}</label>
    <select name="per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        @foreach($options as $opt)
            <option value="{{ $opt }}" {{ (string) $current === (string) $opt ? 'selected' : '' }}>{{ $opt === 'all' ? __('All') : $opt }}</option>
        @endforeach
    </select>
</form>
