<x-layout :title="__('Looking for Buddies')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4>🤝 {{ __('Looking for Buddies') }}</h4>
            <p class="text-muted small mb-0">{{ __('Find dive partners, guides, or a Directeur de Plongée for your next dive.') }}</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            {{-- Active requests --}}
            @forelse($requests as $req)
                @php
                    $cert = $req->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first();
                    $needInfo = \App\Models\BuddyRequest::NEED_TYPES[$req->need_type] ?? $req->need_type;
                    $myResponse = $req->responses->firstWhere('user_id', auth()->id());
                @endphp
                <div class="card dc-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                        <div>
                            <strong>{{ $req->locationLabel() }}</strong>
                            <span class="badge bg-primary ms-1">{{ $req->dive_date->format('D d/m/Y') }}</span>
                            @if($req->dive_time)<span class="badge bg-secondary">{{ $req->dive_time }}</span>@endif
                        </div>
                        <span class="badge bg-{{ match($req->need_type) { 'buddy' => 'success', 'guide' => 'warning text-dark', 'dp' => 'danger', default => 'secondary' } }}">{{ $needInfo }}</span>
                    </div>
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="small">{{ __('Posted by') }} <strong>{{ $req->user->detail?->first_name }} {{ $req->user->detail?->last_name }}</strong></span>
                                @if($cert)
                                    <span class="badge bg-info" style="font-size:0.7rem">{{ $cert->code }} ({{ $cert->federation?->acronym }})</span>
                                @endif
                                @if($req->max_depth)<span class="small text-muted ms-2">{{ __('Max') }} {{ $req->max_depth }}m</span>@endif
                                @if($req->desired_cert_level)<span class="small text-muted ms-2">{{ __('Level') }}: {{ $req->desired_cert_level }}</span>@endif
                                @if($req->max_buddies)<span class="small text-muted ms-2">{{ __('Max buddies') }}: {{ $req->max_buddies }}</span>@endif
                                @if($req->description)<p class="small mb-1 mt-1">{{ $req->description }}</p>@endif
                            </div>
                            @if($req->user_id === auth()->id())
                                <form method="POST" action="{{ route('buddies.close', $req) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ __('Close') }}</button>
                                </form>
                            @endif
                        </div>

                        {{-- Responses --}}
                        @if($req->responses->count())
                            <div class="mt-2 border-top pt-2">
                                @foreach($req->responses as $resp)
                                    <div class="small mb-1">
                                        ✋ <strong>{{ $resp->user->detail?->first_name }} {{ $resp->user->detail?->last_name }}</strong>
                                        @php $rc = $resp->user->certificationLevels->where('category', '!=', 'specialty')->sortByDesc('rank')->first(); @endphp
                                        @if($rc)<span class="badge bg-info" style="font-size:0.65rem">{{ $rc->code }}</span>@endif
                                        @if($resp->message)— {{ $resp->message }}@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Respond form --}}
                        @if($req->user_id !== auth()->id() && !$myResponse)
                            <form method="POST" action="{{ route('buddies.respond', $req) }}" class="mt-2 d-flex gap-2">
                                @csrf
                                <input type="text" name="message" class="form-control form-control-sm" placeholder="{{ __('Optional message…') }}">
                                <button class="btn btn-sm btn-primary text-nowrap">✋ {{ __("I'm in!") }}</button>
                            </form>
                        @elseif($myResponse)
                            <div class="small text-success mt-1">✅ {{ __('You responded to this request.') }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="alert alert-info">{{ __('No active buddy requests. Be the first to post one!') }}</div>
            @endforelse
        </div>

        <div class="col-lg-4">
            {{-- Post new request --}}
            <div class="card dc-card mb-3">
                <div class="card-header">📝 {{ __('Post a Request') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('buddies.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small">{{ __('Where?') }}</label>
                            <select name="dive_site_id" class="form-select form-select-sm" id="buddySite">
                                <option value="">{{ __('Other location…') }}</option>
                                @foreach($sites as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="location_text" class="form-control form-control-sm mt-1" id="buddyLocationText" placeholder="{{ __('Or type a location') }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('When?') }}</label>
                            <input type="date" name="dive_date" class="form-control form-control-sm" required min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="dive_time" class="form-control form-control-sm" placeholder="{{ __('Time (e.g. morning, 10:00)') }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('What do you need?') }}</label>
                            <select name="need_type" class="form-select form-select-sm" required>
                                @foreach(\App\Models\BuddyRequest::NEED_TYPES as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <input type="number" name="max_depth" class="form-control form-control-sm" placeholder="{{ __('Planned max depth (m)') }}" min="1">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="desired_cert_level" class="form-control form-control-sm" placeholder="{{ __('Desired buddy level (e.g. N2+, OWD)') }}">
                        </div>
                        <div class="mb-2">
                            <input type="number" name="max_buddies" class="form-control form-control-sm" placeholder="{{ __('Max number of buddies') }}" min="1" max="10">
                        </div>
                        <div class="mb-2">
                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="{{ __('Details, plans, what you want to do…') }}"></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm w-100">{{ __('Post Request') }}</button>
                    </form>
                </div>
            </div>

            {{-- Info box --}}
            <div class="card dc-card">
                <div class="card-body small">
                    <strong>ℹ️ {{ __('Reminder') }}</strong>
                    <ul class="mb-0 mt-1">
                        <li>{{ __('FFESSM: A Directeur de Plongée (N4+) is required on site unless all divers are N3+.') }}</li>
                        <li>{{ __('Levels 1 & 2 need a Guide de Palanquée (N4/P4) or instructor to dive.') }}</li>
                        <li>{{ __('Uncertified divers need an instructor.') }}</li>
                        <li>{{ __('Always check your medical certificate is valid before diving.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('buddySite')?.addEventListener('change', function() {
        document.getElementById('buddyLocationText').style.display = this.value ? 'none' : 'block';
    });
    </script>
</x-layout>
