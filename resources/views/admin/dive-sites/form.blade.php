<x-layout :title="$site->exists ? __('Edit Dive Site') : __('New Dive Site')">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.dive-sites.index') }}">{{ __('Dive Sites') }}</a></li><li class="breadcrumb-item active">{{ $site->exists ? $site->name : __('New') }}</li></ol></nav>

    <div class="card dc-card">
        <div class="card-body">
            <form method="POST" action="{{ $site->exists ? route('admin.dive-sites.update', $site) : route('admin.dive-sites.store') }}" enctype="multipart/form-data">
                @csrf
                @if($site->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Name') }} *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $site->name) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Country') }}</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country', $site->country) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Region') }}</label>
                        <input type="text" name="region" class="form-control" value="{{ old('region', $site->region) }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">{{ __('Water Type') }}</label>
                        <select name="water_type" class="form-select">
                            <option value="">—</option>
                            @foreach(\App\Models\DiveSite::WATER_TYPES as $t)
                                <option value="{{ $t }}" @selected(old('water_type', $site->water_type) === $t)>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Max Depth') }} (m)</label>
                        <input type="number" name="max_depth" class="form-control" value="{{ old('max_depth', $site->max_depth) }}" min="1" max="300">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Latitude') }}</label>
                        <input type="text" name="latitude" class="form-control" value="{{ old('latitude', $site->latitude) }}" placeholder="49.6116">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Longitude') }}</label>
                        <input type="text" name="longitude" class="form-control" value="{{ old('longitude', $site->longitude) }}" placeholder="6.1319">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('Conditions') }}</label>
                        <textarea name="conditions" class="form-control" rows="3">{{ old('conditions', $site->conditions) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Marine Life') }}</label>
                        <textarea name="marine_life" class="form-control" rows="3">{{ old('marine_life', $site->marine_life) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Safety Notes') }}</label>
                        <textarea name="safety_notes" class="form-control" rows="3">{{ old('safety_notes', $site->safety_notes) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Access Notes') }}</label>
                        <textarea name="access_notes" class="form-control" rows="3">{{ old('access_notes', $site->access_notes) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Facilities') }}</label>
                        <textarea name="facilities" class="form-control" rows="2">{{ old('facilities', $site->facilities) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">🏥 {{ __('Nearest Hospital') }}</label>
                        <textarea name="nearest_hospital" class="form-control" rows="2">{{ old('nearest_hospital', $site->nearest_hospital) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Website') }}</label>
                        <input type="url" name="website_url" class="form-control" value="{{ old('website_url', $site->website_url) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('Site Image') }}</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        @if($site->image_path)
                            <img src="{{ asset('storage/' . $site->image_path) }}" class="mt-2 rounded" style="max-height:100px">
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Map Image') }}</label>
                        <input type="file" name="map_image" class="form-control" accept="image/*">
                        @if($site->map_image_path)
                            <img src="{{ asset('storage/' . $site->map_image_path) }}" class="mt-2 rounded" style="max-height:100px">
                        @endif
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
</x-layout>
