{{-- Branded with the club's own name/address (ThemeSetting, set per club on
     install) rather than the generic app.name — a French recipient getting an
     English "Reset Password Notification" from a mismatched brand name is
     exactly what spam filters (and people) flag as phishing. The physical
     address line is also required by EU/CAN-SPAM-style anti-spam rules for
     automated mail and is itself something filters scan for. --}}
@php $clubName = \App\Models\ThemeSetting::get('club_full_name', config('app.name')); @endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $clubName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $clubName }}. {{ __('All rights reserved.') }}
@if($address = \App\Models\ThemeSetting::get('club_address'))
<br>{{ $address }}
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
