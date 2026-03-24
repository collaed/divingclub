<x-layout :title="__('Add Equipment')">
    <h4 class="mb-4">{{ __('Add Equipment') }}</h4>
    <div class="card dc-card"><div class="card-body">
        <form method="POST" action="{{ route('admin.equipment.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">{{ __('Name') }} *</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required>@error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-3"><label class="form-label">{{ __('Type') }} *</label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach(['bcd','regulator','tank','wetsuit','mask','fins','computer','other'] as $t)
                            <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3"><label class="form-label">{{ __('Condition') }}</label>
                    <select name="condition" class="form-select @error('condition') is-invalid @enderror">
                        @foreach(['new','good','fair','poor'] as $c) <option value="{{ $c }}">{{ ucfirst($c) }}</option> @endforeach
                    </select>
                    @error('condition') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2"><label class="form-label">{{ __('Short #') }}</label><input type="text" name="short_number" class="form-control @error('short_number') is-invalid @enderror" maxlength="10" placeholder="e.g. 12, M3">@error('short_number') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-4"><label class="form-label">{{ __('Serial Number') }}</label><input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror">@error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-md-4"><label class="form-label">{{ __('Purchase Date') }}</label><input type="date" name="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror">@error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"></textarea>@error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
            </div>
            <button class="btn btn-primary mt-3">{{ __('Create') }}</button>
        </form>
    </div></div>
</x-layout>
