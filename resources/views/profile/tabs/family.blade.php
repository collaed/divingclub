@php
    $familyMembers = $target->familyMembers();
    $incoming = \App\Models\FamilyInvite::where('invitee_id', $target->id)->where('status', 'pending')->with('requester.detail')->get();
    $outgoing = \App\Models\FamilyInvite::where('requester_id', $target->id)->where('status', 'pending')->with('invitee.detail')->get();

    if (! $target->isMinor()) {
        $excludeIds = $familyMembers->pluck('id')->push($target->id)
            ->merge($incoming->pluck('requester_id'))->merge($outgoing->pluck('invitee_id'));
        $mySurname = mb_strtoupper((string) $target->detail?->last_name);
        $candidates = \App\Models\User::whereHas('detail')->whereNotIn('id', $excludeIds)
            ->with('detail')->get()
            ->sortByDesc(fn ($u) => similar_text($mySurname, mb_strtoupper((string) $u->detail?->last_name)))
            ->take(25);
    }
@endphp

<p class="small text-muted">
    @icon('ℹ️') {{ __('Family members can register each other for events, upload a medical certificate for each other, and preview each other\'s cotisation — see the quick-select on those screens.') }}
</p>

<h6>{{ __('Your Family') }}</h6>
@if($familyMembers->isEmpty())
    <p class="text-muted">{{ __('You are not part of a family yet.') }}</p>
@else
    <table class="table table-sm">
        <tbody>
        @foreach($familyMembers as $member)
            <tr>
                <td>{{ $member->name }}</td>
                <td class="text-end">
                    <form method="POST" action="{{ route('family.leave') }}" onsubmit="return confirm('{{ __('Remove yourself from this family?') }}')" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger">{{ __('Leave') }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($incoming->isNotEmpty())
    <h6 class="mt-3">{{ __('Pending — waiting on you') }}</h6>
    @foreach($incoming as $invite)
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span>{{ $invite->requester->name }} {{ __('wants to add you to their family.') }}</span>
            <span>
                <form method="POST" action="{{ route('family.confirm', $invite) }}" class="d-inline">@csrf <button class="btn btn-sm btn-success">{{ __('Confirm') }}</button></form>
                <form method="POST" action="{{ route('family.decline', $invite) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-secondary">{{ __('Decline') }}</button></form>
            </span>
        </div>
    @endforeach
@endif

@if($outgoing->isNotEmpty())
    <h6 class="mt-3">{{ __('Pending — waiting on them') }}</h6>
    @foreach($outgoing as $invite)
        <div>{{ __('Invited :name — waiting for them to confirm.', ['name' => $invite->invitee->name]) }}</div>
    @endforeach
@endif

@if(! $target->isMinor())
    <h6 class="mt-3">{{ __('Add a Family Member') }}</h6>
    <p class="small text-muted">{{ __('For a child, you need an existing guardian link first (see Minors & Consent) — once that exists, adding them here is immediate, no confirmation needed from them.') }}</p>
    <form method="POST" action="{{ route('family.propose') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-6">
            <select name="member_id" class="form-select" required>
                <option value="">{{ __('Select a member…') }}</option>
                @foreach($candidates as $candidate)
                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
        </div>
    </form>
@endif
