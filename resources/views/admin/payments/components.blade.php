<x-layout :title="__('Fee Components')">
    <h4 class="mb-4">{{ __('Fee Components') }}</h4>

    <div class="table-responsive mb-4">
        <table class="table table-sm">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Base') }}</th><th>{{ __('Optional') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($components as $c)
                <tr>
                    <td>{{ $c->name }}</td><td><code>{{ $c->slug }}</code></td><td>€{{ number_format($c->amount, 2) }}</td>
                    <td>@if($c->is_base) ✓ @endif</td><td>@if($c->is_optional) ✓ @endif</td>
                    <td><form method="POST" action="{{ route('admin.payments.component.destroy', $c) }}" class="d-inline">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">✕</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card dc-card">
        <div class="card-header">{{ __('Add Component') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.payments.component.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" placeholder="{{ __('Name') }}" required>@error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-2"><input type="text" name="slug" class="form-control form-control-sm @error('slug') is-invalid @enderror" placeholder="{{ __('Slug') }}" required>@error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm @error('amount') is-invalid @enderror" placeholder="{{ __('Amount') }}" step="0.01" required>@error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                    <div class="col-md-2">
                        <div class="form-check form-check-inline"><input type="checkbox" name="is_base" value="1" class="form-check-input"><label class="form-check-label small">{{ __('Base') }}</label></div>
                        <div class="form-check form-check-inline"><input type="checkbox" name="is_optional" value="1" class="form-check-input"><label class="form-check-label small">{{ __('Optional') }}</label></div>
                    </div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary">{{ __('Add') }}</button></div>
                </div>
            </form>
        </div>
    </div>
</x-layout>
