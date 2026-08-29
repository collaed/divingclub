<x-layout :title="__('Privacy Policy')">
<div class="container py-5" style="max-width:800px">
    <h1>{{ __('Privacy Policy') }}</h1>
    <p class="text-muted">{{ __('Last updated:') }} {{ now()->format('F Y') }}</p>

    <h2>1. {{ __('Data Controller') }}</h2>
    <p>Club d'Exploration Profonde (CEP) a.s.b.l., {{ __('registered in Luxembourg') }}.<br>
    {{ __('Contact:') }} <a href="mailto:clubcep@clubcep.eu">clubcep@clubcep.eu</a></p>

    <h2>2. {{ __('Data We Collect') }}</h2>
    <ul>
        <li><strong>{{ __('Account data:') }}</strong> {{ __('name, email address, phone number (optional), diving certification level, medical certificate expiry date.') }}</li>
        <li><strong>{{ __('Authentication data:') }}</strong> {{ __('when you sign in via Google or another OAuth provider, we receive your name, email, and profile picture from that provider. We do not receive or store your password.') }}</li>
        <li><strong>{{ __('Event participation:') }}</strong> {{ __('registration for club events, attendance records, dive logs.') }}</li>
        <li><strong>{{ __('Financial data:') }}</strong> {{ __('membership fee payment status, trip cost-sharing records. We do not store credit card or bank account numbers.') }}</li>
        <li><strong>{{ __('Technical data:') }}</strong> {{ __('IP address, browser type, and session cookies for security and functionality.') }}</li>
    </ul>

    <h2>3. {{ __('Purpose & Legal Basis') }}</h2>
    <p>{{ __('We process your data to:') }}</p>
    <ul>
        <li>{{ __('manage your club membership (contractual necessity);') }}</li>
        <li>{{ __('organise diving events and ensure safety compliance (legitimate interest);') }}</li>
        <li>{{ __('communicate club news and event updates (legitimate interest);') }}</li>
        <li>{{ __('comply with Luxembourg a.s.b.l. legal obligations.') }}</li>
    </ul>

    <h2>4. {{ __('Data Sharing') }}</h2>
    <p>{{ __('We do not sell your data. We share data only with:') }}</p>
    <ul>
        <li>{{ __('diving federations (FLASSA/CMAS) for certification and insurance purposes;') }}</li>
        <li>{{ __('email service providers (Resend) for transactional emails;') }}</li>
        <li>{{ __('translation services (DeepL, Cloudflare) for article translations (no personal data is sent).') }}</li>
    </ul>

    <h2>5. {{ __('Data Retention') }}</h2>
    <p>{{ __('Account data is retained while your membership is active and for 2 years after, unless you request earlier deletion. Event and financial records are retained for 10 years per Luxembourg accounting obligations.') }}</p>

    <h2>6. {{ __('Your Rights') }}</h2>
    <p>{{ __('Under the GDPR, you have the right to access, rectify, erase, restrict processing, and port your data. You may also object to processing or withdraw consent at any time.') }}</p>
    <p>{{ __('To exercise these rights, contact us at') }} <a href="mailto:clubcep@clubcep.eu">clubcep@clubcep.eu</a>.</p>
    <p>{{ __('You may also lodge a complaint with the') }} <a href="https://cnpd.public.lu" target="_blank" rel="noopener">{{ __('Luxembourg data protection authority (CNPD)') }}</a>.</p>

    <h2>7. {{ __('Cookies') }}</h2>
    <p>{{ __('We use only essential session cookies required for authentication and security. We do not use advertising or tracking cookies. Analytics are provided by a self-hosted, privacy-respecting tool (Umami) that does not use cookies.') }}</p>

    <h2>8. {{ __('Security') }}</h2>
    <p>{{ __('All data is transmitted over encrypted connections (TLS). The application is hosted on a dedicated server in the EU. Database backups are encrypted.') }}</p>
</div>
</x-layout>
