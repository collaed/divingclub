@props(['provider', 'href', 'label' => null, 'action' => 'signin'])

@php
    $names = [
        'google' => 'Google', 'microsoft' => 'Microsoft', 'facebook' => 'Facebook',
        'x' => 'X', 'amazon' => 'Amazon', 'apple' => 'Apple',
    ];
    $name = $names[$provider] ?? ucfirst($provider);
    $text = $label ?? ($action === 'signup'
        ? __('Sign up with :provider', ['provider' => $name])
        : __('Sign in with :provider', ['provider' => $name]));
@endphp

<a href="{{ $href }}" role="button" {{ $attributes->merge(['class' => 'dc-social-btn dc-social-'.$provider]) }}>
    <span class="dc-social-icon">
        @switch($provider)
            @case('google')
                <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                @break
            @case('microsoft')
                <svg viewBox="0 0 21 21" aria-hidden="true"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                @break
            @case('facebook')
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                @break
            @case('x')
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.6l5.24 6.93 6.06-6.93zm-1.29 19.5h2.04L6.49 3.24H4.3L17.61 20.65z"/></svg>
                @break
            @case('amazon')
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.93 17.09c-2.55 1.88-6.25 2.88-9.43 2.88-4.46 0-8.47-1.65-11.5-4.39-.24-.21-.03-.5.26-.34 3.28 1.91 7.34 3.06 11.53 3.06 2.82 0 5.92-.58 8.77-1.8.43-.18.79.28.37.59zM17 15.9c-.33-.42-2.16-.2-2.98-.1-.25.03-.29-.19-.06-.35 1.46-1.03 3.86-.73 4.14-.39.28.35-.07 2.76-1.44 3.91-.21.18-.41.08-.32-.15.31-.76 1-2.46.66-2.93zM12.03 4.6V2.99c0-.24.18-.4.4-.4h7.2c.23 0 .41.17.41.4v1.38c0 .23-.19.53-.53 1l-3.73 5.33c1.39-.03 2.85.17 4.11.88.28.16.36.4.38.63v1.72c0 .24-.26.51-.53.37-2.22-1.17-5.17-1.29-7.63.01-.25.13-.51-.13-.51-.37v-1.63c0-.26 0-.71.27-1.11l4.32-6.2h-3.76c-.23 0-.41-.16-.41-.4z"/></svg>
                @break
            @default
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>
        @endswitch
    </span>
    <span class="dc-social-label">{{ $text }}</span>
</a>
