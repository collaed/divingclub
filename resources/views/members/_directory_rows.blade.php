@foreach($members as $m)
    <tr>
        <td style="width:50px">
            <a href="{{ route('admin.profile.show', $m) }}">
            @if($m->detail?->avatar_path)
                <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
            @else
                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <span class="text-white small">{{ strtoupper(substr($m->detail?->first_name ?? '?', 0, 1) . substr($m->detail?->last_name ?? '', 0, 1)) }}</span>
                </div>
            @endif
            </a>
        </td>
        <td><a href="{{ route('admin.profile.show', $m) }}" class="text-decoration-none text-body">{{ $m->detail?->first_name }} {{ $m->detail?->last_name }}</a></td>
        <td>{{ $m->detail?->certification_level ?? '—' }}</td>
        <td>{{ $m->status?->name ?? '—' }}</td>
        <td>{{ $m->detail?->adhesion_year ?? '—' }}</td>
    </tr>
@endforeach
