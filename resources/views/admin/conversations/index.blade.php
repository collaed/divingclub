<x-admin-layout :title="__('Start a Conversation')">
    <h4 class="mb-4">@icon('✉️') {{ __('Start a Conversation') }}</h4>

    <p class="text-muted">
        {{ __('Write to an external contact on behalf of the club. Your personal address stays private — replies come back through the club.') }}
    </p>

    <div class="row">
        <div class="col-lg-7">
            <div class="card dc-card mb-4">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.conversations.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">{{ __('Recipient email') }}</label>
                            <input type="email" name="external_email" list="recent-addresses"
                                class="form-control @error('external_email') is-invalid @enderror"
                                value="{{ old('external_email') }}" required>
                            <datalist id="recent-addresses">
                                @foreach($recentAddresses as $addr)
                                    <option value="{{ $addr->external_email }}">{{ $addr->external_name }}</option>
                                @endforeach
                            </datalist>
                            @error('external_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Recipient name') }}</label>
                            <input type="text" name="external_name"
                                class="form-control @error('external_name') is-invalid @enderror"
                                value="{{ old('external_name') }}">
                            @error('external_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Related event') }} <span class="text-muted">({{ __('optional') }})</span></label>
                            <select name="event_id" class="form-select @error('event_id') is-invalid @enderror">
                                <option value="">{{ __('None') }}</option>
                                @foreach($events as $event)
                                    <option value="{{ $event->id }}" {{ old('event_id') == $event->id ? 'selected' : '' }}>
                                        {{ $event->event_date?->format('d/m/Y') }} — {{ $event->title }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Linking an event appends this conversation to the event page.') }}</div>
                            @error('event_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Subject') }}</label>
                            <input type="text" name="subject"
                                class="form-control @error('subject') is-invalid @enderror"
                                value="{{ old('subject') }}" required>
                            @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Message') }}</label>
                            <textarea name="message" rows="8"
                                class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                            @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Send') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Recent contacts') }}</div>
                <div class="card-body">
                    @if($recentAddresses->isEmpty())
                        <p class="text-muted">{{ __('No contacts yet.') }}</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($recentAddresses->take(15) as $addr)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span>
                                        <strong>{{ $addr->external_name ?: $addr->external_email }}</strong><br>
                                        <small class="text-muted">{{ $addr->external_email }}</small>
                                    </span>
                                    <span class="badge bg-secondary rounded-pill">{{ $addr->hits }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
