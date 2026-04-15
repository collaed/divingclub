<x-admin-layout :title="__('Partnerships')">
<div class="container py-4" style="max-width:700px">
    <h2>Add Partner Club</h2>

    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <form method="POST" action="{{ route('admin.partnerships.store') }}">
        @csrf
        <h5 class="mt-3">Partner Info</h5>
        <div class="mb-3">
            <label class="form-label">Club Name</label>
            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Base URL (their instance)</label>
            <input type="url" name="base_url" class="form-control" placeholder="https://partner-club.divingclub.eu" required value="{{ old('base_url') }}">
        </div>

        <h5 class="mt-4">Inbound Credentials (they call us)</h5>
        <p class="text-muted small">Share these with the partner club so they can connect to your API.</p>
        <div class="mb-3">
            <label class="form-label">Key ID</label>
            <input type="text" name="api_key_id" class="form-control font-monospace" value="{{ $keys['key_id'] }}" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Secret <span class="text-danger">(copy now — cannot be retrieved later)</span></label>
            <input type="text" name="api_secret" class="form-control font-monospace" value="{{ $keys['secret'] }}" readonly>
        </div>

        <h5 class="mt-4">Outbound Credentials (we call them) <span class="text-muted">— optional</span></h5>
        <p class="text-muted small">Enter the credentials the partner club gives you, so you can browse their events.</p>
        <div class="mb-3">
            <label class="form-label">Their Key ID</label>
            <input type="text" name="their_api_key_id" class="form-control font-monospace" value="{{ old('their_api_key_id') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Their Secret</label>
            <input type="text" name="their_api_secret" class="form-control font-monospace" value="{{ old('their_api_secret') }}">
        </div>

        <button type="submit" class="btn btn-primary">Create Partnership</button>
        <a href="{{ route('admin.partnerships.index') }}" class="btn btn-link">Cancel</a>
    </form>
</div>
</x-admin-layout>
