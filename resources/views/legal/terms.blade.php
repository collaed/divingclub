<x-layout :title="__('Terms of Use')">
<div class="container py-5" style="max-width:800px">
    <h1>{{ __('Terms of Use') }}</h1>
    <p class="text-muted">{{ __('Last updated:') }} {{ now()->format('F Y') }}</p>

    <h2>1. {{ __('Operator') }}</h2>
    <p>{{ __('This platform is operated by') }} Club d'Exploration Profonde (CEP) a.s.b.l., {{ __('a non-profit association registered in Luxembourg, for the exclusive use of its members and prospective members.') }}</p>

    <h2>2. {{ __('Access & Accounts') }}</h2>
    <ul>
        <li>{{ __('Registration is open to current and prospective club members.') }}</li>
        <li>{{ __('You are responsible for maintaining the confidentiality of your account credentials.') }}</li>
        <li>{{ __('You may sign in using email/password or a third-party provider (Google). When using a third-party provider, their terms of service also apply.') }}</li>
        <li>{{ __('The club reserves the right to suspend or delete accounts that violate these terms.') }}</li>
    </ul>

    <h2>3. {{ __('Acceptable Use') }}</h2>
    <p>{{ __('You agree to:') }}</p>
    <ul>
        <li>{{ __('provide accurate personal information;') }}</li>
        <li>{{ __('keep your diving certifications and medical information up to date;') }}</li>
        <li>{{ __('respect other members and refrain from abusive, defamatory, or unlawful content;') }}</li>
        <li>{{ __('not attempt to access other members\' private data or circumvent security measures.') }}</li>
    </ul>

    <h2>4. {{ __('Events & Registrations') }}</h2>
    <ul>
        <li>{{ __('Event registrations are binding. Cancellations should be made as early as possible.') }}</li>
        <li>{{ __('The club may cancel or modify events due to weather, safety, or logistical reasons.') }}</li>
        <li>{{ __('Participants must hold valid diving certifications and medical clearance for diving activities.') }}</li>
        <li>{{ __('Trip cost-sharing calculations are final once the settlement is closed by the bureau.') }}</li>
    </ul>

    <h2>5. {{ __('Intellectual Property') }}</h2>
    <p>{{ __('Content uploaded by members (photos, comments) remains the property of the original author. By uploading content, you grant the club a non-exclusive licence to display it on the platform and in club communications.') }}</p>

    <h2>6. {{ __('Liability') }}</h2>
    <p>{{ __('The platform is provided "as is." The club is not liable for data loss, service interruptions, or inaccuracies in automatically translated content. Diving activities are inherently risky; participants are responsible for assessing their own fitness.') }}</p>

    <h2>7. {{ __('Modifications') }}</h2>
    <p>{{ __('These terms may be updated from time to time. Members will be notified of significant changes via email or platform notification.') }}</p>

    <h2>8. {{ __('Governing Law') }}</h2>
    <p>{{ __('These terms are governed by the laws of the Grand Duchy of Luxembourg. Disputes shall be resolved before the competent courts of Luxembourg.') }}</p>

    <h2>9. {{ __('Contact') }}</h2>
    <p>Club d'Exploration Profonde (CEP) a.s.b.l.<br>
    {{ __('Email:') }} <a href="mailto:clubcep@clubcep.eu">clubcep@clubcep.eu</a></p>
</div>
</x-layout>
