<x-admin-layout :title="$site->exists ? __('Edit Dive Site') : __('New Dive Site')">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.dive-sites.index') }}">{{ __('Dive Sites') }}</a></li><li class="breadcrumb-item active">{{ $site->exists ? $site->name : __('New') }}</li></ol></nav>

    <div class="card dc-card">
        <div class="card-body">
            <form method="POST" action="{{ $site->exists ? route('admin.dive-sites.update', $site) : route('admin.dive-sites.store') }}" enctype="multipart/form-data">
                @csrf
                @if($site->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">{{ __('Name') }} *</label>
                        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $site->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="country">{{ __('Country') }}</label>
                        <input type="text" id="country" name="country" class="form-control" value="{{ old('country', $site->country) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="region">{{ __('Region') }}</label>
                        <input type="text" id="region" name="region" class="form-control" value="{{ old('region', $site->region) }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="water_type">{{ __('Water Type') }}</label>
                        <select id="water_type" name="water_type" class="form-select">
                            <option value="">—</option>
                            @foreach(\App\Models\DiveSite::WATER_TYPES as $t)
                                <option value="{{ $t }}" @selected(old('water_type', $site->water_type) === $t)>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="max_depth">{{ __('Max Depth') }} (m)</label>
                        <input type="number" id="max_depth" name="max_depth" class="form-control" value="{{ old('max_depth', $site->max_depth) }}" min="1" max="300">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="latitude">{{ __('Latitude') }}</label>
                        <input type="text" id="latitude" name="latitude" class="form-control" value="{{ old('latitude', $site->latitude) }}" placeholder="49.6116">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="longitude">{{ __('Longitude') }}</label>
                        <input type="text" id="longitude" name="longitude" class="form-control" value="{{ old('longitude', $site->longitude) }}" placeholder="6.1319">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="conditions">{{ __('Conditions') }}</label>
                        <textarea id="conditions" name="conditions" class="form-control" rows="3">{{ old('conditions', $site->conditions) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="marine_life">{{ __('Marine Life') }}</label>
                        <textarea id="marine_life" name="marine_life" class="form-control" rows="3">{{ old('marine_life', $site->marine_life) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="safety_notes">{{ __('Safety Notes') }}</label>
                        <textarea id="safety_notes" name="safety_notes" class="form-control" rows="3">{{ old('safety_notes', $site->safety_notes) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="access_notes">{{ __('Access Notes') }}</label>
                        <textarea id="access_notes" name="access_notes" class="form-control" rows="3">{{ old('access_notes', $site->access_notes) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="facilities">{{ __('Facilities') }}</label>
                        <textarea id="facilities" name="facilities" class="form-control" rows="2">{{ old('facilities', $site->facilities) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="food_options">@icon('🍽️') {{ __('Food & Drink Nearby') }}</label>
                        <textarea id="food_options" name="food_options" class="form-control" rows="2" placeholder="{{ __('Restaurants, cafés, snack bars near the site…') }}">{{ old('food_options', $site->food_options) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nearest_hospital">@icon('🏥') {{ __('Nearest Hospital') }}</label>
                        <textarea id="nearest_hospital" name="nearest_hospital" class="form-control" rows="2">{{ old('nearest_hospital', $site->nearest_hospital) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="website_url">{{ __('Website') }}</label>
                        <input type="url" id="website_url" name="website_url" class="form-control" value="{{ old('website_url', $site->website_url) }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="entry_fee">{{ __('Entry Fee') }} (€)</label>
                        <input type="number" id="entry_fee" name="entry_fee" class="form-control" value="{{ old('entry_fee', $site->entry_fee) }}" step="0.01" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="booking_url">{{ __('Booking URL') }}</label>
                        <input type="url" id="booking_url" name="booking_url" class="form-control" value="{{ old('booking_url', $site->booking_url) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="image">{{ __('Site Image') }}</label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        @if($site->image_path)
                            <img src="{{ asset('storage/' . $site->image_path) }}" class="mt-2 rounded" style="max-height:100px">
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="map_image">{{ __('Map Image') }}</label>
                        <input type="file" id="map_image" name="map_image" class="form-control" accept="image/*">
                        @if($site->map_image_path)
                            <img src="{{ asset('storage/' . $site->map_image_path) }}" class="mt-2 rounded" style="max-height:100px">
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="site_plan">{{ __('Site Plan') }} (PDF/image)</label>
                        <input type="file" id="site_plan" name="site_plan" class="form-control" accept="image/*,.pdf">
                        @if($site->site_plan_path)
                            <a href="{{ asset('storage/' . $site->site_plan_path) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">@icon('📄') {{ __('View current plan') }}</a>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="safety_docs_folder">@icon('📋') {{ __('Safety Docs Folder') }}</label>
                        <input type="text" id="safety_docs_folder" name="safety_docs_folder" class="form-control" value="{{ old('safety_docs_folder', $site->safety_docs_folder) }}" placeholder="{{ __('e.g. safety/vodelée — folder in Document Library') }}">
                        <small class="text-muted">{{ __('Folder path in the Document Library containing safety documents for this site.') }}</small>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $site->is_active ?? true))>
                            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary">{{ $site->exists ? __('Update') : __('Create') }}</button>
                    <a href="{{ route('admin.dive-sites.index') }}" class="btn btn-outline-secondary ms-2">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
