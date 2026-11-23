{{-- Named exceptions on top of (or instead of) the criteria above — see MailAliasCriteria::matchingUsers(). --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label small mb-1">{{ __('Always include') }}</label>
        <div class="position-relative mb-1">
            <input type="text" class="form-control form-control-sm" data-member-combo="include" placeholder="{{ __('Type a name…') }}" autocomplete="off">
            <div class="dropdown-menu w-100" data-member-dropdown="include" style="max-height:200px;overflow-y:auto"></div>
        </div>
        <div data-chip-list="include" class="d-flex flex-wrap gap-1"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-1">{{ __('Always exclude') }}</label>
        <div class="position-relative mb-1">
            <input type="text" class="form-control form-control-sm" data-member-combo="exclude" placeholder="{{ __('Type a name…') }}" autocomplete="off">
            <div class="dropdown-menu w-100" data-member-dropdown="exclude" style="max-height:200px;overflow-y:auto"></div>
        </div>
        <div data-chip-list="exclude" class="d-flex flex-wrap gap-1"></div>
    </div>
</div>
