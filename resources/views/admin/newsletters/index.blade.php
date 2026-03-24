<x-layout :title="__('Newsletters')">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">📬 {{ __('Newsletters') }}</h4>
        <a href="{{ route('admin.newsletters.create') }}" class="btn btn-primary btn-sm">+ {{ __('New Newsletter') }}</a>
    </div>

    @if($newsletters->isEmpty())
        <div class="alert alert-info">{{ __('No newsletters yet.') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Month') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Approvals') }}</th>
                        <th>{{ __('Created by') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($newsletters as $nl)
                        <tr>
                            <td><a href="{{ route('admin.newsletters.show', $nl) }}">{{ $nl->title }}</a></td>
                            <td>{{ $nl->month }}</td>
                            <td>
                                @php $badges = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','sent'=>'info']; @endphp
                                <span class="badge bg-{{ $badges[$nl->status] ?? 'secondary' }}">{{ ucfirst($nl->status) }}</span>
                            </td>
                            <td>{{ $nl->approvals->where('approved', true)->count() }}/3</td>
                            <td>{{ $nl->creator?->name }}</td>
                            <td>
                                @if($nl->status !== 'sent')
                                    <a href="{{ route('admin.newsletters.edit', $nl) }}" class="btn btn-outline-secondary btn-sm">{{ __('Edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $newsletters->links() }}
    @endif
</x-layout>
