<x-admin-layout :title="__('Email System')">
    <h4 class="mb-4">{{ __('Email System') }}</h4>

    <div class="row">
        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Templates') }}</div>
                <div class="card-body">
                    @foreach($templates as $t)
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $t->name }}</strong>
                                <form method="POST" action="{{ route('admin.email.template.destroy', $t) }}" class="d-inline">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">✕</button></form>
                            </div>
                            <small class="text-muted">{{ $t->slug }} · {{ $t->locale }}</small>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.email.template.store') }}" class="mt-3">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Name') }}" required></div>
                            <div class="col-md-3"><input type="text" name="slug" class="form-control form-control-sm" placeholder="{{ __('Slug') }}" required></div>
                            <div class="col-md-2"><input type="text" name="locale" class="form-control form-control-sm" value="en" required></div>
                            <div class="col-12"><input type="text" name="subject" class="form-control form-control-sm" placeholder="@lang('Subject') (use @{{first_name}} etc.)" required></div>
                            <div class="col-12"><textarea name="body" class="form-control form-control-sm" rows="4" placeholder="{{ __('Body') }}" required></textarea></div>
                            <div class="col-12"><button class="btn btn-sm btn-primary">{{ __('Create Template') }}</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dc-card mb-4">
                <div class="card-header">{{ __('Send Email') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.email.send') }}">
                        @csrf
                        <div class="mb-2">
                            <select name="template_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('Select template...') }}</option>
                                @foreach($templates as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <select name="group" class="form-select form-select-sm" required>
                                <option value="">{{ __('Select group...') }}</option>
                                @foreach(['all' => 'All Members', 'active' => 'Active Members', 'instructors' => 'Instructors', 'bureau' => 'Bureau', 'expiring_certs' => 'Expiring Certificates', 'unpaid' => 'Unpaid Memberships'] as $k => $v)
                                    <option value="{{ $k }}">{{ __($v) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm">{{ __('Send') }}</button>
                    </form>
                    <p class="small text-muted mt-2">@lang('Variables'): @{{first_name}}, @{{last_name}}, @{{name}}, @{{email}}, @{{club_name}}</p>
                </div>
            </div>

            <div class="card dc-card">
                <div class="card-header">{{ __('Email Log') }}</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('To') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th></tr></thead>
                        <tbody>
                        @foreach($log as $l)
                            <tr>
                                <td class="small">{{ $l->to_email }}</td>
                                <td class="small">{{ Str::limit($l->subject, 30) }}</td>
                                <td><span class="badge bg-{{ $l->status === 'sent' ? 'success' : ($l->status === 'failed' ? 'danger' : 'warning') }}" style="font-size:0.6rem">{{ $l->status }}</span></td>
                                <td class="small">{{ $l->created_at->format('d/m H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
