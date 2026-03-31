<x-layout :title="__('Minors & Parental Consent')">
    <h4 class="mb-4">{{ __('Minors & Parental Consent') }}</h4>

    @if($minors->isEmpty())
        <div class="alert alert-info">{{ __('No members under 18 found.') }}</div>
    @else
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>{{ __('Minor') }}</th><th>{{ __('Age') }}</th><th>{{ __('Guardian(s)') }}</th><th>{{ __('Consents') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($minors as $minor)
                <tr>
                    <td>{{ $minor->name }}</td>
                    <td>{{ $minor->detail->date_of_birth->age }} {{ __('yrs') }}</td>
                    <td>
                        @forelse($minor->guardians as $g)
                            <span class="badge bg-secondary">{{ $g->name }} ({{ $g->pivot->relationship }})</span>
                            <form method="POST" onsubmit="return confirm('Unlink?')" action="{{ route('admin.guardians.unlink', $g->pivot->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove guardian link?') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0">✕</button>
                            </form>
                        @empty
                            <span class="text-danger small">{{ __('No guardian linked') }}</span>
                        @endforelse
                    </td>
                    <td>
                        @php $consents = $minor->parentalConsents->keyBy('consent_type'); @endphp
                        @foreach(['general', 'events', 'photos', 'medical'] as $type)
                            @php $c = $consents[$type] ?? null; @endphp
                            <span class="badge bg-{{ $c && $c->granted ? 'success' : 'warning text-dark' }}">
                                {{ ucfirst($type) }}
                                @if($c && $c->granted) @icon('✓') @else @icon('✗') @endif
                            </span>
                        @endforeach
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#minor{{ $minor->id }}">{{ __('Manage') }}</button>
                    </td>
                </tr>
                <tr class="collapse" id="minor{{ $minor->id }}">
                    <td colspan="5">
                        <div class="row g-3 p-3 bg-light rounded">
                            {{-- Link guardian --}}
                            <div class="col-md-6">
                                <h6>{{ __('Link Guardian') }}</h6>
                                <form method="POST" action="{{ route('admin.guardians.link') }}" class="row g-2">
                                    @csrf
                                    <input type="hidden" name="minor_user_id" value="{{ $minor->id }}">
                                    <div class="col-md-5">
                                        <select name="guardian_user_id" class="form-select form-select-sm" required>
                                            <option value="">{{ __('Select member...') }}</option>
                                            @foreach(\App\Models\User::whereHas('detail')->orderBy('primary_email')->get() as $u)
                                                @if($u->id !== $minor->id)
                                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="relationship" class="form-select form-select-sm">
                                            <option value="parent">{{ __('Parent') }}</option>
                                            <option value="legal_guardian">{{ __('Legal Guardian') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3"><button class="btn btn-sm btn-primary">{{ __('Link') }}</button></div>
                                </form>
                            </div>
                            {{-- Record consent --}}
                            <div class="col-md-6">
                                <h6>{{ __('Record Consent') }}</h6>
                                <form method="POST" action="{{ route('admin.guardians.consent') }}" enctype="multipart/form-data" class="row g-2">
                                    @csrf
                                    <input type="hidden" name="minor_user_id" value="{{ $minor->id }}">
                                    <div class="col-md-4">
                                        <select name="consent_type" class="form-select form-select-sm" required>
                                            <option value="general">{{ __('General') }}</option>
                                            <option value="events">{{ __('Events') }}</option>
                                            <option value="photos">{{ __('Photos') }}</option>
                                            <option value="medical">{{ __('Medical') }}</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="file" name="document" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="col-md-3"><button class="btn btn-sm btn-success">{{ __('Grant') }}</button></div>
                                </form>
                                {{-- Existing consents with revoke --}}
                                @foreach($minor->parentalConsents->where('granted', true) as $c)
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small>{{ ucfirst($c->consent_type) }} — {{ __('by') }} {{ $c->grantedBy?->name }} · {{ $c->granted_at->format('d/m/Y') }}
                                            @if($c->document_path) <a href="{{ route('admin.guardians.consent.download', $c) }}">📎</a> @endif
                                        </small>
                                        <form method="POST" onsubmit="return confirm('Revoke?')" action="{{ route('admin.guardians.consent.revoke', $c) }}" onsubmit="return confirm('{{ __('Revoke this consent?') }}')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger py-0">{{ __('Revoke') }}</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-layout>
