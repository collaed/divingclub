<x-admin-layout :title="__('Vote Groups')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">🗳️ {{ __('Vote Groups') }}</h4>
        <a href="{{ route('admin.vote-groups.create') }}" class="btn btn-primary btn-sm">+ {{ __('New Group') }}</a>
    </div>

    @if($groups->isEmpty())
        <p class="text-muted">{{ __('No vote groups yet.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Questions') }}</th>
                    <th>{{ __('Tokens') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Created') }}</th>
                </tr></thead>
                <tbody>
                @foreach($groups as $g)
                    <tr data-href="{{ route('admin.vote-groups.show', $g) }}" class="clickable-row">
                        <td><strong>{{ $g->title }}</strong></td>
                        <td>{{ $g->votes_count }}</td>
                        <td>{{ $g->tokens_count }}</td>
                        <td>
                            @php $badges = ['draft'=>'secondary','open'=>'success','closed'=>'dark']; @endphp
                            <span class="badge bg-{{ $badges[$g->status] ?? 'secondary' }}">{{ ucfirst($g->status) }}</span>
                        </td>
                        <td>{{ $g->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
