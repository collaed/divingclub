<x-layout :title="__('Trial Dive Requests')">
    <h4>@icon('🐠') {{ __('Trial Dive Requests') }}</h4>

    <div class="card dc-card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Preferred') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td class="small">{{ $req->created_at->format('d/m/Y') }}</td>
                        <td>{{ $req->first_name }} {{ $req->last_name }}</td>
                        <td class="small"><a href="mailto:{{ $req->email }}">{{ $req->email }}</a></td>
                        <td class="small">{{ $req->phone ?? '—' }}</td>
                        <td class="small">{{ $req->preferred_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.trial-requests.update', $req) }}" class="d-flex gap-1">
                                @csrf @method('PUT')
                                <select name="status" class="form-select form-select-sm" style="width:120px">
                                    @foreach(['pending','confirmed','completed','cancelled'] as $s)
                                        <option value="{{ $s }}" @selected($req->status === $s)>{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                                <input type="date" name="confirmed_date" class="form-control form-control-sm" style="width:130px" value="{{ $req->confirmed_date?->format('Y-m-d') }}">
                                <button class="btn btn-sm btn-primary px-2">💾</button>
                            </form>
                        </td>
                        <td class="small">
                            @if($req->message)<span title="{{ $req->message }}">💬</span>@endif
                            @if($req->admin_notes)<span title="{{ $req->admin_notes }}">📝</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">{{ __('No trial requests yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layout>
