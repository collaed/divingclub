@php
    $dayNames = [__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')];
    $ruleTypeLabels = [
        \App\Models\EventAutomationRule::TYPE_MIN_REGISTRATIONS => __('Minimum registrations'),
        \App\Models\EventAutomationRule::TYPE_REQUIRES_LIFEGUARD => __('Requires a lifeguard'),
    ];
@endphp
<x-admin-layout :title="__('Event Automation Rules')">
    <h4 class="mb-3">@icon('🤖') {{ __('Event Automation Rules') }}</h4>
    <p class="text-muted small">{{ __('Evaluated automatically once registrations close (checked every 15 minutes). A rule on a pattern is the default for every event it generates; a rule on one specific event overrides the pattern\'s rule of the same type for that event only.') }}</p>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    <div class="card dc-card mb-4">
        <div class="card-header">{{ __('Add rule') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.event-automation-rules.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Applies to') }}</label>
                    <select name="target" class="form-select form-select-sm dc-target-select" required>
                        <option value="pattern">{{ __('A recurring pattern (default)') }}</option>
                        <option value="event">{{ __('One specific event (override)') }}</option>
                    </select>
                </div>
                <div class="col-md-4 dc-target-pattern">
                    <label class="form-label small mb-1">{{ __('Pattern') }}</label>
                    <select name="season_pattern_id" class="form-select form-select-sm">
                        @foreach($patterns as $p)
                            <option value="{{ $p->id }}">{{ $dayNames[$p->day_of_week] ?? $p->day_of_week }} {{ $p->start_time }} — {{ $p->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 dc-target-event" hidden>
                    <label class="form-label small mb-1">{{ __('Event') }}</label>
                    <select name="event_id" class="form-select form-select-sm">
                        @foreach($events as $e)
                            <option value="{{ $e->id }}">{{ $e->event_date?->format('d/m/Y') }} — {{ $e->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('Rule') }}</label>
                    <select name="rule_type" class="form-select form-select-sm dc-rule-type" required>
                        @foreach($ruleTypeLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 dc-threshold-field">
                    <label class="form-label small mb-1">{{ __('Threshold') }}</label>
                    <input type="number" name="threshold" min="0" class="form-control form-control-sm" placeholder="{{ __('e.g. 4') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="cancels_event" value="0">
                        <input type="checkbox" name="cancels_event" value="1" class="form-check-input" id="dc-cancels-event">
                        <label class="form-check-label small" for="dc-cancels-event">{{ __('Also cancel the event') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">{{ __('Email subject') }}</label>
                    <input type="text" name="email_subject" class="form-control form-control-sm" placeholder="{{ __('Left blank: a default subject is used') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">{{ __('Extra fixed recipients') }}</label>
                    <input type="text" name="extra_recipients" class="form-control form-control-sm" placeholder="{{ __('comma-separated addresses') }}">
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">{{ __('Email text') }}</label>
                    <textarea name="email_body" rows="3" class="form-control form-control-sm" placeholder="{{ __('What the recipients will read') }}"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary">@icon('➕') {{ __('Add rule') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>{{ __('Applies to') }}</th>
                    <th>{{ __('Rule') }}</th>
                    <th>{{ __('Cancels?') }}</th>
                    <th>{{ __('Recipients') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td class="small">
                            @if($rule->seasonPattern)
                                <span class="badge bg-secondary">{{ __('Pattern') }}</span> {{ $dayNames[$rule->seasonPattern->day_of_week] ?? '' }} — {{ $rule->seasonPattern->title }}
                            @elseif($rule->event)
                                <span class="badge bg-info text-dark">{{ __('Event') }}</span> {{ $rule->event->title }}
                            @else
                                <span class="text-muted">{{ __('(deleted)') }}</span>
                            @endif
                        </td>
                        <td class="small">
                            {{ $ruleTypeLabels[$rule->rule_type] ?? $rule->rule_type }}
                            @if($rule->rule_type === \App\Models\EventAutomationRule::TYPE_MIN_REGISTRATIONS) (&lt; {{ $rule->threshold }}) @endif
                        </td>
                        <td class="small">{{ $rule->cancels_event ? __('Yes') : __('No') }}</td>
                        <td class="small text-muted">{{ $rule->extra_recipients ?: '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.event-automation-rules.destroy', $rule) }}" data-confirm="{{ __('Remove this rule?') }}" data-confirm-style="danger">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">@icon('🗑️') {{ __('Remove') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-4">{{ __('No automation rules configured yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        // No inline handlers, event delegation per project JS convention.
        var targetSelect = document.querySelector('.dc-target-select');
        var patternField = document.querySelector('.dc-target-pattern');
        var eventField = document.querySelector('.dc-target-event');
        var ruleTypeSelect = document.querySelector('.dc-rule-type');
        var thresholdField = document.querySelector('.dc-threshold-field');

        function syncTarget() {
            var isEvent = targetSelect.value === 'event';
            eventField.hidden = !isEvent;
            patternField.hidden = isEvent;
        }
        function syncThreshold() {
            thresholdField.hidden = ruleTypeSelect.value !== '{{ \App\Models\EventAutomationRule::TYPE_MIN_REGISTRATIONS }}';
        }
        targetSelect.addEventListener('change', syncTarget);
        ruleTypeSelect.addEventListener('change', syncThreshold);
        syncTarget();
        syncThreshold();
    </script>
</x-admin-layout>
