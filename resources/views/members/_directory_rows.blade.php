@foreach($members as $m)
    <tr>
        <td style="width:50px">
            <a href="{{ route('members.profile', $m) }}">
            @if($m->detail?->avatar_path)
                <img src="{{ asset('storage/' . $m->detail->avatar_path) }}" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
            @else
                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <span class="text-white small">{{ strtoupper(substr($m->detail?->first_name ?? '?', 0, 1) . substr($m->detail?->last_name ?? '', 0, 1)) }}</span>
                </div>
            @endif
            </a>
        </td>
        <td data-label="{{ __('Name') }}"><a href="{{ route('members.profile', $m) }}" class="text-decoration-none text-body">{{ $m->detail?->first_name }} {{ $m->detail?->last_name }}</a></td>
        @php $scubaCert = $m->primaryCertification(); $freedivingCert = $m->primaryFreedivingCertification(); @endphp
        <td data-label="{{ __('Scuba') }}">
            @if($scubaCert)
                <span class="badge bg-success">★</span> {{ $scubaCert->code }}
            @else
                —
            @endif
        </td>
        <td data-label="{{ __('Freediving') }}">
            @if($freedivingCert)
                <span class="badge bg-info">★</span> {{ $freedivingCert->code }}
            @else
                —
            @endif
        </td>
        <td data-label="{{ __('Nationality') }}">{{ $m->detail?->nationality ?? '—' }}</td>
        <td data-label="{{ __('Member Since') }}">{{ $m->detail?->adhesion_year ?? '—' }}</td>
    </tr>
@endforeach
