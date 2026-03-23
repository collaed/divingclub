<x-layout :title="__('Contact Us')">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h4 class="mb-4">{{ __('Contact Us') }}</h4>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row g-4">
                {{-- Club Info --}}
                <div class="col-md-6">
                    <div class="card dc-card h-100">
                        <div class="card-body">
                            @php
                                $clubEmail = \App\Models\ThemeSetting::get('club_email');
                                $clubPhone = \App\Models\ThemeSetting::get('club_phone');
                                $clubName = \App\Models\ThemeSetting::get('club_full_name', config('app.name', 'Diving Club'));
                                $clubAddress = \App\Models\ThemeSetting::get('club_address');
                            @endphp

                            <h5 class="mb-3">@icon('📋') {{ __('Club Details') }}</h5>
                            <p class="fw-bold mb-1">{{ $clubName }}</p>
                            @if($clubAddress)<p class="text-muted">{!! nl2br(e(str_replace(' / ', "\n", $clubAddress))) !!}</p>@endif
                            @if($clubEmail)<p>@icon('📧') <a href="mailto:{{ $clubEmail }}">{{ $clubEmail }}</a></p>@endif
                            @if($clubPhone)<p>@icon('📞') <a href="tel:{{ $clubPhone }}">{{ $clubPhone }}</a></p>@endif

                            {{-- Social Links --}}
                            @php
                                $socials = [
                                    'social_facebook'  => ['icon' => '📘', 'label' => 'Facebook'],
                                    'social_instagram' => ['icon' => '📷', 'label' => 'Instagram'],
                                    'social_youtube'   => ['icon' => '🎬', 'label' => 'YouTube'],
                                    'social_tiktok'    => ['icon' => '🎵', 'label' => 'TikTok'],
                                    'social_x'         => ['icon' => '𝕏', 'label' => 'X'],
                                    'social_whatsapp'  => ['icon' => '💬', 'label' => 'WhatsApp'],
                                ];
                            @endphp
                            @php $hasAnySocial = false; @endphp
                            @foreach($socials as $key => $meta)
                                @if(\App\Models\ThemeSetting::get($key))
                                    @if(!$hasAnySocial)<h6 class="mt-4">{{ __('Follow Us') }}</h6><div class="d-flex flex-wrap gap-2">@php $hasAnySocial = true; @endphp @endif
                                    <a href="{{ \App\Models\ThemeSetting::get($key) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">{{ $meta['icon'] }} {{ $meta['label'] }}</a>
                                @endif
                            @endforeach
                            @if($hasAnySocial)</div>@endif

                            {{-- Training Locations --}}
                            @php $locations = json_decode(\App\Models\ThemeSetting::get('training_locations', '[]'), true) ?: []; @endphp
                            @if(count($locations))
                                <h6 class="mt-4">@icon('📍') {{ __('Training Locations') }}</h6>
                                @foreach($locations as $loc)
                                    <div class="mb-2">
                                        <strong>{{ $loc['name'] }}</strong>
                                        @if(!empty($loc['address']))<br><span class="text-muted small">{{ $loc['address'] }}</span>@endif
                                        @if(!empty($loc['lat']) && !empty($loc['lon']))
                                            <br><a href="https://www.google.com/maps/search/?api=1&query={{ $loc['lat'] }},{{ $loc['lon'] }}" target="_blank" class="small">@icon('🗺️') {{ __('View on Map') }}</a>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Contact Form --}}
                <div class="col-md-6">
                    <div class="card dc-card h-100">
                        <div class="card-body">
                            <h5 class="mb-3">@icon('✉️') {{ __('Send us a Message') }}</h5>
                            <form method="POST" action="{{ route('contact.send') }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Name') }} *</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', auth()->user()?->name) }}" required>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Email') }} *</label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', auth()->user()?->email) }}" required>
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Subject') }} *</label>
                                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required>
                                    @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Message') }} *</label>
                                    <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="5" required>{{ old('message') }}</textarea>
                                    @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                {{-- Honeypot --}}
                                <div style="display:none"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                                <button type="submit" class="btn btn-primary">{{ __('Send Message') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layout>
