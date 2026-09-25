@php $clubName = \App\Models\ThemeSetting::get('club_full_name', config('app.name')); @endphp
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $clubName }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ $clubName }}. @lang('All rights reserved.')
            @if($address = \App\Models\ThemeSetting::get('club_address'))
                {{ $address }}
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
