<!-- Participant row partial: cert level badge, medical status, audit trail, proxy cancel | ClubCEP.eu -->
@php
    $isNonMember = $reg->isNonMember();
    $d = $isNonMember ? null : $reg->user->detail;
    $cert = in_array($event->event_type, ['apnea', 'pool_apnea'])
        ? ($d?->apnea_level ?? '')
        : ($d?->certification_level ?? '');
    $med = $isNonMember ? ['status' => 'n/a', 'badge' => 'secondary', 'label' => 'N/A'] : app(\App\Services\MedicalComplianceService::class)->getStatus($reg->user, $event->event_date);
    $isCancelled = $reg->status === 'cancelled';
    $medInvalid = !$isCancelled && !$isNonMember && in_array($med['status'], ['missing', 'expired']);
    $pendingLicence = $reg->status === 'pending_licence';
    // A registration confirmed earlier never gets re-checked once finalized
    // (only a still-pending_licence row does, via "Recheck licence") — this
    // flags an already-confirmed/waiting member whose licence has since
    // lapsed, purely for display, without touching their registration status.
    $licenceNowInvalid = !$isCancelled && !$isNonMember && !$pendingLicence
        && $event->required_federation_id
        && ! $reg->user->hasActiveLicence($event->requiredFederation, $event->event_date);
@endphp
<li class="list-group-item small {{ $isCancelled ? 'bg-light text-decoration-line-through' : (($medInvalid || $licenceNowInvalid) ? 'list-group-item-danger' : '') }}" style="border-bottom: 2px solid rgba(var(--bs-emphasis-color-rgb), 0.15);">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            @if($isNonMember)
                <span class="{{ $isCancelled ? 'text-muted' : '' }}">{{ $reg->non_member_name }}</span>
                <span class="badge bg-secondary" style="font-size:0.6rem">{{ __('non-member') }}</span>
            @elseif($isPrivileged)
                <a href="{{ route('admin.profile.show', $reg->user) }}" class="{{ $isCancelled ? 'text-muted' : '' }}">{{ $reg->user->name }}</a>
            @else
                <span class="{{ $isCancelled ? 'text-muted' : '' }}">{{ $reg->user->name }}</span>
            @endif
            @if($isPrivileged && !$isCancelled && !$isNonMember)
                <a href="{{ route('admin.profile.show', $reg->user) }}?tab=equipment" class="ms-1" title="{{ __('Equipment') }}" style="font-size:0.7rem;text-decoration:none">🔧</a>
            @endif
            @if($cert && ($event->levels_display || $isPrivileged))
                <span class="badge bg-info text-dark" style="font-size:0.65rem">{{ $cert }}</span>
            @endif
            @if(!$isCancelled && $isPrivileged)
                <span class="badge bg-{{ $med['badge'] }}" style="font-size:0.55rem">{{ __($med['label']) }}</span>
            @endif
            @if($medInvalid && !$isPrivileged)
                <span class="text-danger" style="font-size:0.7rem">⚠ {{ __('Medical cert invalid at event date') }}</span>
            @endif
            @if($pendingLicence)
                <span class="{{ $isPrivileged ? 'badge bg-danger' : 'text-danger' }}" style="font-size:0.7rem">⚠ {{ __('Needs an active :federation licence', ['federation' => $event->requiredFederation->acronym]) }}</span>
            @endif
            @if($licenceNowInvalid)
                <span class="{{ $isPrivileged ? 'badge bg-danger' : 'text-danger' }}" style="font-size:0.7rem">⚠ {{ __('No longer has an active :federation licence', ['federation' => $event->requiredFederation->acronym]) }}</span>
            @endif
            @if(isset($showPosition) && $reg->waiting_list_position)
                <span class="badge bg-warning text-dark">#{{ $reg->waiting_list_position }}</span>
            @endif
        </div>
        <div class="text-end text-nowrap">
            @if(!$isCancelled)
                <span class="badge bg-{{ $reg->status === 'confirmed' ? 'success' : 'warning text-dark' }}">{{ \Illuminate\Support\Str::headline($reg->status) }}</span>
            @endif
        </div>
    </div>
    {{-- Audit line --}}
    <div class="text-muted" style="font-size:0.7rem">
        {{ $reg->created_at?->format('d/m/y H:i') }}
        @if($reg->registeredByUser)
            {{ __('by') }} {{ $reg->registeredByUser->name }}
        @endif
        @if($reg->comment)
            — <em>{{ $reg->comment }}</em>
        @endif
        @if(!$isCancelled && ($isPrivileged || (auth()->check() && $reg->user_id === auth()->id())))
            <a href="#" onclick="this.nextElementSibling.classList.toggle('d-none');return false" class="ms-1">✏️</a>
            <form method="POST" action="{{ route('events.update-comment', $event) }}" class="d-none mt-1">
                @csrf
                <input type="hidden" name="registration_id" value="{{ $reg->id }}">
                <div class="input-group input-group-sm" style="max-width:300px">
                    <input type="text" name="comment" class="form-control" value="{{ $reg->comment }}" placeholder="{{ __('Comment') }}" style="font-size:0.7rem">
                    <button class="btn btn-primary btn-sm" style="font-size:0.7rem">💾</button>
                </div>
            </form>
        @endif
        @if($pendingLicence && $isPrivileged)
            <form method="POST" action="{{ route('events.recheck-licence', $event) }}" class="d-inline ms-1">
                @csrf
                <input type="hidden" name="registration_id" value="{{ $reg->id }}">
                <button class="btn btn-outline-secondary btn-sm" style="font-size:0.65rem">{{ __('Recheck licence') }}</button>
            </form>
        @endif
    </div>
    {{-- Cancellation info --}}
    @if(isset($showCancel) && $isCancelled)
        <div class="text-danger" style="font-size:0.7rem">
            @icon('✗') {{ $reg->cancelled_at?->format('d/m/y H:i') }}
            @if($reg->cancelledByUser)
                {{ __('by') }} {{ $reg->cancelledByUser->name }}
            @endif
            @if($reg->cancel_comment)
                — <em>{{ $reg->cancel_comment }}</em>
            @endif
        </div>
    @endif
    {{-- Cancel button for privileged users --}}
    @auth
        @if(!$isCancelled && $isPrivileged && ($isNonMember || $reg->user_id !== auth()->id()))
            <form method="POST" action="{{ route('events.cancel-registration', $event) }}" class="mt-1" data-confirm="{{ __('Unregister :name?', ['name' => $reg->participantName()]) }}" data-confirm-style="warning" data-confirm-btn="{{ __('Confirm') }}">
                @csrf
                @if($isNonMember)
                    <input type="hidden" name="registration_id" value="{{ $reg->id }}">
                @else
                    <input type="hidden" name="user_id" value="{{ $reg->user_id }}">
                @endif
                <div class="input-group input-group-sm">
                    <input type="text" name="cancel_comment" class="form-control" placeholder="{{ __('Reason') }}" style="font-size:0.7rem">
                    <button class="btn btn-danger btn-sm" style="font-size:0.7rem">✗</button>
                </div>
            </form>
        @endif
    @endauth
</li>
