@php
    $familyMembers = $target->familyMembers();
    $incoming = \App\Models\FamilyInvite::where('invitee_id', $target->id)->where('status', 'pending')->with('requester.detail')->get();
    $outgoing = \App\Models\FamilyInvite::where('requester_id', $target->id)->where('status', 'pending')->with('invitee.detail')->get();

    if (! $target->isMinor()) {
        $excludeIds = $familyMembers->pluck('id')->push($target->id)
            ->merge($incoming->pluck('requester_id'))->merge($outgoing->pluck('invitee_id'));
        // The full membership (no cap — a short-list heuristic here would just
        // hide whoever you're actually looking for, see the "surname similarity,
        // top 25" version this replaced).
        $candidates = \App\Models\User::whereHas('detail')->whereNotIn('id', $excludeIds)
            ->with('detail')->get()
            ->sortBy(fn ($u) => mb_strtoupper((string) $u->name));
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
    <form method="POST" action="{{ route('family.propose') }}" class="row g-2 align-items-end" data-family-propose-form>
        @csrf
        <div class="col-md-6">
            <input type="text" list="family-candidates-list" class="form-control" placeholder="{{ __('Type a name…') }}" autocomplete="off" required data-family-member-search>
            <input type="hidden" name="member_id" data-family-member-id>
            <datalist id="family-candidates-list">
                @foreach($candidates as $candidate)
                    <option data-id="{{ $candidate->id }}" value="{{ $candidate->name }}"></option>
                @endforeach
            </datalist>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">{{ __('Add') }}</button>
        </div>
    </form>
    @push('scripts')
    <script>
    (function () {
        'use strict';
        document.querySelectorAll('[data-family-propose-form]').forEach(function (form) {
            var search = form.querySelector('[data-family-member-search]');
            var hidden = form.querySelector('[data-family-member-id]');
            var options = Array.from(form.querySelectorAll('#family-candidates-list option'));

            function resolve() {
                var match = options.find(function (o) { return o.value === search.value; });
                hidden.value = match ? match.dataset.id : '';
            }
            search.addEventListener('input', resolve);
            form.addEventListener('submit', function (e) {
                resolve();
                if (!hidden.value) {
                    e.preventDefault();
                    search.setCustomValidity('{{ __('Pick a name from the list.') }}');
                    search.reportValidity();
                } else {
                    search.setCustomValidity('');
                }
            });
        });
    })();
    </script>
    @endpush
@endif
